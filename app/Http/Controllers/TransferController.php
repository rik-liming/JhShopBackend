<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Helpers\ApiResponse;
use App\Enums\ApiCode;
use Carbon\Carbon;
use App\Models\Transfer;

class TransferController extends Controller
{
    /**
     * 创建转账接口
     */
    public function createTransfer(Request $request)
    {
        // 从中间件获取的用户ID
        $userId = $request->user_id_from_token ?? null;

        if (!$userId) {
            return ApiResponse::error(ApiCode::USER_NOT_FOUND);
        }

        $receiverUserId = $request->receiverUserId;
        $amount = $request->amount;

        $date = Carbon::now()->format('YmdHis'); // 获取当前日期和时间，格式：202506021245
        $randomNumber = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT); // 生成 4 位随机数，填充 0
        $transaction_id = $date . $randomNumber;

        $newTransfer = DB::transaction(function() use ($request, $userId, 
            $amount, $receiverUserId, $transaction_id) {

            $transfer = Transfer::create([
                'transaction_id' => $transaction_id,
                'sender_user_id' => $userId, // 当前登录用户的ID
                'sender_user_name' => '',
                'receiver_user_id' => $receiverUserId,
                'receiver_user_name' => '',
                'amount' => $amount,
                'exchange_rate' => 7.26,
                'cny_amount' => 2320,
                'fee' => 2.00,
                'actual_amount' => $amount + 2.00,
                'status' => 0,
            ]);

            FinancialRecord::create([
                'transaction_id' => $transaction_id,
                'user_id' => $userId, // 当前登录用户的ID
                'amount' => $amount,
                'exchange_rate' => 7.26,
                'cny_amount' => 2320,
                'actual_amount' => $amount - 2.00,
                'fee' => 2.00,
                'status' => 0,
                'balance_before' => 0.00,
                'balance_after' => 0.00,
                'transaction_type'=> 'transfer', 
            ]);

            return $transfer;
        });

        return ApiResponse::success([
            'transfer' => $newTransfer,
        ]);
    }

    /**
     * 获取转账详情
     */
    public function getTransferByTranaction(Request $request)
    {
        // 打印 SQL 查询，检查查询是否正确
        $transfer = Transfer::with('transaction')
            ->where('transaction_id', $request->transaction_id)
            ->first();

        if (!$transfer) {
            return ApiResponse::error(ApiCode::TRANSFER_NOT_FOUND);
        }

        return ApiResponse::success([
            'transfer' => $transfer,
        ]);
    }

}
