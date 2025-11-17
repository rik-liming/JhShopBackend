<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Helpers\ApiResponse;
use App\Enums\ApiCode;
use App\Models\Order;
use App\Models\OrderListing;
use App\Models\FinancialRecord;
use App\Models\UserAccount;
use App\Enums\BusinessDef;
use App\Helpers\MessageHelper;
use App\Events\TransactionUpdated;

class AdminOrderController extends Controller
{
    public function getOrderByPage(Request $request)
    {
        // 获取分页参数
        $page = $request->input('page', 1);  // 当前页，默认是第1页
        $pageSize = $request->input('page_size', 100);  // 每页显示的记录数，默认是10条
        $buy_user_id = $request->input('buy_user_id', '');  // 搜索关键词，默认空字符串
        $sell_user_id = $request->input('sell_user_id', '');  // 搜索关键词，默认空字符串
        $payment_method = $request->input('payment_method', '');  // 搜索关键词，默认空字符串
        $type = $request->input('type', '');  // 搜索关键词，默认空字符串
        $display_order_id = $request->input('display_order_id', '');  // 搜索关键词，默认空字符串

        // 构建查询
        $query = Order::with('buyer')->with('seller')
            ->orderBy('id', 'desc');

        if ($buy_user_id) {
            $query->where('buy_user_id', $buy_user_id);
        }

        if ($sell_user_id) {
            $query->where('sell_user_id', $sell_user_id);
        }

        if ($payment_method) {
            $query->where('payment_method', $payment_method);
        }

        if ($type) {
            $query->where('type', $type);
        }

        if ($display_order_id) {
            $query->where('display_order_id', $display_order_id);
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
     * 争议订单处理
     */
    public function orderJudge(Request $request)
    {
        // 验证输入参数
        $request->validate([
            'orderId' => 'required',
            'status' => 'required|in:' . implode(',', [
                BusinessDef::ORDER_STATUS_ARGUE_APPROVE,
                BusinessDef::ORDER_STATUS_ARGUE_REJECT,
            ]),
        ], [
            'orderId.required' => '订单ID不能为空',
            'status.required' => '订单状态不能为空'
        ]);

        // 使用悲观锁读取订单，防止重复处理
        $order = Order::where('id', $request->orderId)->lockForUpdate()->first();
        if (!$order) {
            return ApiResponse::error(ApiCode::ORDER_NOT_FOUND);
        }

        if ($order->status !== BusinessDef::ORDER_STATUS_ARGUE) {
            return ApiResponse::error(ApiCode::OPERATION_FAIL);
        }

        // ***********************
        // 驳回争议处理
        // ***********************
        if ($request->status === BusinessDef::ORDER_STATUS_ARGUE_REJECT) {

            DB::beginTransaction();
            try {
                // 锁订单
                $order = Order::where('id', $request->orderId)->lockForUpdate()->first();

                // 修改订单状态
                $order->status = BusinessDef::ORDER_STATUS_ARGUE_REJECT;
                $order->save();

                // 锁财务记录
                $buyerTransaction = FinancialRecord::where('transaction_id', $order->buy_transaction_id)
                    ->lockForUpdate()->first();
                $sellerTransaction = FinancialRecord::where('transaction_id', $order->sell_transaction_id)
                    ->lockForUpdate()->first();

                if (!$buyerTransaction || !$sellerTransaction) {
                    DB::rollBack();
                    return ApiResponse::error(ApiCode::TRANSACTION_NOT_FOUND);
                }

                // 买家财务记录
                $buyerTransaction->actual_amount = 0.00;
                $buyerTransaction->status = BusinessDef::TRANSACTION_COMPLETED;
                $buyerTransaction->save();

                // 卖家财务记录
                $sellerTransaction->actual_amount = 0.00;
                $sellerTransaction->status = BusinessDef::TRANSACTION_COMPLETED;
                $sellerTransaction->save();

                // 恢复挂单，锁订单挂单
                $orderListing = OrderListing::where('id', $order->order_listing_id)
                    ->lockForUpdate()->first();

                if (!$orderListing) {
                    DB::rollBack();
                    return ApiResponse::error(ApiCode::ORDER_LISTING_NOT_FOUND);
                }

                $orderListing->remain_amount = bcadd($orderListing->remain_amount, $order->amount, 2);
                if ($orderListing->status == BusinessDef::ORDER_LISTING_STATUS_STOCK_LOCK) {
                    $orderListing->status = BusinessDef::ORDER_LISTING_STATUS_ONLINE;
                }
                $orderListing->save();

                DB::commit();

                // 推送通知（事务外）
                MessageHelper::pushMessage($order->buy_user_id, [
                    'transaction_id' => $buyerTransaction->transaction_id,
                    'transaction_type' => $buyerTransaction->transaction_type,
                    'reference_id' => $order->id,
                ]);

                MessageHelper::pushMessage($order->sell_user_id, [
                    'transaction_id' => $sellerTransaction->transaction_id,
                    'transaction_type' => $sellerTransaction->transaction_type,
                    'reference_id' => $order->id,
                ]);

                // 事件
                event(new TransactionUpdated(
                    $order->buy_user_id,
                    $buyerTransaction->transaction_id,
                    $buyerTransaction->transaction_type,
                    $buyerTransaction->reference_id
                ));

                event(new TransactionUpdated(
                    $order->sell_user_id,
                    $sellerTransaction->transaction_id,
                    $sellerTransaction->transaction_type,
                    $sellerTransaction->reference_id
                ));

            } catch (\Exception $e) {
                \Log::error('[OrderJudge Reject] error: ' . $e->getMessage());
                DB::rollBack();
                return ApiResponse::error(ApiCode::OPERATION_FAIL);
            }

        }

        // ***********************
        // 争议通过处理
        // ***********************
        else if ($request->status === BusinessDef::ORDER_STATUS_ARGUE_APPROVE) {

            DB::beginTransaction();
            try {
                // 锁订单
                $order = Order::where('id', $request->orderId)->lockForUpdate()->first();

                // 锁卖家账户
                $sellerAccount = UserAccount::where('user_id', $order->sell_user_id)
                    ->lockForUpdate()->first();

                if (!$sellerAccount) {
                    DB::rollBack();
                    return ApiResponse::error(ApiCode::USER_ACCOUNT_NOT_FOUND);
                }

                // 修改订单状态
                $order->status = BusinessDef::ORDER_STATUS_ARGUE_APPROVE;
                $order->save();

                // 修改卖家余额
                $balanceBefore = $sellerAccount->total_balance;
                $balanceAfter = bcsub($sellerAccount->total_balance, $order->total_price, 2);

                $sellerAccount->total_balance = $balanceAfter;
                $sellerAccount->save();

                // 锁定财务记录
                $buyerTransaction = FinancialRecord::where('transaction_id', $order->buy_transaction_id)
                    ->lockForUpdate()->first();
                $sellerTransaction = FinancialRecord::where('transaction_id', $order->sell_transaction_id)
                    ->lockForUpdate()->first();

                if (!$buyerTransaction || !$sellerTransaction) {
                    DB::rollBack();
                    return ApiResponse::error(ApiCode::TRANSACTION_NOT_FOUND);
                }

                // 买家财务记录
                $buyerTransaction->actual_amount = $order->total_price;
                $buyerTransaction->status = BusinessDef::TRANSACTION_COMPLETED;
                $buyerTransaction->save();

                // 卖家财务记录
                $sellerTransaction->actual_amount = -$order->total_price;
                $sellerTransaction->balance_before = $balanceBefore;
                $sellerTransaction->balance_after = $balanceAfter;
                $sellerTransaction->status = BusinessDef::TRANSACTION_COMPLETED;
                $sellerTransaction->save();

                DB::commit();

                // 推送通知
                MessageHelper::pushMessage($order->buy_user_id, [
                    'transaction_id' => $buyerTransaction->transaction_id,
                    'transaction_type' => $buyerTransaction->transaction_type,
                    'reference_id' => $order->id,
                ]);

                MessageHelper::pushMessage($order->sell_user_id, [
                    'transaction_id' => $sellerTransaction->transaction_id,
                    'transaction_type' => $sellerTransaction->transaction_type,
                    'reference_id' => $order->id,
                ]);

                event(new TransactionUpdated(
                    $order->buy_user_id,
                    $buyerTransaction->transaction_id,
                    $buyerTransaction->transaction_type,
                    $buyerTransaction->reference_id
                ));

                event(new TransactionUpdated(
                    $order->sell_user_id,
                    $sellerTransaction->transaction_id,
                    $sellerTransaction->transaction_type,
                    $sellerTransaction->reference_id
                ));

            } catch (\Exception $e) {
                \Log::error('[OrderJudge Approve] error: ' . $e->getMessage());
                DB::rollBack();
                return ApiResponse::error(ApiCode::ORDER_CONFIRM_FAIL);
            }
        }

        return ApiResponse::success([]);
    }
}
