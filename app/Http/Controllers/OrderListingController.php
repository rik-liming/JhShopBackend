<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OrderListing;
use Illuminate\Support\Facades\Validator;
use App\Models\UserAccount;
use Illuminate\Support\Facades\DB;
use App\Helpers\ApiResponse;
use App\Enums\ApiCode;

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
            'min_sale_amount' => 'required|numeric|min:0.01',
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

        $newOrderListing = DB::transaction(function() use ($request, $userId) {
            // 创建挂单
            $orderListing = OrderListing::create([
                'user_id' => $userId, // 当前登录用户的ID
                'amount' => $request->input('amount'),
                'min_sale_amount' => $request->input('min_sale_amount'),
                'payment_method' => $request->input('payment_method'),
                'status' => 1, // 默认状态为在售
            ]);

            $userAccount = UserAccount::where('user_id', $userId)->first();
            $userAccount->available_balance -= $request->input('amount');
            $userAccount->save();

            return $orderListing;
        });

        return ApiResponse::success([
            'id' => $newOrderListing->id,
        ]);
    }
}
