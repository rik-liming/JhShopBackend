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

        // 从中间件获取的用户ID
        $userId = $request->user_id_from_token ?? null;

        if (!$userId) {
            return ApiResponse::error(ApiCode::USER_NOT_FOUND);
        }

        $userAccount = UserAccount::select(
            'total_balance',
            'available_balance',
        )
        ->where('user_id', $userId)
        ->first();
        
        if (!$userAccount) {
            return ApiResponse::error(ApiCode::USER_NOT_FOUND);
        }

        if ($request->input('amount') > $userAccount->available_balance) {
            return ApiResponse::error(ApiCode::USER_BALANCE_NOT_ENOUGH);
        }

        // 如果未设置收款信息，无法挂单
        $paymentMethod = UserPaymentMethod::where('status', BusinessDef::PAYMENT_METHOD_ACTIVE)
            ->where('user_id', $userId)
            ->where('payment_method', $request->input('payment_method'))
            ->where('default_payment', BusinessDef::PAYMENT_METHOD_IS_DEFAULT)
            ->first();
        if (!$paymentMethod) {
            return ApiResponse::error(ApiCode::USER_PAYMENT_METHOD_NOT_SET);
        }

        $existingOrderListing = OrderListing::where('user_id', $userId)
            ->whereIn('status', [
                BusinessDef::ORDER_LISTING_STATUS_ONLINE,
                BusinessDef::ORDER_LISTING_STATUS_FROBIDDEN,
                BusinessDef::ORDER_LISTING_STATUS_STOCK_LOCK,
            ])
            ->where('payment_method', $request->input('payment_method'))
            ->first();
        if ($existingOrderListing) {
            return ApiResponse::error(ApiCode::ORDER_LISTING_PAYMENT_METHOD_LIMIT);
        }

        $newOrderListing = DB::transaction(function() use ($request, $userId) {
            // 创建挂单
            $orderListing = OrderListing::create([
                'user_id' => $userId,
                'amount' => $request->input('amount'),
                'remain_amount' => $request->input('amount'),
                'min_sale_amount' => $request->input('min_sale_amount'),
                'payment_method' => $request->input('payment_method'),
                'status' => BusinessDef::ORDER_LISTING_STATUS_ONLINE, // 默认状态为在售
            ]);

            $userAccount = UserAccount::where('user_id', $userId)->first();
            $userAccount->available_balance = bcsub($userAccount->available_balance, $request->input('amount'), 2);
            $userAccount->save();

            return $orderListing;
        });

        // 推送通知挂单更新
        event(new OrderListingUpdated());

        return ApiResponse::success([
            'id' => $newOrderListing->id,
        ]);
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
        // 从中间件获取的用户ID
        $userId = $request->user_id_from_token ?? null;
        $id = $request->id ?? null;

        if (!$userId) {
            return ApiResponse::error(ApiCode::USER_NOT_FOUND);
        }

        if (!$id) {
            return ApiResponse::error(ApiCode::ORDER_LISTING_NOT_FOUND);
        }

        $orderListing = OrderListing::with('orders')->find($id);
        if (!$orderListing) {
            return ApiResponse::error(ApiCode::ORDER_LISTING_NOT_FOUND);
        }

        // 检查所有关联订单状态
        $forbiddenStatuses = [
            BusinessDef::ORDER_STATUS_WAIT_BUYER,
            BusinessDef::ORDER_STATUS_WAIT_SELLER,
            BusinessDef::ORDER_STATUS_ARGUE,
        ];
        foreach ($orderListing->orders as $order) {
            if (in_array($order->status, $forbiddenStatuses)) {
                return ApiResponse::error(ApiCode::ORDER_LISTING_CANCEL_FORBIDDEN);
            }
        }

        DB::beginTransaction();
        try {
            $orderListing->status = BusinessDef::ORDER_LISTING_STATUS_CANCEL;
            $orderListing->save();

            // 撤单需要返还用户冻结的资产
            $userAccount = UserAccount::where('user_id', $userId)
                ->first();
            
            if (!$userAccount) {
                return ApiResponse::error(ApiCode::USER_NOT_FOUND);
            }

            $userAccount->available_balance = bcadd($userAccount->available_balance, $orderListing->remain_amount, 2);
            $userAccount->save();

            DB::commit();
            return ApiResponse::success([]);
        } catch (\Throwable $e) {
            DB::rollBack();
            // report($e);
            return ApiResponse::error(ApiCode::OPERATION_FAIL);
        }
    }
}
