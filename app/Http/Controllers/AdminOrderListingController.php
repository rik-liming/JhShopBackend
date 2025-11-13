<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OrderListing;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Helpers\ApiResponse;
use App\Enums\ApiCode;
use App\Enums\BusinessDef;

class AdminOrderListingController extends Controller
{
    public function getOrderListingByPage(Request $request)
    {
        // 验证输入参数
        $request->validate([
            'payment_method' => 'required|in:' . implode(',', [
                BusinessDef::PAYMENT_METHOD_ALIPAY,
                BusinessDef::PAYMENT_METHOD_WECHAT,
                BusinessDef::PAYMENT_METHOD_BANK,
            ]),
        ], [
            'payment_method.required' => '卖场不能为空'
        ]);

        // 获取分页参数
        $page = $request->input('page', 1);  // 当前页，默认是第1页
        $pageSize = $request->input('page_size', 100);  // 每页显示的记录数，默认是10条
        $user_id = $request->input('user_id', '');  // 搜索关键词，默认空字符串
        $payment_method = $request->input('payment_method', '');  // 搜索关键词，默认空字符串

        // 构建查询
        $query = OrderListing::with('user')->orderBy('id', 'desc');

        if ($user_id) {
            $query->where('user_id', $user_id);
        }

        if ($payment_method) {
            $query->where('payment_method', $payment_method);
        }

        // 获取符合条件的用户总数
        $totalCount = $query->count();

        // 分页
        $orderListings = $query->skip(($page - 1) * $pageSize)  // 计算分页的偏移量
                    ->take($pageSize)  // 每页获取指定数量的用户
                    ->get();

        return ApiResponse::success([
            'total' => $totalCount,  // 总记录数
            'current_page' => $page,  // 当前页
            'page_size' => $pageSize,  // 每页记录数
            'orderListings' => $orderListings,  // 当前页的挂单列表
        ]);
    }

    /**
     * 更新挂单信息
     */
    public function updateOrderListing(Request $request)
    {
        // 获取传入的更新参数
        $status = $request->input('status', null);  // 状态

        // 查找指定ID的挂单
        $orderListing = OrderListing::find($request->id);

        if (!$orderListing) {
            return ApiResponse::error(ApiCode::ORDER_LISTING_NOT_FOUND);
        }

        // 更新信息
        if ($orderListing->status !== $status) {
            $orderListing->status = $status;
        }

        // 保存更新
        $orderListing->save();

        return ApiResponse::success([
            'orderListing' => $orderListing
        ]);
    }
}
