<?php

namespace App\Helpers;

use App\Models\Order;
use App\Models\User;
use App\Models\DailyReport;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Enums\BusinessDef;

class ReportHelper
{
    /**
     * 生成日报
     * @param string|null $date 手动指定日期（默认昨天）
     */
    public static function generateDailyReport(?string $date = null)
    {
        $date = $date ?: Carbon::yesterday()->toDateString();
        $start = Carbon::parse($date)->startOfDay();
        $end = Carbon::parse($date)->endOfDay();

        DB::beginTransaction();
        try {
            /* -------------------------------
             * 系统买家报表（buyer）
             * ------------------------------- */
            $buyerReports = Order::from('jh_user_order as orders')
                ->select(
                    'orders.buy_user_id as user_id',
                    DB::raw('COUNT(*) as order_count'),
                    DB::raw('SUM(orders.amount) as total_amount')
                )
                ->join('jh_user as u', 'orders.buy_user_id', '=', 'u.id')
                ->whereBetween('orders.created_at', [$start, $end])
                ->whereIn('orders.status', [
                    BusinessDef::ORDER_STATUS_COMPLETED,
                    BusinessDef::ORDER_STATUS_ARGUE_APPROVE,
                ]) // 成功状态
                ->where('u.role', 'buyer')
                ->groupBy('orders.buy_user_id')
                ->get();

            foreach ($buyerReports as $r) {
                DailyReport::updateOrCreate(
                    ['report_date' => $date, 'user_id' => $r->user_id, 'type' => 'buyer'],
                    ['order_count' => $r->order_count, 'total_amount' => $r->total_amount]
                );
			}
			
			/* -------------------------------
             * 自动化买家报表（buyer）
             * ------------------------------- */
            $buyerReports = Order::from('jh_user_order as orders')
                ->select(
                    'orders.buy_user_id as user_id',
                    DB::raw('COUNT(*) as order_count'),
                    DB::raw('SUM(orders.amount) as total_amount')
                )
                ->join('jh_user as u', 'orders.buy_user_id', '=', 'u.id')
                ->whereBetween('orders.created_at', [$start, $end])
                ->whereIn('orders.status', [
                    BusinessDef::ORDER_STATUS_COMPLETED,
                    BusinessDef::ORDER_STATUS_ARGUE_APPROVE,
                ]) // 成功状态
                ->where('u.role', 'autoBuyer')
                ->groupBy('orders.buy_user_id')
                ->get();

            foreach ($buyerReports as $r) {
                DailyReport::updateOrCreate(
                    ['report_date' => $date, 'user_id' => $r->user_id, 'type' => 'autoBuyer'],
                    ['order_count' => $r->order_count, 'total_amount' => $r->total_amount]
                );
            }

            /* -------------------------------
             * 代理报表（agent）统计其团队卖家
             * ------------------------------- */
            $agentReports = Order::from('jh_user_order as orders')
                ->select(
                    'u.root_agent_id as agent_id',
                    DB::raw('COUNT(*) as order_count'),
                    DB::raw('SUM(orders.amount) as total_amount')
                )
                ->join('jh_user as u', 'orders.sell_user_id', '=', 'u.id')
                ->whereBetween('orders.created_at', [$start, $end])
                ->whereIn('orders.status', [
                    BusinessDef::ORDER_STATUS_COMPLETED,
                    BusinessDef::ORDER_STATUS_ARGUE_APPROVE,
                ])
                ->whereIn('u.role', ['seller', 'agent']) // 只统计卖家订单
                ->groupBy('u.root_agent_id')
                ->get();

            foreach ($agentReports as $r) {
                DailyReport::updateOrCreate(
                    ['report_date' => $date, 'user_id' => $r->agent_id, 'type' => 'agent'],
                    ['order_count' => $r->order_count, 'total_amount' => $r->total_amount]
                );
            }

            DB::commit();
            return ['success' => true, 'date' => $date];
        } catch (\Throwable $e) {
            DB::rollBack();
            // report($e);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
