<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Helpers\ApiResponse;
use App\Enums\ApiCode;
use App\Models\FinancialRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class FinancialRecordController extends Controller
{
    public function getMyRecords(Request $request)
    {
        // 从中间件获取的用户ID
        $userId = $request->user_id_from_token ?? null;

        if (!$userId) {
            return ApiResponse::error(ApiCode::USER_NOT_FOUND);
        }

        $page = $request->input('page', 1);  // 当前页，默认是第1页
        $pageSize = $request->input('pagesize', 100);  // 每页显示的记录数，默认是10条
        
        // 构建查询
        $query = FinancialRecord::where('user_id', $userId)->orderBy('id', 'desc');

        // 获取符合条件的用户总数
        $totalCount = $query->count();

        // 分页
        $records = $query->skip(($page - 1) * $pageSize)  // 计算分页的偏移量
                    ->take($pageSize)  // 每页获取指定数量的用户
                    ->get();

        return ApiResponse::success([
            'total' => $totalCount,  // 总记录数
            'current_page' => $page,  // 当前页
            'page_size' => $pageSize,  // 每页记录数
            'records' => $records,  // 当前页的挂单列表
        ]);
    }
}
