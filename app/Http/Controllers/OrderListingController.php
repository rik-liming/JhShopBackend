<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OrderListing;
use Illuminate\Support\Facades\Validator;
use App\Models\UserAccount;
use Illuminate\Support\Facades\DB;
use App\Helpers\ApiResponse;
use App\Enums\ApiCode;
use App\Models\UserPaymentMethod;

class OrderListingController extends Controller
{
    /**
     * 创建挂单接口
     */
    public function createOrderListing(Request $request)
    {
        // 验证输入参数
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'min_sale_amount' => 'required|numeric|min:0',
            'payment_method' => 'required|in:bank,alipay,wechat',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->errors(),
            ], 400);
        }

        // 从中间件获取的用户ID
        $userId = $request->user_id_from_token ?? null;

        if (!$userId) {
            return ApiResponse::error(ApiCode::USER_NOT_FOUND);
        }

        $userAccount = UserAccount::select(
            'total_balance',
            'available_balance',
        )
        ->where('user_id', $userId)
        ->first();
        
        if (!$userAccount) {
            return ApiResponse::error(ApiCode::USER_NOT_FOUND);
        }

        if ($request->input('amount') > $userAccount->available_balance) {
            return ApiResponse::error(ApiCode::USER_BALANCE_NOT_ENOUGH);
        }

        // 如果未设置收款信息，无法挂单
        $paymentMethod = UserPaymentMethod::where('status', 1)
            ->where('user_id', $userId)
            ->where('payment_method', $request->input('payment_method'))
            ->first();
        if (!$paymentMethod) {
            return ApiResponse::error(ApiCode::USER_PAYMENT_METHOD_NOT_SET);
        }

        $newOrderListing = DB::transaction(function() use ($request, $userId) {
            // 创建挂单
            $orderListing = OrderListing::create([
                'user_id' => $userId,
                'amount' => $request->input('amount'),
                'remain_amount' => $request->input('amount'),
                'min_sale_amount' => $request->input('min_sale_amount'),
                'payment_method' => $request->input('payment_method'),
                'status' => 1, // 默认状态为在售
            ]);

            $userAccount = UserAccount::where('user_id', $userId)->first();
            $userAccount->available_balance = bcsub($userAccount->available_balance, $request->input('amount'), 2);
            $userAccount->save();

            return $orderListing;
        });

        return ApiResponse::success([
            'id' => $newOrderListing->id,
        ]);
    }

    public function getOrderListingByPage(Request $request)
    {
        // 验证输入参数
        $validator = Validator::make($request->all(), [
            'payment_method' => 'required|in:bank,alipay,wechat',
        ]);

        // 获取分页参数
        $page = $request->input('page', 1);  // 当前页，默认是第1页
        $pageSize = $request->input('page_size', 100);  // 每页显示的记录数，默认是10条
        $payment_method = $request->input('payment_method', '');  // 搜索关键词，默认空字符串

        // 构建查询
        $query = OrderListing::where('status', 1)
                     ->where('payment_method', $payment_method);

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
     * 获取当前挂单信息
     */
    public function getOrderListing(Request $request)
    {
        $orderListing = OrderListing::find($request->id);

        if (!$orderListing) {
            return ApiResponse::error(ApiCode::ORDER_LISTING_NOT_FOUND);
        }

        return ApiResponse::success([
            'orderListing' => $orderListing,
        ]);
    }
}
