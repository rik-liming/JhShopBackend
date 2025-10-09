<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Helpers\ApiResponse;
use App\Enums\ApiCode;
use App\Models\PlatformConfig;
use App\Models\UserPaymentMethod;
use App\Models\OrderListing;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    /**
     * 创建订单
     */
    public function create(Request $request)
    {
        $request->validate([
            'order_listing_id' => 'required',
            'amount' => 'required|numeric|min:1', // 购买数量必须大于 0
            'payment_method' => 'required|in:bank,alipay,wechat', // 支付方式必须合法
            'buy_user_id' => 'required|integer|exists:jh_user,id', // 买家必须是存在的用户
            'buy_account_name' => 'required|string', // 买家账户名
            'buy_account_number' => 'required|string', // 买家账户号码
        ], [
            'order_listing_id.required' => '挂单Id不能为空',
        ]);

        // 获取挂单
        $orderListing = OrderListing::find($request->order_listing_id);
        if (!$orderListing) {
            return ApiResponse::error(ApiCode::ORDER_LISTING_NOT_FOUND);
        }

        // 检查库存是否足够
        if ($orderListing->remain_amount < $request->amount) {
            return ApiResponse::error(ApiCode::ORDER_LISTING_AMOUNT_NOT_ENOUGH);
        }

        // todo: 需要反查seller的支付信息

        // todo: 需要获取当前比率

        // 开启事务，确保数据一致性
        DB::beginTransaction();

        try {

            // 创建订单记录
            $order = new Order([
                'order_listing_id' => $orderListing->id,
                'amount' => $request->amount,
                'payment_method' => $request->payment_method,
                'buy_user_id' => $request->buy_user_id,
                'buy_account_name' => $request->buy_account_name,
                'buy_account_number' => $request->buy_account_number,
                'buy_bank_name' => $request->buy_bank_name ?? '',
                'buy_issue_bank_name' => $request->buy_issue_bank_name ?? '',
                'sell_user_id' => $orderListing->user_id,
                'sell_account_name' => '曾智',
                'sell_account_number' => '44244242@qq.com',
                'sell_qr_code' => '',
                'sell_account_name' => '曾智',
                'sell_account_number' => '88045412442',
                'sell_bank_name' => '中国银行',
                'sell_issue_bank_name' => '北京朝阳区支行',
                'exchange_rate' => 7.26,
                'total_price' => $request->amount,
                'total_cny_price' => $request->amount * 7.26,
                'status' => 0, // 初始状态为待支付
            ]);
            $order->save();

            // 更新挂单的剩余库存
            $orderListing->remain_amount -= $request->amount;
            $orderListing->save();

            // 提交事务
            DB::commit();

            return ApiResponse::success([
                'order' => $order,
            ]);
        } catch (\Exception $e) {
            \Log::error('An error occurred: ' . $e->getMessage());
            // 回滚事务
            DB::rollBack();
            return ApiResponse::error(ApiCode::ORDER_CREATE_FAIL);
        }
    }

    public function getMyBuyerOrders(Request $request)
    {
        // 验证输入参数
        $validator = Validator::make($request->all(), [
            'channel' => 'required|in:bank,alipay,wechat',
        ]);

        // 从中间件获取的用户ID
        $userId = $request->user_id_from_token ?? null;

        if (!$userId) {
            return ApiResponse::error(ApiCode::USER_NOT_FOUND);
        }

        $page = $request->input('page', 1);  // 当前页，默认是第1页
        $pageSize = $request->input('pagesize', 100);  // 每页显示的记录数，默认是10条
        $channel = $request->input('channel', '');


        // 构建查询
        $query = Order::where('buy_user_id', $userId)
                     ->where('payment_method', $channel);

        // 获取符合条件的用户总数
        $totalCount = $query->count();

        // 分页
        $orders = $query->skip(($page - 1) * $pageSize)  // 计算分页的偏移量
                    ->take($pageSize)  // 每页获取指定数量的用户
                    ->get();

        return ApiResponse::success([
            'total' => $totalCount,  // 总记录数
            'current_page' => $page,  // 当前页
            'page_size' => $pageSize,  // 每页记录数
            'orders' => $orders,  // 当前页的挂单列表
        ]);
    }

    public function getMySellerOrders(Request $request)
    {
        // 验证输入参数
        $validator = Validator::make($request->all(), [
            'channel' => 'required|in:bank,alipay,wechat',
        ]);

        // 从中间件获取的用户ID
        $userId = $request->user_id_from_token ?? null;

        if (!$userId) {
            return ApiResponse::error(ApiCode::USER_NOT_FOUND);
        }

        $page = $request->input('page', 1);  // 当前页，默认是第1页
        $pageSize = $request->input('pagesize', 100);  // 每页显示的记录数，默认是10条
        $channel = $request->input('channel', '');

        // 构建查询
        $query = Order::where('seller_user_id', $userId)
                     ->where('payment_method', $channel);

        // 获取符合条件的用户总数
        $totalCount = $query->count();

        // 分页
        $orders = $query->skip(($page - 1) * $pageSize)  // 计算分页的偏移量
                    ->take($pageSize)  // 每页获取指定数量的用户
                    ->get();

        return ApiResponse::success([
            'total' => $totalCount,  // 总记录数
            'current_page' => $page,  // 当前页
            'page_size' => $pageSize,  // 每页记录数
            'orders' => $orders,  // 当前页的挂单列表
        ]);
    }
}
