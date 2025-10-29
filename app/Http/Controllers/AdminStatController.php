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

use App\Models\Order;

class AdminStatController extends Controller
{
    // 获取 dashboard 的统计数据
    public function getDashboard(Request $request)
    {
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

        // 定义三个状态的初始结构
        $summary = [
            'completed' => [
                'count' => 0,
                'amount' => 0,
                'chart' => array_fill(0, 24, 0),
            ],
            'ongoing' => [
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
            0 => 'ongoing',
            1 => 'ongoing',
            2 => 'completed',
            3 => 'hanged',
            4 => 'completed',
        ];

        // 遍历查询结果，填充 summary 数据
        foreach ($orders as $row) {
            $status = $row->status;

            $formattedStatus = $statusMap[$status] ?? 'unknown';

            // 只统计已知状态（避免其他状态污染）
            if (!isset($summary[$formattedStatus])) continue;

            // 保留两位小数
            $totalAmount = round((float) $row->total_amount, 2);

            $summary[$formattedStatus]['chart'][$row->hour] = $totalAmount;
            $summary[$formattedStatus]['count'] += $row->count;
            $summary[$formattedStatus]['amount'] += (float) $row->total_amount;
        }

        // 最终 amount 也保留两位小数
        foreach ($summary as &$statusData) {
            $statusData['amount'] = round($statusData['amount'], 2);
        }

        return ApiResponse::success([
            'summary' => $summary
        ]);
    }
}