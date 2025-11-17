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
        // 验证输入参数
        $request->validate([
            'id' => 'required|integer',
            'status' => 'required|in:' . implode(',', [
                BusinessDef::ORDER_LISTING_STATUS_OFFSELL,
                BusinessDef::ORDER_LISTING_STATUS_ONLINE,
                BusinessDef::ORDER_LISTING_STATUS_FROBIDDEN,
            ]),
        ], [
            'id.required' => '挂单ID不能为空',
            'status.required' => '挂单状态不能为空',
        ]);

        $orderListingId = $request->input('id');
        $newStatus = $request->input('status');

        DB::beginTransaction();

        try {
            // 加悲观锁，避免并发修改
            $orderListing = OrderListing::where('id', $orderListingId)
                ->lockForUpdate()
                ->first();

            if (!$orderListing) {
                throw new \Exception('', ApiCode::ORDER_LISTING_NOT_FOUND);
            }

            // 状态不同时才更新
            if ($orderListing->status !== $newStatus) {
                $orderListing->status = $newStatus;
                $orderListing->save();
            }

            DB::commit();

            return ApiResponse::success([
                'orderListing' => $orderListing
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            return ApiResponse::error($e->getCode());            
        }
    }
}
