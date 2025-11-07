<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Helpers\ApiResponse;
use App\Enums\ApiCode;
use Carbon\Carbon;
use App\Models\Transfer;
use App\Models\UserAccount;
use App\Models\User;
use App\Models\FinancialRecord;
use App\Models\PlatformConfig;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Hash;
use App\Helpers\AdminMessageHelper;
use App\Enums\BusinessDef;
use App\Events\BusinessUpdated;

class TransferController extends Controller
{
    /**
     * 创建转账接口
     */
    public function createTransfer(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'receiver_user_id' => 'required',
            'payment_password' => 'required',
        ], [
            'amount.required' => '转账金额不能为空',
            'amount.min' => '转账金额至少0.01',
            'receiver_user_id.required' => '接收转账信息不能为空',
            'payment_password.required' => '支付密码不能为空',
        ]);

        // 从中间件获取的用户ID
        $userId = $request->user_id_from_token ?? null;
        $receiver_user_id = $request->receiver_user_id;
        $amount = $request->amount;
        $payment_password = $request->payment_password;

        if (!$userId) {
            return ApiResponse::error(ApiCode::USER_NOT_FOUND);
        }

        $user = User::where('id', $userId)->first();
        if (!$user) {
            return ApiResponse::error(ApiCode::USER_NOT_FOUND);
        }

        $receiverUser = User::where('id', $receiver_user_id)->first();
        if (!$receiverUser) {
            return ApiResponse::error(ApiCode::USER_NOT_FOUND);
        }

        if ($receiverUser->role !== BusinessDef::USER_ROLE_SELLER
            && $receiverUser->role !== BusinessDef::USER_ROLE_AGENT) {
            return ApiResponse::error(ApiCode::TRANSFER_USER_ILLEGAL_ROLE);
        }

        $config = PlatformConfig::first();
        if (!$config) {
            return ApiResponse::error(ApiCode::CONFIG_NOT_FOUND);
        }

        $userAccount = UserAccount::where('user_id', $userId)->first();
        if (!$userAccount) {
            return ApiResponse::error(ApiCode::USER_ACCOUNT_NOT_FOUND);
        }

        $receiverUserAccount = UserAccount::where('user_id', $receiver_user_id)->first();
        if (!$receiverUserAccount) {
            return ApiResponse::error(ApiCode::USER_ACCOUNT_NOT_FOUND);
        }

        if (!$userAccount->payment_password) {
            return ApiResponse::error(ApiCode::USER_PAYMENT_PASSWORD_NOT_SET);
        }

        if (!Hash::check($payment_password, $userAccount->payment_password)) {
            return ApiResponse::error(ApiCode::USER_PAYMENT_PASSWORD_WRONG);
        }

        $totalExpense = bcadd($amount, $config->transfer_fee, 2);
        if (bccomp($totalExpense, $userAccount->available_balance, 2) > 0) {
            return ApiResponse::error(ApiCode::USER_BALANCE_NOT_ENOUGH);
        }

        // transaction id
        $today = Carbon::now()->format('Ymd');
        $todayTransactionIncrKey = "transaction:{$today}:sequence";
        $transactionSequence = Redis::incr($todayTransactionIncrKey);

        $formattedSequence = str_pad($transactionSequence, 4, '0', STR_PAD_LEFT); // 生成 3 位随机数，填充 0
        $transaction_id = "${today}_${formattedSequence}";

        // display transfer id
        $date = Carbon::now()->format('YmdHis');
        $randomNumber = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT); // 生成 4 位随机数，填充 0
        $display_transfer_id = "${date}${randomNumber}";

        // 开启事务，确保数据一致性
        DB::beginTransaction();

        try {
            $cnyAmount = bcmul($amount, $config->exchange_rate_platform, 2);
            $cnyAmount = ceil($cnyAmount * 100) / 100;

            // 提交申请的同时，需要冻结金额
            $userAccount->available_balance = bcsub($userAccount->available_balance, $totalExpense, 2);
            $userAccount->save();

            $transfer = Transfer::create([
                'display_transfer_id' => $display_transfer_id,
                'sender_user_id' => $user->id,
                'sender_user_name' => $user->user_name,
                'receiver_user_id' => $receiverUser->id,
                'receiver_user_name' => $receiverUser->user_name,
                'amount' => $amount,
                'exchange_rate' => $config->exchange_rate_platform,
                'cny_amount' => $cnyAmount,
                'fee' => $config->transfer_fee,
                'actual_amount' => 0.00,
                'sender_balance_before' => 0.00,
                'sender_balance_after' => 0.00,
                'receiver_balance_before' => 0.00,
                'receiver_balance_after' => 0.00,
                'status' => BusinessDef::TRANSFER_WAIT,
            ]);
            $transfer->save();

            // 创建双方的交易记录
            $senderTransaction = $this->generateTransaction($transfer, 'transfer_send');
            $receiverTransaction = $this->generateTransaction($transfer, 'transfer_receive');

            // 记录以便反查
            $transfer->sender_transaction_id = $senderTransaction->transaction_id;
            $transfer->receiver_transaction_id = $receiverTransaction->transaction_id;
            $transfer->save();

            // 提交事务
            DB::commit();

            // 提交转账成功，推送消息给后台管理员
            // business id
            $today = Carbon::now()->format('Ymd');
            $todayBusinessIncrKey = "business:{$today}:sequence";
            $businessSequence = Redis::incr($todayBusinessIncrKey);

            $formattedSequence = str_pad($businessSequence, 4, '0', STR_PAD_LEFT); // 生成 3 位随机数，填充 0
            $business_id = "${today}_${formattedSequence}";

            AdminMessageHelper::pushMessage([
                'business_id' => $business_id,
                'business_type' => BusinessDef::ADMIN_BUSINESS_TYPE_TRANSFER,
                'reference_id' => $transfer->id,
                'title' => '',
                'content' => '',
            ]);

            // 通知管理员业务变动
            event(new BusinessUpdated());

            return ApiResponse::success([
                'transfer' => $transfer,
            ]);
        } catch (\Exception $e) {
            \Log::error('An error occurred: ' . $e->getMessage());
            // 回滚事务
            DB::rollBack();
            return ApiResponse::error(ApiCode::OPERATION_FAIL);
        }
    }

    protected function generateTransaction($transfer, $transactionType) {

        // transaction id
        $today = Carbon::now()->format('Ymd');
        $todayTransactionIncrKey = "transaction:{$today}:sequence";
        $transactionSequence = Redis::incr($todayTransactionIncrKey);

        $formattedSequence = str_pad($transactionSequence, 4, '0', STR_PAD_LEFT); // 生成 3 位随机数，填充 0
        $transaction_id = "${today}_${formattedSequence}";

        if ($transactionType === 'transfer_send') {
            $userId = $transfer->sender_user_id;
        } else {
            $userId = $transfer->receiver_user_id;
        }

        $newTransaction = FinancialRecord::create([
            'transaction_id' => $transaction_id,
            'user_id' => $userId,
            'amount' => $transfer->amount,
            'exchange_rate' => $transfer->exchange_rate,
            'cny_amount' => $transfer->cny_amount,
            'fee' => $transfer->fee,
            'actual_amount' => 0.00,
            'balance_before' => 0.00,
            'balance_after' => 0.00,
            'transaction_type'=> $transactionType,
            'reference_id' => $transfer->id,
            'display_reference_id' => $transfer->display_transfer_id,
        ]);
        return $newTransaction;
    }

    /**
     * 获取转账详情
     */
    public function getTransferByTranaction(Request $request)
    {
        $transfer = Transfer::where('id', $request->id)
            ->first();

        if (!$transfer) {
            return ApiResponse::error(ApiCode::TRANSFER_NOT_FOUND);
        }

        return ApiResponse::success([
            'transfer' => $transfer,
        ]);
    }

}
