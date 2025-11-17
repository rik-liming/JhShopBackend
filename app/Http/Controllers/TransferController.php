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
use App\Events\AdminBusinessUpdated;

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

        $userId = $request->user_id_from_token ?? null;
        if (!$userId) {
            return ApiResponse::error(ApiCode::USER_NOT_FOUND);
        }

        try {
            DB::beginTransaction();

            $receiver_user_id = $request->receiver_user_id;
            $amount = $request->amount;
            $payment_password = $request->payment_password;

            // 锁定用户
            $user = User::where('id', $userId)->lockForUpdate()->first();
            $receiverUser = User::where('id', $receiver_user_id)->lockForUpdate()->first();

            if (!$user || !$receiverUser) {
                throw new \Exception("用户不存在", ApiCode::USER_NOT_FOUND);
            }

            if (!in_array($receiverUser->role, [
                BusinessDef::USER_ROLE_SELLER,
                BusinessDef::USER_ROLE_AGENT
            ])) {
                throw new \Exception("转账接收方角色不合法", ApiCode::TRANSFER_USER_ILLEGAL_ROLE);
            }

            $config = PlatformConfig::first();
            if (!$config) {
                throw new \Exception("平台配置不存在", ApiCode::CONFIG_NOT_FOUND);
            }

            // 锁定账户
            $userAccount = UserAccount::where('user_id', $userId)->lockForUpdate()->first();
            $receiverUserAccount = UserAccount::where('user_id', $receiver_user_id)->lockForUpdate()->first();

            if (!$userAccount || !$receiverUserAccount) {
                throw new \Exception("账户不存在", ApiCode::USER_ACCOUNT_NOT_FOUND);
            }

            if (!$userAccount->payment_password) {
                throw new \Exception("未设置支付密码", ApiCode::USER_PAYMENT_PASSWORD_NOT_SET);
            }

            if (!Hash::check($payment_password, $userAccount->payment_password)) {
                throw new \Exception("支付密码错误", ApiCode::USER_PAYMENT_PASSWORD_WRONG);
            }

            // 判断余额
            $totalExpense = bcadd($amount, $config->transfer_fee, 2);
            if (bccomp($totalExpense, $userAccount->available_balance, 2) > 0) {
                throw new \Exception("余额不足", ApiCode::USER_BALANCE_NOT_ENOUGH);
            }

            // 生成 ID
            $today = Carbon::now()->format('Ymd');
            $transactionSequence = Redis::incr("transaction:{$today}:sequence");
            $transaction_id = $today . '_' . str_pad($transactionSequence, 4, '0', STR_PAD_LEFT);

            $display_transfer_id = Carbon::now()->format('YmdHis') . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);

            // 金额换算
            $cnyAmount = ceil(bcmul($amount, $config->exchange_rate_platform, 2) * 100) / 100;

            // 冻结发送者金额
            $userAccount->available_balance = bcsub($userAccount->available_balance, $totalExpense, 2);
            $userAccount->save();

            // 创建转账记录
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

            // 创建交易记录
            $senderTransaction = $this->generateTransaction($transfer, 'transfer_send');
            $receiverTransaction = $this->generateTransaction($transfer, 'transfer_receive');

            $transfer->sender_transaction_id = $senderTransaction->transaction_id;
            $transfer->receiver_transaction_id = $receiverTransaction->transaction_id;
            $transfer->save();

            DB::commit();

            // 推送消息（此时事务已提交）
            $businessSequence = Redis::incr("business:{$today}:sequence");
            $business_id = $today . '_' . str_pad($businessSequence, 4, '0', STR_PAD_LEFT);

            AdminMessageHelper::pushMessage([
                'business_id' => $business_id,
                'business_type' => BusinessDef::ADMIN_BUSINESS_TYPE_TRANSFER,
                'reference_id' => $transfer->id,
                'title' => '',
                'content' => '',
            ]);

            event(new AdminBusinessUpdated());

            return ApiResponse::success(['transfer' => $transfer]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('[createTransfer] ' . $e->getMessage());

            // 返回对应 ApiCode
            $code = $e->getCode() ?: ApiCode::OPERATION_FAIL;

            return ApiResponse::error($code);
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
