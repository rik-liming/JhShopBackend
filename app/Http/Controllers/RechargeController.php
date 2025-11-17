<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Helpers\ApiResponse;
use App\Enums\ApiCode;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Recharge;
use App\Models\FinancialRecord;
use App\Models\PlatformConfig;
use App\Models\UserAccount;
use Illuminate\Support\Facades\Redis;
use App\Helpers\AdminMessageHelper;
use App\Enums\BusinessDef;
use App\Events\AdminBusinessUpdated;

class RechargeController extends Controller
{
    /**
     * 创建充值接口
     */
    public function createRecharge(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'screenshot' => 'required|image',
        ], [
            'amount.required' => '充值金额不能为空',
            'amount.min' => '充值金额至少0.01',
            'screenshot.required' => '截图不能为空',
            'screenshot.image' => '截图必须是图片',
        ]);

        $userId = $request->user_id_from_token ?? null;
        if (!$userId) {
            return ApiResponse::error(ApiCode::USER_NOT_FOUND);
        }

        DB::beginTransaction();
        try {
            // 查询用户和账户并加锁，保证数据安全
            $user = User::where('id', $userId)->lockForUpdate()->first();
            if (!$user) {
                throw new \Exception('', ApiCode::USER_NOT_FOUND);
            }

            $userAccount = UserAccount::where('user_id', $userId)->lockForUpdate()->first();
            if (!$userAccount) {
                throw new \Exception('', ApiCode::USER_ACCOUNT_NOT_FOUND);
            }

            // 检查是否存在待处理的充值
            $existingRecharge = Recharge::where('user_id', $userId)
                ->where('status', BusinessDef::RECHARGE_WAIT)
                ->first();
            if ($existingRecharge) {
                throw new \Exception('', ApiCode::RECHARGE_REQUEST_LIMIT);
            }

            $config = PlatformConfig::first();
            if (!$config) {
                throw new \Exception('', ApiCode::CONFIG_NOT_FOUND);
            }

            // 生成 transaction_id
            $today = Carbon::now()->format('Ymd');
            $transactionSequence = Redis::incr("transaction:{$today}:sequence");
            $transaction_id = $today . '_' . str_pad($transactionSequence, 4, '0', STR_PAD_LEFT);

            // display recharge id
            $display_recharge_id = Carbon::now()->format('YmdHis') . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);

            $amount = $request->amount;

            // 保存截图
            $image = $request->file('screenshot');
            $imageDirectory = '/data/images';
            if (!file_exists($imageDirectory)) {
                mkdir($imageDirectory, 0777, true);
            }
            $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
            $image->move($imageDirectory, $imageName);
            $recharge_images = $imageDirectory . '/' . $imageName;

            // 计算人民币金额
            $cnyAmount = ceil(bcmul($amount, $config->exchange_rate_platform, 2) * 100) / 100;

            // 创建充值记录
            $recharge = Recharge::create([
                'display_recharge_id' => $display_recharge_id,
                'user_id' => $userId,
                'user_name' => $user->user_name,
                'amount' => $amount,
                'exchange_rate' => $config->exchange_rate_platform,
                'cny_amount' => $cnyAmount,
                'actual_amount' => 0.00,
                'recharge_address' => $config->payment_address,
                'recharge_images' => $recharge_images,
                'balance_before' => 0.00,
                'balance_after' => 0.00,
                'status' => BusinessDef::RECHARGE_WAIT,
            ]);

            FinancialRecord::create([
                'transaction_id' => $transaction_id,
                'user_id' => $userId,
                'amount' => $amount,
                'exchange_rate' => $config->exchange_rate_platform,
                'cny_amount' => $cnyAmount,
                'fee' => 0.00,
                'actual_amount' => 0.00,
                'balance_before' => 0.00,
                'balance_after' => 0.00,
                'transaction_type'=> 'recharge',
                'reference_id' => $recharge->id,
                'display_reference_id' => $display_recharge_id,
                'description' => "",
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('充值失败: ' . $e->getMessage());
            // return ApiResponse::error(ApiCode::RECHARGE_REQUEST_FAIL, $e->getMessage());
            return ApiResponse::error($e->getCode());
        }

        // 推送消息给后台管理员
        $businessSequence = Redis::incr("business:{$today}:sequence");
        $business_id = $today . '_' . str_pad($businessSequence, 4, '0', STR_PAD_LEFT);

        AdminMessageHelper::pushMessage([
            'business_id' => $business_id,
            'business_type' => BusinessDef::ADMIN_BUSINESS_TYPE_RECHARGE,
            'reference_id' => $recharge->id,
            'title' => '',
            'content' => '',
        ]);

        event(new AdminBusinessUpdated());

        return ApiResponse::success([
            'recharge' => $recharge,
        ]);
    }

    /**
     * 获取充值详情
     */
    public function getRechargeByTranaction(Request $request)
    {
        $recharge = Recharge::where('id', $request->id)
            ->first();

        if (!$recharge) {
            return ApiResponse::error(ApiCode::RECHARGE_NOT_FOUND);
        }

        return ApiResponse::success([
            'recharge' => $recharge,
        ]);
    }
}
