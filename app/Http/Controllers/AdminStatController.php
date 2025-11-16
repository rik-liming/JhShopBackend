<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Exceptions\ApiException;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use App\Helpers\ApiResponse;
use App\Enums\ApiCode;
use App\Enums\BusinessDef;

use App\Models\Order;
use App\Models\Recharge;

class AdminStatController extends Controller
{
    // 获取 dashboard 的统计数据
    public function getDashboard(Request $request)
    {
        return ApiResponse::success([
            'order_summary' => $this->statOrderSummary(),
            'recharge_summary' => $this->statRechargeSummary(),
        ]);
    }

    /**
     * 统计订单详情
     */
    protected function statOrderSummary() {
        // 定义今天的起止时间
        $startOfDay = Carbon::today();  // 00:00:00
        $endOfDay = Carbon::now();      // 当前时间（可改成 endOfDay() 表示 23:59:59）

        // 查询今天所有订单，并按状态和小时聚合
        $orders = Order::select(
                DB::raw("status"),
                DB::raw("HOUR(created_at) as hour"),
                DB::raw("COUNT(*) as count"),
                DB::raw("SUM(amount) as total_amount")
            )
            ->whereBetween('created_at', [$startOfDay, $endOfDay])
            ->groupBy('status', DB::raw('HOUR(created_at)'))
            ->get();

        // 定义2个状态的初始结构
        $orderSummary = [
            'completed' => [
                'count' => 0,
                'amount' => 0,
                'chart' => array_fill(0, 24, 0),
            ],
            'hanged' => [
                'count' => 0,
                'amount' => 0,
                'chart' => array_fill(0, 24, 0),
            ],
        ];

        $statusMap = [
            BusinessDef::ORDER_STATUS_COMPLETED => 'completed',
            BusinessDef::ORDER_STATUS_ARGUE => 'hanged',
            BusinessDef::ORDER_STATUS_ARGUE_APPROVE => 'completed',
        ];

        // 遍历查询结果，填充 orderSummary 数据
        foreach ($orders as $row) {
            $status = $row->status;

            $formattedStatus = $statusMap[$status] ?? 'unknown';

            // 只统计已知状态（避免其他状态污染）
            if (!isset($orderSummary[$formattedStatus])) continue;

            // 保留两位小数
            $totalAmount = round((float) $row->total_amount, 2);

            $orderSummary[$formattedStatus]['chart'][$row->hour] = $totalAmount;
            $orderSummary[$formattedStatus]['count'] += $row->count;
            $orderSummary[$formattedStatus]['amount'] += (float) $row->total_amount;
        }

        // 最终 amount 也保留两位小数
        foreach ($orderSummary as &$statusData) {
            $statusData['amount'] = round($statusData['amount'], 2);
        }

        return $orderSummary;
    }

    /**
     * 统计充值详情
     */
    protected function statRechargeSummary() {
        // 定义今天的起止时间
        $startOfDay = Carbon::today();  // 00:00:00
        $endOfDay = Carbon::now();      // 当前时间（可改成 endOfDay() 表示 23:59:59）

        // 查询今天所有充值，并按状态和小时聚合
        $recharges = Recharge::select(
                DB::raw("status"),
                DB::raw("HOUR(updated_at) as hour"),
                DB::raw("COUNT(*) as count"),
                DB::raw("SUM(amount) as total_amount")
            )
            ->whereBetween('updated_at', [$startOfDay, $endOfDay])
            ->groupBy('status', DB::raw('HOUR(updated_at)'))
            ->get();

        // 定义1个状态的初始结构
        $rechargeSummary = [
            'completed' => [
                'count' => 0,
                'amount' => 0,
                'chart' => array_fill(0, 24, 0),
            ],
        ];

        $statusMap = [
            BusinessDef::RECHARGE_APPROVE => 'completed',
        ];

        // 遍历查询结果，填充 rechargeSummary 数据
        foreach ($recharges as $row) {
            $status = $row->status;

            $formattedStatus = $statusMap[$status] ?? 'unknown';

            // 只统计已知状态（避免其他状态污染）
            if (!isset($rechargeSummary[$formattedStatus])) continue;

            // 保留两位小数
            $totalAmount = round((float) $row->total_amount, 2);

            $rechargeSummary[$formattedStatus]['chart'][$row->hour] = $totalAmount;
            $rechargeSummary[$formattedStatus]['count'] += $row->count;
            $rechargeSummary[$formattedStatus]['amount'] += (float) $row->total_amount;
        }

        // 最终 amount 也保留两位小数
        foreach ($rechargeSummary as &$statusData) {
            $statusData['amount'] = round($statusData['amount'], 2);
        }

        return $rechargeSummary;
    }
}