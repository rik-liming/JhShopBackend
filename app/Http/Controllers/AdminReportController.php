<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Exceptions\ApiException;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use App\Helpers\ApiResponse;
use App\Enums\ApiCode;

use App\Models\DailyReport;
use App\Helpers\ReportHelper;

class AdminReportController extends Controller
{
    public function getReportByTime(Request $request)
    {
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

        $type = $request->input('type', '');  // 搜索关键词，默认空字符串

        // 构建查询
        $query = DailyReport::query()->orderBy('id', 'desc');

        // 如果传入了时间范围，则加入时间条件
        if ($startDate) {
            $query->where('report_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('report_date', '<=', $endDate);
        }

        if ($type) {
            $query->where('type', $type);
        }

        // 查询数据
        $reports = $query->get();

        // 计算汇总
        $totalCount = $reports->sum('order_count');
        $totalAmount = $reports->sum('total_amount');

        return ApiResponse::success([
            'reports' => $reports,
            'totalCount' => $totalCount,
            'totalAmount' => $totalAmount,
        ]);
    }

    public function generateTodayReport(Request $request)
    {
        $result = ReportHelper::generateDailyReport(Carbon::today()->toDateString());

        if ($result['success']) {
            return ApiResponse::success([]);
        } else {
            return ApiResponse::error(ApiCode::OPERATION_FAIL);
        }
    }
}