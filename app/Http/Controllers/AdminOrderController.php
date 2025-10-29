<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Helpers\ApiResponse;
use App\Enums\ApiCode;
use App\Models\Order;

class AdminOrderController extends Controller
{
    public function getOrderByPage(Request $request)
    {
        // 验证输入参数
        $validator = Validator::make($request->all(), [
            'payment_method' => 'required|in:bank,alipay,wechat',
        ]);

        // 获取分页参数
        $page = $request->input('page', 1);  // 当前页，默认是第1页
        $pageSize = $request->input('page_size', 100);  // 每页显示的记录数，默认是10条
        $buy_user_id = $request->input('buy_user_id', '');  // 搜索关键词，默认空字符串
        $sell_user_id = $request->input('sell_user_id', '');  // 搜索关键词，默认空字符串
        $payment_method = $request->input('payment_method', '');  // 搜索关键词，默认空字符串
        $type = $request->input('type', '');  // 搜索关键词，默认空字符串

        // 构建查询
        $query = Order::query();

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
}
