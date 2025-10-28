<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\ApiResponse;
use App\Enums\ApiCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use App\Models\FinancialRecord;
use App\Models\PlatformConfig;
use App\Models\UserPaymentMethod;
use App\Models\OrderListing;
use App\Models\Order;
use App\Models\User;
use App\Models\UserAccount;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;

class OrderController extends Controller
{
    /**
     * 创建订单
     */
    public function create(Request $request)
    {
        $request->validate([
            'order_listing_id' => 'required',
            'cny_amount' => 'required|numeric|min:1', // 下单金额必须大于 0
            'account_name' => 'required|string', // 买家账户名
            'account_number' => 'required|string', // 买家账户号码
            'payment_password' => 'required',
        ], [
            'order_listing_id.required' => '挂单Id不能为空',
            'cny_amount.required' => '购买数量不能为空',
            'cny_amount.min' => '购买数量不能少于1',
            'account_name.required' => '购买人姓名不能为空',
            'account_number.required' => '购买人账户不能为空',
            'payment_password.required' => '支付密码不能为空',
        ]);

        // 从中间件获取的用户ID
        $userId = $request->user_id_from_token ?? null;
        $payment_password = $request->payment_password;

        if (!$userId) {
            return ApiResponse::error(ApiCode::USER_NOT_FOUND);
        }

        $user = User::where('id', $userId)->first();
        if (!$user) {
            return ApiResponse::error(ApiCode::USER_NOT_FOUND);
        }

        $userAccount = UserAccount::where('user_id', $userId)->first();
        if (!$userAccount) {
            return ApiResponse::error(ApiCode::USER_ACCOUNT_NOT_FOUND);
        }

        if (!$userAccount->payment_password) {
            return ApiResponse::error(ApiCode::USER_PAYMENT_PASSWORD_NOT_SET);
        }

        if (!Hash::check($payment_password, $userAccount->payment_password)) {
            return ApiResponse::error(ApiCode::USER_PAYMENT_PASSWORD_WRONG);
        }

        // 获取挂单
        $orderListing = OrderListing::find($request->order_listing_id);
        if (!$orderListing) {
            return ApiResponse::error(ApiCode::ORDER_LISTING_NOT_FOUND);
        }

        // 需要获取当前比率
        $config = PlatformConfig::first();
        if (!$config) {
            return ApiResponse::error(ApiCode::CONFIG_NOT_FOUND);
        }
        
        $currentExchangeRate = 7.25;
        switch ($orderListing->payment_method) {
            case 'alipay':
                $currentExchangeRate = $config->exchange_rate_alipay;
                break;
            case 'wechat':
                $currentExchangeRate = $config->exchange_rate_wechat;
                break;
            case 'bank':
                $currentExchangeRate = $config->exchange_rate_bank;
                break;
            default:
                break;
        }

        // 计算得到cny对应的amount
        $amount = bcdiv($request->cny_amount, $currentExchangeRate, 2);

        // 检查卖家支付信息
        $paymentMethod = UserPaymentMethod::where('status', 1)
            ->where('user_id', $orderListing->user_id)
            ->where('payment_method', $orderListing->payment_method)
            ->first();
        if (!$paymentMethod) {
            return ApiResponse::error(ApiCode::USER_PAYMENT_METHOD_NOT_SET);
        }

        // 检查库存是否足够
        if ($orderListing->remain_amount < $amount) {
            return ApiResponse::error(ApiCode::ORDER_LISTING_AMOUNT_NOT_ENOUGH);
        }

        // 检查是否满足最低购买需求
        if ($orderListing->min_sale_amount > $amount) {
            return ApiResponse::error(ApiCode::ORDER_LISTING_MIN_SALE_AMOUNT_LIMIT);
        }

        // 开启事务，确保数据一致性
        DB::beginTransaction();

        try {

            // display order id
            $date = Carbon::now()->format('YmdHis');
            $randomNumber = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT); // 生成 4 位随机数，填充 0
            $display_order_id = "${date}${randomNumber}";

            // 创建订单记录
            $order = new Order([
                'order_listing_id' => $orderListing->id,
                'display_order_id' => $display_order_id,
                'amount' => $amount,
                'payment_method' => $orderListing->payment_method,
                'buy_user_id' => $userId,
                'buy_account_name' => $request->account_name,
                'buy_account_number' => $request->account_number,
                'buy_bank_name' => $request->bank_name ?? '',
                'buy_issue_bank_name' => $request->issue_bank_name ?? '',
                'sell_user_id' => $orderListing->user_id,
                'sell_account_name' => $paymentMethod->account_name,
                'sell_account_number' => $paymentMethod->account_number,
                'sell_qr_code' => $paymentMethod->qr_code,
                'sell_bank_name' => $paymentMethod->bank_name,
                'sell_issue_bank_name' => $paymentMethod->issue_bank_name,
                'exchange_rate' => $currentExchangeRate,
                'total_price' => $amount,
                'total_cny_price' => $request->cny_amount,
                'status' => 0, // 初始状态为待支付
            ]);
            $order->save();

            // 创建买卖双方的交易记录
            $buyerTransaction = $this->generateTransaction($order, 'order_buy');
            $sellerTransaction = $this->generateTransaction($order, 'order_sell');

            // 记录以便反查
            $order->buy_transaction_id = $buyerTransaction->transaction_id;
            $order->sell_transaction_id = $sellerTransaction->transaction_id;
            $order->save();

            // 更新挂单的剩余库存
            $orderListing->remain_amount = bcsub($orderListing->remain_amount, $amount, 2);
            // 卖完自动下架
            if ($orderListing->remain_amount <= 0) {
                $orderListing->status = 0;
            }
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

    public function autoBuyerCreate(Request $request)
    {
        $request->validate([
            'auto_buyer_id' => 'required',
            'cny_amount' => 'required|numeric|min:1', // 下单金额必须大于 0
            'payment_method' => 'required|in:bank,alipay,wechat',
        ], [
            'auto_buyer_id.required' => '下单用户ID不能为空',
            'cny_amount.required' => '下单金额不能为空',
            'cny_amount.min' => '下单金额不能少于1',
            'payment_method.required' => '支付方式不能为空',
        ]);

        $auto_buyer_id = $request->auto_buyer_id;
        $cny_amount = $request->cny_amount;
        $payment_method = $request->payment_method;

        if (!$auto_buyer_id) {
            return ApiResponse::error(ApiCode::USER_AUTO_BUYER_VERIFY_FAIL);
        }

        $autoBuyer = User::where('id', $auto_buyer_id)
            ->where('role', 'autoBuyer')
            ->first();

        if (!$autoBuyer) {
            return ApiResponse::error(ApiCode::USER_AUTO_BUYER_VERIFY_FAIL);
        }

        // 需要获取当前比率
        $config = PlatformConfig::first();
        if (!$config) {
            return ApiResponse::error(ApiCode::CONFIG_NOT_FOUND);
        }
        
        $currentExchangeRate = 7.25;
        switch ($payment_method) {
            case 'alipay':
                $currentExchangeRate = $config->exchange_rate_alipay;
                break;
            case 'wechat':
                $currentExchangeRate = $config->exchange_rate_wechat;
                break;
            case 'bank':
                $currentExchangeRate = $config->exchange_rate_bank;
                break;
            default:
                break;
        }

        // 计算得到cny对应的amount
        $amount = bcdiv($cny_amount, $currentExchangeRate, 2);

        $orderListings = OrderListing::where('payment_method', $payment_method)
            ->where('status', 1)
            ->where('remain_amount', '>=', $amount)
            ->where('min_sale_amount', '<=', $amount)
            ->orderBy('updated_at', 'asc')
            ->limit(3)
            ->get();

        if ($orderListings->isEmpty()) {
            return ApiResponse::error(ApiCode::ORDER_LISTING_MATCH_FAIL);
        }

        $matchedOrderListing = null;

        // 依次检查 3 个挂单
        foreach ($orderListings as $orderListing) {
            $paymentMethod = UserPaymentMethod::where('status', 1)
                ->where('user_id', $orderListing->user_id)
                ->where('payment_method', $orderListing->payment_method)
                ->first();

            if ($paymentMethod) {
                // 找到符合条件的第一个挂单，立即使用
                $matchedOrderListing = $orderListing;
                break;
            }
        }

        // 若没有找到符合条件的挂单
        if (!$matchedOrderListing) {
            return ApiResponse::error(ApiCode::ORDER_LISTING_MATCH_FAIL);
        }

        // 创建订单
        // 开启事务，确保数据一致性
        DB::beginTransaction();

        try {

            // display order id
            $date = Carbon::now()->format('YmdHis');
            $randomNumber = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT); // 生成 4 位随机数，填充 0
            $display_order_id = "${date}${randomNumber}";

            // 创建订单记录
            $order = new Order([
                'order_listing_id' => $matchedOrderListing->id,
                'display_order_id' => $display_order_id,
                'type' => 'auto',
                'amount' => $amount,
                'payment_method' => $matchedOrderListing->payment_method,
                'buy_user_id' => $autoBuyer->id,
                'buy_account_name' => '',
                'buy_account_number' => '',
                'buy_bank_name' => '',
                'buy_issue_bank_name' => '',
                'sell_user_id' => $orderListing->user_id,
                'sell_account_name' => $paymentMethod->account_name,
                'sell_account_number' => $paymentMethod->account_number,
                'sell_qr_code' => $paymentMethod->qr_code,
                'sell_bank_name' => $paymentMethod->bank_name,
                'sell_issue_bank_name' => $paymentMethod->issue_bank_name,
                'exchange_rate' => $currentExchangeRate,
                'total_price' => $amount,
                'total_cny_price' => $request->cny_amount,
                'status' => 0, // 初始状态为待支付
            ]);
            $order->save();

            // 创建买卖双方的交易记录
            $buyerTransaction = $this->generateTransaction($order, 'order_auto_buy');
            $sellerTransaction = $this->generateTransaction($order, 'order_auto_sell');

            // 记录以便反查
            $order->buy_transaction_id = $buyerTransaction->transaction_id;
            $order->sell_transaction_id = $sellerTransaction->transaction_id;
            $order->save();

            // 更新挂单的剩余库存
            $orderListing->remain_amount = bcsub($orderListing->remain_amount, $amount, 2);
            // 卖完自动下架
            if ($orderListing->remain_amount <= 0) {
                $orderListing->status = 0;
            }
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

    public function autoBuyerConfirm(Request $request)
    {
        $request->validate([
            'order_listing_id' => 'required',
            'auto_buyer_id' => 'required',
            'cny_amount' => 'required|numeric|min:1', // 下单金额必须大于 0
            'account_name' => 'required|string', // 买家账户名
            'account_number' => 'required|string', // 买家账户号码
        ], [
            'order_listing_id.required' => '挂单Id不能为空',
            'auto_buyer_id.required' => '购买人Id不能为空',
            'cny_amount.required' => '购买数量不能为空',
            'cny_amount.min' => '购买数量不能少于1',
            'account_name.required' => '购买人姓名不能为空',
            'account_number.required' => '购买人账户不能为空',
        ]);

        $userId = $request->auto_buyer_id;

        if (!$userId) {
            return ApiResponse::error(ApiCode::USER_AUTO_BUYER_VERIFY_FAIL);
        }

        $autoBuyer = User::where('id', $userId)
            ->where('role', 'autoBuyer')
            ->where('status', 1)
            ->first();

        if (!$autoBuyer) {
            return ApiResponse::error(ApiCode::USER_AUTO_BUYER_VERIFY_FAIL);
        }

        // 获取挂单
        $orderListing = OrderListing::find($request->order_listing_id);
        if (!$orderListing) {
            return ApiResponse::error(ApiCode::ORDER_LISTING_NOT_FOUND);
        }

        // 需要获取当前比率
        $config = PlatformConfig::first();
        if (!$config) {
            return ApiResponse::error(ApiCode::CONFIG_NOT_FOUND);
        }
        
        $currentExchangeRate = 7.25;
        switch ($orderListing->payment_method) {
            case 'alipay':
                $currentExchangeRate = $config->exchange_rate_alipay;
                break;
            case 'wechat':
                $currentExchangeRate = $config->exchange_rate_wechat;
                break;
            case 'bank':
                $currentExchangeRate = $config->exchange_rate_bank;
                break;
            default:
                break;
        }

        // 计算得到cny对应的amount
        $amount = bcdiv($request->cny_amount, $currentExchangeRate, 2);

        // 检查卖家支付信息
        $paymentMethod = UserPaymentMethod::where('status', 1)
            ->where('user_id', $orderListing->user_id)
            ->where('payment_method', $orderListing->payment_method)
            ->first();
        if (!$paymentMethod) {
            return ApiResponse::error(ApiCode::USER_PAYMENT_METHOD_NOT_SET);
        }

        // 检查库存是否足够
        if ($orderListing->remain_amount < $amount) {
            return ApiResponse::error(ApiCode::ORDER_LISTING_AMOUNT_NOT_ENOUGH);
        }

        // 检查是否满足最低购买需求
        if ($orderListing->min_sale_amount > $amount) {
            return ApiResponse::error(ApiCode::ORDER_LISTING_MIN_SALE_AMOUNT_LIMIT);
        }

        // 开启事务，确保数据一致性
        DB::beginTransaction();

        try {

            // display order id
            $date = Carbon::now()->format('YmdHis');
            $randomNumber = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT); // 生成 4 位随机数，填充 0
            $display_order_id = "${date}${randomNumber}";

            // 创建订单记录
            $order = new Order([
                'order_listing_id' => $orderListing->id,
                'display_order_id' => $display_order_id,
                'amount' => $amount,
                'payment_method' => $orderListing->payment_method,
                'buy_user_id' => $userId,
                'buy_account_name' => $request->account_name,
                'buy_account_number' => $request->account_number,
                'buy_bank_name' => $request->bank_name ?? '',
                'buy_issue_bank_name' => $request->issue_bank_name ?? '',
                'sell_user_id' => $orderListing->user_id,
                'sell_account_name' => $paymentMethod->account_name,
                'sell_account_number' => $paymentMethod->account_number,
                'sell_qr_code' => $paymentMethod->qr_code,
                'sell_bank_name' => $paymentMethod->bank_name,
                'sell_issue_bank_name' => $paymentMethod->issue_bank_name,
                'exchange_rate' => $currentExchangeRate,
                'total_price' => $amount,
                'total_cny_price' => $request->cny_amount,
                'status' => 0, // 初始状态为待支付
            ]);
            $order->save();

            // 创建买卖双方的交易记录
            $buyerTransaction = $this->generateTransaction($order, 'buyer');
            $sellerTransaction = $this->generateTransaction($order, 'seller');

            // 记录以便反查
            $order->buy_transaction_id = $buyerTransaction->transaction_id;
            $order->sell_transaction_id = $sellerTransaction->transaction_id;
            $order->save();

            // 更新挂单的剩余库存
            $orderListing->remain_amount = bcsub($orderListing->remain_amount, $amount, 2);
            // 卖完自动下架
            if ($orderListing->remain_amount <= 0) {
                $orderListing->status = 0;
            }
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
        $page_size = $request->input('page_size', 100);  // 每页显示的记录数，默认是10条
        
        // 构建查询
        $query = Order::where('buy_user_id', $userId);

        if ($request->payment_method) {
            $query->where('payment_method', $request->payment_method);
        }

        // 获取符合条件的用户总数
        $totalCount = $query->count();

        // 分页
        $orders = $query->skip(($page - 1) * $page_size)  // 计算分页的偏移量
                    ->take($page_size)  // 每页获取指定数量的用户
                    ->get();

        return ApiResponse::success([
            'total' => $totalCount,  // 总记录数
            'current_page' => $page,  // 当前页
            'page_size' => $page_size,  // 每页记录数
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
        $page_size = $request->input('page_size', 100);  // 每页显示的记录数，默认是10条

        // 构建查询
        $query = Order::where('sell_user_id', $userId);

        if ($request->payment_method) {
            $query->where('payment_method', $request->payment_method);
        }

        // 获取符合条件的用户总数
        $totalCount = $query->count();

        // 分页
        $orders = $query->skip(($page - 1) * $page_size)  // 计算分页的偏移量
                    ->take($page_size)  // 每页获取指定数量的用户
                    ->get();

        return ApiResponse::success([
            'total' => $totalCount,  // 总记录数
            'current_page' => $page,  // 当前页
            'page_size' => $page_size,  // 每页记录数
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
        if (!$order) {
            return ApiResponse::error(ApiCode::ORDER_NOT_FOUND);
        }

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

        // 只有订单状态和用户信息校验通过，才能确认成功
        if ($request->role === 'buyer') {
            if ($userId == $order->buy_user_id && $order->status == 0) {
                // 开启事务，确保数据一致性
                DB::beginTransaction();

                try {
                    $order->status = 1;
                    $order->save();

                    // 更新买家的财务变动记录
                    $buyerTransaction = FinancialRecord::
                        where('transaction_id', $order->buy_transaction_id)
                        ->first();
                    
                    $buyerTransaction->actual_amount = $order->total_price;
                    $buyerTransaction->save();

                    // 提交事务
                    DB::commit();

                } catch (\Exception $e) {
                    \Log::error('An error occurred: ' . $e->getMessage());
                    // 回滚事务
                    DB::rollBack();
                    return ApiResponse::error(ApiCode::ORDER_CONFIRM_FAIL);
                }
            } else {
                return ApiResponse::error(ApiCode::ORDER_CONFIRM_FAIL);
            }
        } else if ($request->role === 'seller') {
            // 商家确认收款后，需要处理财务变动
            if ($userId == $order->sell_user_id && $order->status == 1) {

                $userAccount = UserAccount::where('user_id', $userId)->first();
                if (!$userAccount) {
                    return ApiResponse::error(ApiCode::USER_ACCOUNT_NOT_FOUND);
                }

                // 开启事务，确保数据一致性
                DB::beginTransaction();

                try {
                    $order->status = 2;
                    $order->save();

                    // 更新卖家的总余额（可用余额在挂单时已经冻结，这里不需要处理）
                    $balanceBefore = $userAccount->total_balance;
                    $balanceAfter = bcsub($userAccount->total_balance, $order->total_price, 2);

                    $userAccount->total_balance = $balanceAfter;
                    $userAccount->save();

                    // 更新卖家的财务变动记录
                    $sellerTransaction = FinancialRecord::
                        where('transaction_id', $order->sell_transaction_id)
                        ->first();
                    
                    $sellerTransaction->actual_amount = $order->total_price;
                    $sellerTransaction->balance_before = $balanceBefore;
                    $sellerTransaction->balance_after = $balanceAfter;
                    $sellerTransaction->save();

                    // 提交事务
                    DB::commit();

                } catch (\Exception $e) {
                    \Log::error('An error occurred: ' . $e->getMessage());
                    // 回滚事务
                    DB::rollBack();
                    return ApiResponse::error(ApiCode::ORDER_CONFIRM_FAIL);
                }
            } else {
                return ApiResponse::error(ApiCode::ORDER_CONFIRM_FAIL);
            }
        }

        return ApiResponse::success([
            'order' => $order,
        ]);
    }

    protected function generateTransaction($order, $transactionType) {

        // transaction id
        $today = Carbon::now()->format('Ymd');
        $todayTransactionIncrKey = "transaction:{$today}:sequence";
        $transactionSequence = Redis::incr($todayTransactionIncrKey);

        $formattedSequence = str_pad($transactionSequence, 4, '0', STR_PAD_LEFT); // 生成 3 位随机数，填充 0
        $transaction_id = "${today}_${formattedSequence}";

        if ($transactionType === 'order_buy' || $transactionType === 'order_auto_buy') {
            $userId = $order->buy_user_id;
        } else if ($transactionType === 'order_sell' || $transactionType === 'order_auto_sell') {
            $userId = $order->sell_user_id;
        }

        $newTransaction = FinancialRecord::create([
            'transaction_id' => $transaction_id,
            'user_id' => $userId,
            'amount' => $order->total_price,
            'exchange_rate' => $order->exchange_rate,
            'cny_amount' => $order->total_cny_price,
            'fee' => 0.00,
            'actual_amount' => 0.00,
            'balance_before' => 0.00,
            'balance_after' => 0.00,
            'transaction_type'=> $transactionType,
            'reference_id' => $order->id,
            'display_reference_id' => $order->display_order_id,
        ]);
        return $newTransaction;
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
                    ->whereIn('status', [2, 4]);

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
                    ->whereIn('status', [2, 4]);

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

    public function autoBuyerOrderConfirm(Request $request)
    {
        // 验证输入参数
        $request->validate([
            'order_id' => 'required',
            'auto_buyer_id' => 'required',
            'account_name' => 'required',
            'account_number' => 'required',
        ], [
            'order_id.required' => '订单ID不能为空',
            'auto_buyer_id.required' => '买家ID不能为空',
            'account_name.required' => '账户名不能为空',
            'account_number.required' => '账户号不能为空',
        ]);

        $userId = $request->auto_buyer_id;

        if (!$userId) {
            return ApiResponse::error(ApiCode::USER_AUTO_BUYER_VERIFY_FAIL);
        }

        $autoBuyer = User::where('id', $userId)
            ->where('role', 'autoBuyer')
            ->where('status', 1)
            ->first();

        if (!$autoBuyer) {
            return ApiResponse::error(ApiCode::USER_AUTO_BUYER_VERIFY_FAIL);
        }

        $order = Order::where('id', $request->order_id)->first();
        if (!$order) {
            return ApiResponse::error(ApiCode::ORDER_NOT_FOUND);
        }

        // 只有订单状态和用户信息校验通过，才能确认成功
        if ($userId == $order->buy_user_id && $order->status == 0) {
            // 开启事务，确保数据一致性
            DB::beginTransaction();

            try {
                $order->status = 1;
                $order->buy_account_name = $request->account_name;
                $order->buy_account_number = $request->account_number;
                $order->buy_bank_name = $request->bank_name ?? '';
                $order->save();

                // 更新买家的财务变动记录
                $buyerTransaction = FinancialRecord::
                    where('transaction_id', $order->buy_transaction_id)
                    ->first();
                
                $buyerTransaction->actual_amount = $order->total_price;
                $buyerTransaction->save();

                // 提交事务
                DB::commit();

            } catch (\Exception $e) {
                \Log::error('An error occurred: ' . $e->getMessage());
                // 回滚事务
                DB::rollBack();
                return ApiResponse::error(ApiCode::ORDER_CONFIRM_FAIL);
            }
        } else {
            return ApiResponse::error(ApiCode::ORDER_CONFIRM_FAIL);
        }

        return ApiResponse::success([
            'order' => $order,
        ]);
    }
}
