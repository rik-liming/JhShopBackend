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
use App\Events\BusinessUpdated;

class WithdrawController extends Controller
{
    /**
     * 创建提现接口
     */
    public function createWithdraw(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'withdraw_address' => 'required',
            'payment_password' => 'required',
        ], [
            'amount.required' => '提现金额不能为空',
            'amount.min' => '提现金额至少为1',
            'withdraw_address.required' => '提现地址不能为空',
            'payment_password.required' => '支付密码不能为空',
        ]);

        // 从中间件获取的用户ID
        $userId = $request->user_id_from_token ?? null;
        $withdraw_address = $request->withdraw_address;
        $amount = $request->amount;
        $payment_password = $request->payment_password;

        if (!$userId) {
            return ApiResponse::error(ApiCode::USER_NOT_FOUND);
        }

        $user = User::where('id', $userId)->first();
        if (!$user) {
            return ApiResponse::error(ApiCode::USER_NOT_FOUND);
        }

        $config = PlatformConfig::first();
        if (!$config) {
            return ApiResponse::error(ApiCode::CONFIG_NOT_FOUND);
        }

        $userAccount = UserAccount::where('user_id', $userId)->first();
        if (!$userAccount) {
            return ApiResponse::error(ApiCode::USER_ACCOUNT_NOT_FOUND);
        }

        if (!$userAccount->payment_password) {
            return ApiResponse::error(ApiCode::USER_PAYMENT_PASSWORD_NOT_SET);
        }

        if (!Hash::check($payment_password, $userAccount->payment_password)) {
            return ApiResponse::error(ApiCode::USER_PAYMENT_PASSWORD_WRONG);
        }

        $totalExpense = bcadd($amount, $config->withdrawl_fee, 2);
        if (bccomp($totalExpense, $userAccount->available_balance, 2) > 0) {
            return ApiResponse::error(ApiCode::USER_BALANCE_NOT_ENOUGH);
        }

        // transaction id
        $today = Carbon::now()->format('Ymd');
        $todayTransactionIncrKey = "transaction:{$today}:sequence";
        $transactionSequence = Redis::incr($todayTransactionIncrKey);

        $formattedSequence = str_pad($transactionSequence, 4, '0', STR_PAD_LEFT); // 生成 3 位随机数，填充 0
        $transaction_id = "${today}_${formattedSequence}";

        // display withdraw id
        $date = Carbon::now()->format('YmdHis');
        $randomNumber = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT); // 生成 4 位随机数，填充 0
        $display_withdraw_id = "${date}${randomNumber}";

        $newWithdraw = DB::transaction(function() use ($request, $user, $userAccount, $config,
            $amount, $totalExpense, $withdraw_address, $display_withdraw_id, $transaction_id) {

            $cnyAmount = bcmul($amount, $config->exchange_rate_platform, 2);
            $cnyAmount = ceil($cnyAmount * 100) / 100;

            // 提交申请的同时，需要冻结提现金额
            $userAccount->available_balance = bcsub($userAccount->available_balance, $totalExpense, 2);
            $userAccount->save();

            $withdraw = Withdraw::create([
                'display_withdraw_id' => $display_withdraw_id, // 当前登录用户的ID
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
                'transaction_type'=> 'withdraw',
                'reference_id' => $withdraw->id,
                'display_reference_id' => $display_withdraw_id,
            ]);

            return $withdraw;
        });

        // 提交提现成功，推送消息给后台管理员
        // business id
        $today = Carbon::now()->format('Ymd');
        $todayBusinessIncrKey = "business:{$today}:sequence";
        $businessSequence = Redis::incr($todayBusinessIncrKey);

        $formattedSequence = str_pad($businessSequence, 4, '0', STR_PAD_LEFT); // 生成 3 位随机数，填充 0
        $business_id = "${today}_${formattedSequence}";

        AdminMessageHelper::pushMessage([
            'business_id' => $business_id,
            'business_type' => BusinessDef::ADMIN_BUSINESS_TYPE_WITHDRAW,
            'reference_id' => $newWithdraw->id,
            'title' => '',
            'content' => '',
        ]);

        // 通知管理员业务变动
        event(new BusinessUpdated());

        return ApiResponse::success([
            'withdraw' => $newWithdraw,
        ]);
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
