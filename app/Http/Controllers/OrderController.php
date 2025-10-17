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
use Carbon\Carbon;
use App\Models\FinancialRecord;

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
        // 从中间件获取的用户ID
        $userId = $request->user_id_from_token ?? null;

        if (!$userId) {
            return ApiResponse::error(ApiCode::USER_NOT_FOUND);
        }

        $page = $request->input('page', 1);  // 当前页，默认是第1页
        $pageSize = $request->input('pagesize', 100);  // 每页显示的记录数，默认是10条
        
        // 构建查询
        $query = Order::where('buy_user_id', $userId);

        if ($request->channel) {
            $query->where('payment_method', $request->channel);
        }

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
        // 从中间件获取的用户ID
        $userId = $request->user_id_from_token ?? null;

        if (!$userId) {
            return ApiResponse::error(ApiCode::USER_NOT_FOUND);
        }

        $page = $request->input('page', 1);  // 当前页，默认是第1页
        $pageSize = $request->input('pagesize', 100);  // 每页显示的记录数，默认是10条

        // 构建查询
        $query = Order::where('sell_user_id', $userId);

        if ($request->channel) {
            $query->where('payment_method', $request->channel);
        }

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

    
    /**
     * 获取订单详情
     */
    public function getOrderDetail(Request $request)
    {
        // 验证输入参数
        $validator = Validator::make($request->all(), [
            'orderId' => 'required',
        ]);

        $order = Order::where('id', $request->orderId)->first();

        return ApiResponse::success([
            'order' => $order,
        ]);
    }

    /**
     * 确认订单
     */
    public function orderConfirm(Request $request)
    {
        // 验证输入参数
        $validator = Validator::make($request->all(), [
            'orderId' => 'required',
            'role' => 'required|in:buyer,seller',
        ]);

        // 从中间件获取的用户ID
        $userId = $request->user_id_from_token ?? null;

        if (!$userId) {
            return ApiResponse::error(ApiCode::USER_NOT_FOUND);
        }

        $order = Order::where('id', $request->orderId)->first();
        if (!$order) {
            return ApiResponse::error(ApiCode::ORDER_NOT_FOUND);
        }

        if ($request->role === 'buyer') {
            $order->status = 1;
        } else if ($request->role === 'seller') {
            $order->status = 2;
        }

        $this->generateTransaction($order, $request->role);

        return ApiResponse::success([
            'order' => $order,
        ]);
    }

    protected function generateTransaction($order, $role) {

        $date = Carbon::now()->format('YmdHis'); // 获取当前日期和时间，格式：202506021245
        $randomNumber = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT); // 生成 4 位随机数，填充 0
        $transaction_id = $date . $randomNumber;

        DB::transaction(function() use ($order, $role, $transaction_id) {
            $order->save();

            if ($role === 'buyer') {
                $userId = $order->buy_user_id;
            } else {
                $userId = $order->sell_user_id;
            }

            FinancialRecord::create([
                'transaction_id' => $transaction_id,
                'user_id' => $userId,
                'amount' => $order->total_price,
                'exchange_rate' => $order->exchange_rate,
                'cny_amount' => $order->total_cny_price,
                'fee' => 0.00,
                'actual_amount' => $order->total_price,
                'balance_before' => 0.00,
                'balance_after' => 0.00,
                'transaction_type'=> 'order',
                'order_id'=> $order->id,
                'payment_method'=> $order->payment_method,
            ]);
        });
    }

    public function getMyOrderReport(Request $request)
    {
        // 从中间件获取的用户ID
        $userId = $request->user_id_from_token ?? null;

        if (!$userId) {
            return ApiResponse::error(ApiCode::USER_NOT_FOUND);
        }

        // 获取前端传入的开始时间和结束时间
        $startTime = $request->input('startTime', '');
        $endTime = $request->input('endTime', '');

        // 如果没有传入时间，则默认是今天
        if (empty($startTime) && empty($endTime)) {
            $startDate = Carbon::today()->startOfDay();  // 今天的 00:00:00
            $endDate = Carbon::today()->endOfDay();  // 今天的 23:59:59
        } else {
            // 如果传入了时间，则按照时间处理
            $startDate = $startTime ? Carbon::createFromFormat('Y-m-d', $startTime)->startOfDay() : null;
            $endDate = $endTime ? Carbon::createFromFormat('Y-m-d', $endTime)->endOfDay() : null;

            // 如果没有传入结束时间，默认为当前时间
            if (!$endDate && $startDate) {
                $endDate = Carbon::now()->endOfDay();
            }
        }

        // 构建查询
        $query = Order::where('sell_user_id', $userId)
                    ->where('status', 2);

        // 如果传入了时间范围，则加入时间条件
        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        // 获取符合条件的订单总数
        $totalCount = $query->count();

        $orders = $query->get();

        // 获取订单金额总数
        $totalAmount = $query->sum('amount');

        return ApiResponse::success([
            'totalCount' => $totalCount,  // 总订单数
            'totalAmount' => $totalAmount,  // 总金额
            'orders' => $orders, // 所有订单
        ]);
    }

    public function getGroupOrderReport(Request $request)
    {
        // 从中间件获取的用户ID
        $userId = $request->user_id_from_token ?? null;

        if (!$userId) {
            return ApiResponse::error(ApiCode::USER_NOT_FOUND);
        }

        // 获取前端传入的开始时间和结束时间
        $startTime = $request->input('startTime', '');
        $endTime = $request->input('endTime', '');

        // 如果没有传入时间，则默认是今天
        if (empty($startTime) && empty($endTime)) {
            $startDate = Carbon::today()->startOfDay();  // 今天的 00:00:00
            $endDate = Carbon::today()->endOfDay();  // 今天的 23:59:59
        } else {
            // 如果传入了时间，则按照时间处理
            $startDate = $startTime ? Carbon::createFromFormat('Y-m-d', $startTime)->startOfDay() : null;
            $endDate = $endTime ? Carbon::createFromFormat('Y-m-d', $endTime)->endOfDay() : null;

            // 如果没有传入结束时间，默认为当前时间
            if (!$endDate && $startDate) {
                $endDate = Carbon::now()->endOfDay();
            }
        }

        // 获取当前用户的 rootAgentId 对应的所有用户 userIds
        $userIds = User::where('root_agent_id', $userId)->pluck('id')->toArray();

        if (empty($userIds)) {
            return ApiResponse::error(ApiCode::USER_NOT_FOUND);
        }

        // 构建查询
        $query = Order::whereIn('sell_user_id', $userIds)
                    ->where('status', 2);

        // 如果传入了时间范围，则加入时间条件
        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        // 获取符合条件的订单总数
        $totalCount = $query->count();

        // 获取所有符合条件的订单
        $orders = $query->get();

        // 获取订单金额总数
        $totalAmount = $query->sum('amount');

        return ApiResponse::success([
            'totalCount' => $totalCount,  // 总订单数
            'totalAmount' => $totalAmount,  // 总金额
            'orders' => $orders,  // 所有订单
        ]);
    }
}
