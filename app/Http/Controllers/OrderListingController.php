<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Helpers\ApiResponse;
use App\Enums\ApiCode;
use App\Models\OrderListing;
use App\Models\UserPaymentMethod;
use App\Models\UserAccount;
use App\Models\User;
use App\Models\PlatformConfig;
use App\Enums\BusinessDef;
use App\Events\OrderListingUpdated;

class OrderListingController extends Controller
{
    /**
     * 创建挂单接口
     */
    public function createOrderListing(Request $request)
    {
        // 验证输入参数
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'min_sale_amount' => 'required|numeric|min:0',
            'payment_method' => 'required|in:' . implode(',', [
                BusinessDef::PAYMENT_METHOD_ALIPAY,
                BusinessDef::PAYMENT_METHOD_WECHAT,
                BusinessDef::PAYMENT_METHOD_BANK,
            ]),
        ], [
            'amount.required' => '金额不能为空',
            'amount.min' => '金额至少0.01',
            'min_sale_amount.required' => '最低销售额不能为空',
            'payment_method.required' => '卖场不能为空'
        ]);

        // 从中间件获取用户ID
        $userId = $request->user_id_from_token ?? null;

        if (!$userId) {
            return ApiResponse::error(ApiCode::USER_NOT_FOUND);
        }

        DB::beginTransaction();

        try {

            // 对账户加悲观锁
            $userAccount = UserAccount::where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if (!$userAccount) {
                throw new \Exception('', ApiCode::USER_NOT_FOUND);
            }

            // 再次余额检查（并发安全）
            if ($request->input('amount') > $userAccount->available_balance) {
                throw new \Exception('', ApiCode::USER_BALANCE_NOT_ENOUGH);
            }

            // 检查用户是否设置默认收款方式
            $paymentMethod = UserPaymentMethod::where('status', BusinessDef::PAYMENT_METHOD_ACTIVE)
                ->where('user_id', $userId)
                ->where('payment_method', $request->input('payment_method'))
                ->where('default_payment', BusinessDef::PAYMENT_METHOD_IS_DEFAULT)
                ->first();

            if (!$paymentMethod) {
                throw new \Exception('', ApiCode::USER_PAYMENT_METHOD_NOT_SET);
            }

            // 锁住用户在同卖场的挂单
            $existingOrderListing = OrderListing::where('user_id', $userId)
                ->whereIn('status', [
                    BusinessDef::ORDER_LISTING_STATUS_ONLINE,
                    BusinessDef::ORDER_LISTING_STATUS_FROBIDDEN,
                    BusinessDef::ORDER_LISTING_STATUS_STOCK_LOCK,
                ])
                ->where('payment_method', $request->input('payment_method'))
                ->lockForUpdate()
                ->first();

            if ($existingOrderListing) {
                throw new \Exception('', ApiCode::ORDER_LISTING_PAYMENT_METHOD_LIMIT);
            }

            // 创建挂单
            $orderListing = OrderListing::create([
                'user_id' => $userId,
                'amount' => $request->input('amount'),
                'remain_amount' => $request->input('amount'),
                'min_sale_amount' => $request->input('min_sale_amount'),
                'payment_method' => $request->input('payment_method'),
                'status' => BusinessDef::ORDER_LISTING_STATUS_ONLINE,
            ]);

            // 扣余额
            $userAccount->available_balance = bcsub($userAccount->available_balance, $request->input('amount'), 2);
            $userAccount->save();

            DB::commit();

        } catch (\Throwable $e) {
            DB::rollBack();

            return ApiResponse::error($e->getCode());
        }

        // 推送通知，减少锁持有
        event(new OrderListingUpdated());

        return ApiResponse::success(['id' => $orderListing->id]);
    }

    public function getOrderListingByPage(Request $request)
    {
        // 验证输入参数
        $request->validate([
            'payment_method' => 'required|in:' . implode(',', [
                BusinessDef::PAYMENT_METHOD_ALIPAY,
                BusinessDef::PAYMENT_METHOD_WECHAT,
                BusinessDef::PAYMENT_METHOD_BANK,
            ]),
        ], [
            'payment_method.required' => '卖场不能为空'
        ]);

        // 获取分页参数
        $page = $request->input('page', 1);  // 当前页，默认是第1页
        $pageSize = $request->input('page_size', 100);  // 每页显示的记录数，默认是10条
        $payment_method = $request->input('payment_method', '');  // 搜索关键词，默认空字符串

        // 构建查询
        $query = OrderListing::where('status', BusinessDef::ORDER_LISTING_STATUS_ONLINE)
                     ->where('payment_method', $payment_method)
                     ->orderBy('id', 'desc');

        // 获取符合条件的用户总数
        $totalCount = $query->count();

        // 分页
        $orderListings = $query->skip(($page - 1) * $pageSize)  // 计算分页的偏移量
                    ->take($pageSize)  // 每页获取指定数量的用户
                    ->get();

        return ApiResponse::success([
            'total' => $totalCount,  // 总记录数
            'current_page' => $page,  // 当前页
            'page_size' => $pageSize,  // 每页记录数
            'orderListings' => $orderListings,  // 当前页的挂单列表
        ]);
    }

    /**
     * 获取当前挂单信息
     */
    public function getOrderListing(Request $request)
    {
        $orderListing = OrderListing::find($request->id);

        if (!$orderListing) {
            return ApiResponse::error(ApiCode::ORDER_LISTING_NOT_FOUND);
        }

        return ApiResponse::success([
            'orderListing' => $orderListing,
        ]);
    }

    /**
     * 获取当前挂单信息
     */
    public function getMyOrderListing(Request $request)
    {
        // 从中间件获取的用户ID
        $userId = $request->user_id_from_token ?? null;

        if (!$userId) {
            return ApiResponse::error(ApiCode::USER_NOT_FOUND);
        }

        // 构建查询
        $orderListings = OrderListing::where('user_id', $userId)
            ->where('status', BusinessDef::ORDER_LISTING_STATUS_ONLINE)
            ->orderBy('id', 'desc')
            ->get();

        if (!$orderListings) {
            return ApiResponse::error(ApiCode::ORDER_LISTING_NOT_FOUND);
        }

        return ApiResponse::success([
            'orderListings' => $orderListings,
        ]);
    }

    /**
     * 撤销挂单信息
     */
    public function cancelOrderListing(Request $request)
    {
        $userId = $request->user_id_from_token ?? null;
        $id = $request->id ?? null;

        if (!$userId) {
            return ApiResponse::error(ApiCode::USER_NOT_FOUND);
        }

        if (!$id) {
            return ApiResponse::error(ApiCode::ORDER_LISTING_NOT_FOUND);
        }

        DB::beginTransaction();

        try {

            // 加悲观锁
            $orderListing = OrderListing::where('id', $id)
                ->lockForUpdate()
                ->with('orders')
                ->first();

            if (!$orderListing) {
                throw new \Exception('', ApiCode::ORDER_LISTING_NOT_FOUND);
            }

            // 幂等：已取消则直接返回成功
            if ($orderListing->status === BusinessDef::ORDER_LISTING_STATUS_CANCEL) {
                DB::commit();
                return ApiResponse::success([]);
            }

            // 检查是否存在禁止取消的订单状态
            $forbiddenStatuses = [
                BusinessDef::ORDER_STATUS_WAIT_BUYER,
                BusinessDef::ORDER_STATUS_WAIT_SELLER,
                BusinessDef::ORDER_STATUS_ARGUE,
            ];

            foreach ($orderListing->orders as $order) {
                if (in_array($order->status, $forbiddenStatuses)) {
                    throw new \Exception('', ApiCode::ORDER_LISTING_CANCEL_FORBIDDEN);
                }
            }

            // 修改挂单状态
            $orderListing->status = BusinessDef::ORDER_LISTING_STATUS_CANCEL;
            $orderListing->save();

            // 加悲观锁避免余额并发问题
            $userAccount = UserAccount::where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if (!$userAccount) {
                throw new \Exception('', ApiCode::USER_NOT_FOUND);
            }

            // 返还冻结金额
            $userAccount->available_balance = bcadd(
                $userAccount->available_balance,
                $orderListing->remain_amount,
                2
            );
            $userAccount->save();

            DB::commit();
            return ApiResponse::success([]);

        } catch (\Throwable $e) {
            DB::rollBack();
            return ApiResponse::error($e->getCode());
        }
    }
}
