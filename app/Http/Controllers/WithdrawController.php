<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Helpers\ApiResponse;
use App\Enums\ApiCode;
use Carbon\Carbon;
use App\Models\Withdraw;
use App\Models\FinancialRecord;

class WithdrawController extends Controller
{
    /**
     * 创建提现接口
     */
    public function createWithdraw(Request $request)
    {
        // 从中间件获取的用户ID
        $userId = $request->user_id_from_token ?? null;

        if (!$userId) {
            return ApiResponse::error(ApiCode::USER_NOT_FOUND);
        }

        $date = Carbon::now()->format('YmdHis'); // 获取当前日期和时间，格式：202506021245
        $randomNumber = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT); // 生成 4 位随机数，填充 0
        $transaction_id = $date . $randomNumber;

        $newWithdraw = DB::transaction(function() use ($request, $userId, 
            $amount, $transaction_id) {

            $withdraw = Withdraw::create([
                'transaction_id' => $transaction_id,
                'user_id' => $userId, // 当前登录用户的ID
                'user_name' => '',
                'amount' => $amount,
                'actual_amount' => $amount - 2.00,
                'fee' => 2.00,
                'withdraw_address' => 'afdsfxerwrwwe',
                'status' => 0,
            ]);

            FinancialRecord::create([
                'transaction_id' => $transaction_id,
                'user_id' => $userId, // 当前登录用户的ID
                'amount' => $amount,
                'actual_amount' => $amount - 2.00,
                'fee' => 2.00,
                'status' => 0,
                'balance_before' => 0.00,
                'balance_after' => 0.00,
                'transaction_type'=> 'withdraw', 
                'order_id' => '',
                'payment_method' => '',
                'description' => '',
            ]);

            return $withdraw;
        });

        return ApiResponse::success([
            'id' => $newWithdraw->id,
        ]);
    }

    /**
     * 获取提现详情
     */
    public function getWithdrawByTranaction(Request $request)
    {
        $withdraw = Withdraw::where('transaction_id', $request->transaction_id)
            ->first();

        if (!$withdraw) {
            return ApiResponse::error(ApiCode::WITHDRAW_NOT_FOUND);
        }

        return ApiResponse::success([
            'withdraw' => $withdraw,
        ]);
    }
}
