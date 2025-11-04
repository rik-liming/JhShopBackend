<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use App\Models\OrderListing;
use Illuminate\Support\Facades\Log;
use App\Enums\BusinessDef;
use Illuminate\Support\Facades\DB;

class CheckOrders extends Command
{
    /**
     * 命令签名
     */
    protected $signature = 'orders:check';

    /**
     * 命令描述
     */
    protected $description = 'Check and update expired orders (created more than 20 minutes ago)';

    /**
     * 执行命令
     */
    public function handle()
    {
		$this->handleCancelOrders();
		$this->handleArgueOrders();
	}
	
	protected function handleCancelOrders() {
		// 计算时间阈值
        $threshold = now()->subMinutes(10);

        // 查询符合条件的订单ID
        $orderIds = Order::where('status', BusinessDef::ORDER_STATUS_WAIT_BUYER)
            ->where('created_at', '<', $threshold)
            ->pluck('id')
            ->toArray();

        if (empty($orderIds)) {
            return;
		}

		// 分别取消各笔订单，回滚数据
		foreach ($orderIds as $orderId) {
			$this->doCancelOrderLogic($orderId);
		}

        $dealCount = count($orderIds);
        // 写入日志
        \Log::info("[CronCheckOrders], {$dealCount} orders marked as expired at " . now(), ['order_ids' => $orderIds]);
	}

	protected function handleArgueOrders() {
		// 计算时间阈值
        $threshold = now()->subMinutes(10);

        // 查询符合条件的订单ID
        $orderIds = Order::where('status', BusinessDef::ORDER_STATUS_WAIT_SELLER)
            ->where('created_at', '<', $threshold)
            ->pluck('id')
            ->toArray();

        if (empty($orderIds)) {
            return;
        }

        // 批量更新状态为 4（争议）
        $count = Order::whereIn('id', $orderIds)->update(['status' => BusinessDef::ORDER_STATUS_ARGUE]);

        // 写入日志
        \Log::info("[CronCheckOrders], {$count} orders marked as argued at " . now(), ['order_ids' => $orderIds]);
	}

	protected function doCancelOrderLogic($orderId) {
		DB::beginTransaction();
        try {
			$order = Order::where('id', $orderId)->first();
			if ($order->status !== BusinessDef::ORDER_STATUS_WAIT_BUYER) {
				return;
			}

			// 变更订单状态
			$order->status = BusinessDef::ORDER_STATUS_EXPIRED;
			$order->save();

            // 恢复挂单状态
            $orderListing = OrderListing::where('id', $order->order_listing_id)
            ->first();

            $orderListing->remain_amount = bcadd($orderListing->remain_amount, $order->amount, 2);
            if ($orderListing->status == BusinessDef::ORDER_LISTING_STATUS_STOCK_LOCK) { // 如果是因为库存冻结下架，需要恢复上架
                $orderListing->status = BusinessDef::ORDER_LISTING_STATUS_ONLINE;
            }
            $orderListing->save();

            DB::commit();
            return ['success' => true, 'date' => now()];
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('[ExpireOrder] error occurred: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
	} 
}
