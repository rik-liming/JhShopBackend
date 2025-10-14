<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Helpers\ApiResponse;
use App\Enums\ApiCode;
use Carbon\Carbon;
use App\Models\Recharge;
use App\Models\FinancialRecord;

class RechargeController extends Controller
{
    /**
     * 创建充值接口
     */
    public function createRecharge(Request $request)
    {
        // 从中间件获取的用户ID
        $userId = $request->user_id_from_token ?? null;

        if (!$userId) {
            return ApiResponse::error(ApiCode::USER_NOT_FOUND);
        }

        $date = Carbon::now()->format('YmdHis'); // 获取当前日期和时间，格式：202506021245
        $randomNumber = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT); // 生成 4 位随机数，填充 0
        $transaction_id = $date . $randomNumber;

        $amount = $request->amount;

        $newRecharge = DB::transaction(function() use ($request, $userId, 
            $amount, $transaction_id) {

            $recharge = Recharge::create([
                'transaction_id' => $transaction_id,
                'user_id' => $userId, // 当前登录用户的ID
                'user_name' => '',
                'amount' => $amount,
                'recharge_address' => 'testabdfsfssfs',
                'recharge_images' => "", // text 类型字段，传递空字符串
                'status' => 0,
            ]);

            FinancialRecord::create([
                'transaction_id' => $transaction_id,
                'user_id' => $userId, // 当前登录用户的ID
                'amount' => $amount,
                'actual_amount' => $amount,
                'fee' => 0.00,
                'balance_before' => 0.00,
                'balance_after' => 0.00,
                'transaction_type'=> 'recharge', 
                'description' => "",
            ]);

            return $recharge;
        });

        return ApiResponse::success([
            'recharge' => $newRecharge,
        ]);
    }

    /**
     * 获取充值详情
     */
    public function getRechargeByTranaction(Request $request)
    {
        $recharge = Recharge::where('transaction_id', $request->transaction_id)
            ->first();

        if (!$recharge) {
            return ApiResponse::error(ApiCode::RECHARGE_NOT_FOUND);
        }

        return ApiResponse::success([
            'recharge' => $recharge,
        ]);
    }
}
