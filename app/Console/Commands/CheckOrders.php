<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use App\Models\OrderListing;
use Illuminate\Support\Facades\Log;
use App\Enums\BusinessDef;
use Illuminate\Support\Facades\DB;
use App\Helpers\AdminMessageHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;
use App\Events\AdminBusinessUpdated;
use App\Events\AdminReddotUpdated;

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

        // 分别取消各笔订单，回滚数据
		foreach ($orderIds as $orderId) {
			$this->doArgueOrderLogic($orderId);
		}

        $dealCount = count($orderIds);

        // 写入日志
        \Log::info("[CronCheckOrders], {$dealCount} orders marked as argued at " . now(), ['order_ids' => $orderIds]);
	}

	protected function doCancelOrderLogic($orderId)
    {
        DB::beginTransaction();
        try {

            // 1) 悲观锁锁住订单（防止并发取消）
            $order = Order::where('id', $orderId)
                ->lockForUpdate()
                ->first();

            if (!$order || $order->status !== BusinessDef::ORDER_STATUS_WAIT_BUYER) {
                DB::rollBack();
                return;
            }

            // 2) 悲观锁锁住挂单（防止 remain_amount 被并发修改）
            $orderListing = OrderListing::where('id', $order->order_listing_id)
                ->lockForUpdate()
                ->first();

            if (!$orderListing) {
                DB::rollBack();
                \Log::error("[ExpireOrder] Listing not found, ID: {$order->order_listing_id}");
                return ['success' => false, 'error' => 'Listing not found'];
            }

            // 3) 更新订单状态
            $order->status = BusinessDef::ORDER_STATUS_EXPIRED;
            $order->save();

            // 4) 恢复挂单库存
            $orderListing->remain_amount = bcadd($orderListing->remain_amount, $order->amount, 2);

            if ($orderListing->status == BusinessDef::ORDER_LISTING_STATUS_STOCK_LOCK) {
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

    
    protected function doArgueOrderLogic($orderId)
    {
        DB::beginTransaction();
        try {

            // 加悲观锁避免并发争议处理
            $order = Order::where('id', $orderId)
                ->lockForUpdate()
                ->first();

            if (!$order || $order->status !== BusinessDef::ORDER_STATUS_WAIT_SELLER) {
                DB::rollBack();
                return;
            }

            // 更新订单状态为争议
            $order->status = BusinessDef::ORDER_STATUS_ARGUE;
            $order->save();

            DB::commit(); // 事务到此完成

        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('[ArgueOrder] error occurred: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }

        /**
         * 事务提交后执行以下逻辑
         * 避免锁占用过久，提高吞吐量
         */

        // 生成业务 ID（当天自增）
        $today = Carbon::now()->format('Ymd');
        $todayBusinessIncrKey = "business:{$today}:sequence";
        $businessSequence = Redis::incr($todayBusinessIncrKey);
        $formattedSequence = str_pad($businessSequence, 4, '0', STR_PAD_LEFT);
        $business_id = "{$today}_{$formattedSequence}";

        // 根据订单类型选择业务类型
        $businessType = match ($order->type) {
            BusinessDef::ORDER_TYPE_NORMAL => BusinessDef::ADMIN_BUSINESS_TYPE_ORDER_ARGUE,
            BusinessDef::ORDER_TYPE_AUTO => BusinessDef::ADMIN_BUSINESS_TYPE_AUTO_ORDER_ARGUE,
            default => BusinessDef::ADMIN_BUSINESS_TYPE_ORDER_ARGUE,
        };

        // 推送消息给管理员
        AdminMessageHelper::pushMessage([
            'business_id' => $business_id,
            'business_type' => $businessType,
            'reference_id' => $order->id,
            'title' => '',
            'content' => '',
        ]);

        // 通知管理员业务变动
        event(new AdminBusinessUpdated());
        event(new AdminReddotUpdated());

        return ['success' => true, 'date' => now()];
    }

}
