<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Helpers\ApiResponse;
use App\Enums\ApiCode;
use Carbon\Carbon;
use App\Models\Withdraw;
use App\Models\UserAccount;
use App\Models\FinancialRecord;
use App\Models\PlatformConfig;
use App\Models\User;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Hash;
use App\Helpers\AdminMessageHelper;
use App\Enums\BusinessDef;
use App\Events\AdminBusinessUpdated;

class WithdrawController extends Controller
{
    /**
     * 创建提现接口
     */
    public function createWithdraw(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'withdraw_address' => 'required',
            'payment_password' => 'required',
        ], [
            'amount.required' => '提现金额不能为空',
            'amount.min' => '提现金额至少为0.01',
            'withdraw_address.required' => '提现地址不能为空',
            'payment_password.required' => '支付密码不能为空',
        ]);

        try {
            $userId = $request->user_id_from_token ?? null;
            if (!$userId) {
                throw new \Exception('', ApiCode::USER_NOT_FOUND);
            }

            $amount = $request->amount;
            $withdraw_address = $request->withdraw_address;
            $payment_password = $request->payment_password;

            DB::beginTransaction();

            // 加锁用户 & 账户
            $user = User::where('id', $userId)->lockForUpdate()->first();
            $userAccount = UserAccount::where('user_id', $userId)->lockForUpdate()->first();
            $config = PlatformConfig::lockForUpdate()->first();

            if (!$user || !$userAccount) throw new \Exception('', ApiCode::USER_NOT_FOUND);
            if (!$config) throw new \Exception('', ApiCode::CONFIG_NOT_FOUND);

            if (!$userAccount->payment_password) {
                throw new \Exception('', ApiCode::USER_PAYMENT_PASSWORD_NOT_SET);
            }
            if (!Hash::check($payment_password, $userAccount->payment_password)) {
                throw new \Exception('', ApiCode::USER_PAYMENT_PASSWORD_WRONG);
            }

            // 校验可用余额
            $totalExpense = bcadd($amount, $config->withdrawl_fee, 2);
            if (bccomp($totalExpense, $userAccount->available_balance, 2) > 0) {
                throw new \Exception('', ApiCode::USER_BALANCE_NOT_ENOUGH);
            }

            // 生成 ID
            $today = Carbon::now()->format('Ymd');
            $transactionSequence = Redis::incr("transaction:{$today}:sequence");
            $transaction_id = $today . '_' . str_pad($transactionSequence, 4, '0', STR_PAD_LEFT);

            $display_withdraw_id = Carbon::now()->format('YmdHis')
                . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);

            $cnyAmount = ceil(bcmul($amount, $config->exchange_rate_platform, 2) * 100) / 100;

            // 冻结提现金额
            $userAccount->available_balance = bcsub($userAccount->available_balance, $totalExpense, 2);
            $userAccount->save();

            // 创建提现记录
            $withdraw = Withdraw::create([
                'display_withdraw_id' => $display_withdraw_id,
                'user_id' => $user->id,
                'user_name' => $user->user_name,
                'amount' => $amount,
                'exchange_rate' => $config->exchange_rate_platform,
                'cny_amount' => $cnyAmount,
                'withdraw_address' => $withdraw_address,
                'fee' => $config->withdrawl_fee,
                'actual_amount' => 0.00,
                'balance_before' => 0.00,
                'balance_after' => 0.00,
                'status' => BusinessDef::WITHDRAW_WAIT,
            ]);

            // 财务记录
            FinancialRecord::create([
                'transaction_id' => $transaction_id,
                'user_id' => $user->id,
                'amount' => $amount,
                'exchange_rate' => $config->exchange_rate_platform,
                'cny_amount' => $cnyAmount,
                'fee' => $config->withdrawl_fee,
                'actual_amount' => 0.00,
                'balance_before' => 0.00,
                'balance_after' => 0.00,
                'transaction_type'=> BusinessDef::TRANSACTION_TYPE_WITHDRAW,
                'reference_id' => $withdraw->id,
                'display_reference_id' => $display_withdraw_id,
            ]);

            DB::commit();

            // 推送给管理员
            $businessSequence = Redis::incr("business:{$today}:sequence");
            $business_id = $today . '_' . str_pad($businessSequence, 4, '0', STR_PAD_LEFT);

            AdminMessageHelper::pushMessage([
                'business_id' => $business_id,
                'business_type' => BusinessDef::ADMIN_BUSINESS_TYPE_WITHDRAW,
                'reference_id' => $withdraw->id,
                'title' => '',
                'content' => '',
            ]);

            event(new AdminBusinessUpdated());

            return ApiResponse::success(['withdraw' => $withdraw]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('[createWithdraw] '.$e->getMessage());
            return ApiResponse::error($e->getCode());
        }
    }



    /**
     * 获取提现详情
     */
    public function getWithdrawByTranaction(Request $request)
    {
        $withdraw = Withdraw::where('id', $request->id)
            ->first();

        if (!$withdraw) {
            return ApiResponse::error(ApiCode::WITHDRAW_NOT_FOUND);
        }

        return ApiResponse::success([
            'withdraw' => $withdraw,
        ]);
    }
}
