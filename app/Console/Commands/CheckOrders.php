<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

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
	
	protected handleCancelOrders() {
		// 计算时间阈值
        $threshold = now()->subMinutes(20);

        // 查询符合条件的订单ID
        $orderIds = Order::where('status', 0)
            ->where('created_at', '<', $threshold)
            ->pluck('id')
            ->toArray();

        if (empty($orderIds)) {
            return;
		}

		// 分别取消各笔订单，回滚数据
		foreach ($orderIds as $orderId) {
			$this->doCancelOrderLogic($orderIds);
		}

        // 写入日志
        \Log::info("[CronCheckOrders], {$count} orders marked as expired at " . now(), ['order_ids' => $orderIds]);
	}

	protected handleArgueOrders() {
		// 计算时间阈值
        $threshold = now()->subMinutes(20);

        // 查询符合条件的订单ID
        $orderIds = Order::where('status', 1)
            ->where('created_at', '<', $threshold)
            ->pluck('id')
            ->toArray();

        if (empty($orderIds)) {
            return;
        }

        // 批量更新状态为 4（争议）
        $count = Order::whereIn('id', $orderIds)->update(['status' => 4]);

        // 写入日志
        \Log::info("[CronCheckOrders], {$count} orders marked as argued at " . now(), ['order_ids' => $orderIds]);
	}

	protected doCancelOrderLogic($orderId) {
		DB::beginTransaction();
        try {
			$order = Order::where('id', $orderIds)->first();
			if ($order->status !== 0) {
				return;
			}

			// 变更订单状态
			$order->status = 3;
			$order->save();

			// 恢复挂单状态，todo

            DB::commit();
            return ['success' => true, 'date' => $date];
        } catch (\Throwable $e) {
            DB::rollBack();
            // report($e);
            return ['success' => false, 'error' => $e->getMessage()];
        }
	} 
}
