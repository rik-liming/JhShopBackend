<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use App\Models\OrderListing;
use Illuminate\Support\Facades\Log;
use App\Enums\BusinessDef;
use Illuminate\Support\Facades\DB;
use App\Helpers\AdminMessageHelper;
use App\Events\BusinessUpdated;
use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;

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
    
    protected function doArgueOrderLogic($orderId) {
		DB::beginTransaction();
        try {
			$order = Order::where('id', $orderId)->first();
			if ($order->status !== BusinessDef::ORDER_STATUS_WAIT_SELLER) {
				return;
			}

			// 变更订单状态
			$order->status = BusinessDef::ORDER_STATUS_ARGUE;
			$order->save();

            DB::commit();

            // 存在争议订单，推送消息给后台管理员
            // business id
            $today = Carbon::now()->format('Ymd');
            $todayBusinessIncrKey = "business:{$today}:sequence";
            $businessSequence = Redis::incr($todayBusinessIncrKey);

            $formattedSequence = str_pad($businessSequence, 4, '0', STR_PAD_LEFT); // 生成 3 位随机数，填充 0
            $business_id = "${today}_${formattedSequence}";

            $businessType = '';
            switch ($order->type) {
                case BusinessDef::ORDER_TYPE_NORMAL:
                    $businessType = BusinessDef::ADMIN_BUSINESS_TYPE_ORDER_ARGUE;
                    break;
                case BusinessDef::ORDER_TYPE_AUTO:
                    $businessType = BusinessDef::ADMIN_BUSINESS_TYPE_AUTO_ORDER_ARGUE;
                    break;
            }

            AdminMessageHelper::pushMessage([
                'business_id' => $business_id,
                'business_type' => $businessType,
                'reference_id' => $order->id,
                'title' => '',
                'content' => '',
            ]);

            // 通知管理员业务变动
            event(new BusinessUpdated());

            return ['success' => true, 'date' => now()];
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('[ExpireOrder] error occurred: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
