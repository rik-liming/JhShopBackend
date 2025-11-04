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
use App\Events\BusinessUpdated;

class RechargeController extends Controller
{
    /**
     * 创建充值接口
     */
    public function createRecharge(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'screenshot' => 'required|image',
        ], [
            'amount.required' => '充值金额不能为空',
            'amount.min' => '充值金额要大于0',
            'screenshot.required' => '截图不能为空',
            'screenshot.image' => '截图必须是图片',
        ]);

        // 从中间件获取的用户ID
        $userId = $request->user_id_from_token ?? null;

        if (!$userId) {
            return ApiResponse::error(ApiCode::USER_NOT_FOUND);
        }

        $user = User::where('id', $userId)->first();
        if (!$user) {
            return ApiResponse::error(ApiCode::USER_NOT_FOUND);
        }

        // 查找是否存在待处理的充值，不允许充值期间再次申请
        $existingRecharge = Recharge::where('user_id', $userId)
            ->where('status', BusinessDef::RECHARGE_WAIT)
            ->first();
        if ($existingRecharge) {
            return ApiResponse::error(ApiCode::RECHARGE_REQUEST_LIMIT);
        }

        $userAccount = UserAccount::where('user_id', $userId)->first();
        if (!$userAccount) {
            return ApiResponse::error(ApiCode::USER_ACCOUNT_NOT_FOUND);
        }

        $config = PlatformConfig::first();
        if (!$config) {
            return ApiResponse::error(ApiCode::CONFIG_NOT_FOUND);
        }

        // transaction id
        $today = Carbon::now()->format('Ymd');
        $todayTransactionIncrKey = "transaction:{$today}:sequence";
        $transactionSequence = Redis::incr($todayTransactionIncrKey);

        $formattedSequence = str_pad($transactionSequence, 4, '0', STR_PAD_LEFT); // 生成 3 位随机数，填充 0
        $transaction_id = "${today}_${formattedSequence}";

        // display recharge id
        $date = Carbon::now()->format('YmdHis');
        $randomNumber = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT); // 生成 4 位随机数，填充 0
        $display_recharge_id = "${date}${randomNumber}";

        $amount = $request->amount;

        // 处理截图保存逻辑
        $image = $request->file('screenshot');

        // 确保服务器上的 /data/images 目录存在
        $imageDirectory = '/data/images';
        if (!file_exists($imageDirectory)) {
            // 如果目录不存在，则创建它
            mkdir($imageDirectory, 0777, true);
        }

        // 为图片生成一个唯一的文件名
        $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();

        // 将图片保存到 /data/images 目录
        $image->move($imageDirectory, $imageName);

        // 记录图片路径
        $recharge_images = $imageDirectory . '/' . $imageName;

        $newRecharge = DB::transaction(function() use ($request, $userId, 
            $user, $config, $userAccount, $amount, $recharge_images, $transaction_id, $display_recharge_id) {

            $cnyAmount = bcmul($amount, $config->exchange_rate_platform, 2);
            $cnyAmount = ceil($cnyAmount * 100) / 100;

            $recharge = Recharge::create([
                'display_recharge_id' => $display_recharge_id,
                'user_id' => $userId,
                'user_name' => $user->user_name,
                'amount' => $amount,
                'exchange_rate' => $config->exchange_rate_platform,
                'cny_amount' => $cnyAmount, 
                'actual_amount' => 0.00,
                'recharge_address' => $config->payment_address,
                'recharge_images' => $recharge_images, // text 类型字段，传递空字符串
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

            return $recharge;
        });

        if (!$newRecharge) {
            return ApiResponse::error(ApiCode::RECHARGE_REQUEST_FAIL);
        }

        // 提交充值成功，推送消息给后台管理员
        // business id
        $today = Carbon::now()->format('Ymd');
        $todayBusinessIncrKey = "business:{$today}:sequence";
        $businessSequence = Redis::incr($todayBusinessIncrKey);

        $formattedSequence = str_pad($businessSequence, 4, '0', STR_PAD_LEFT); // 生成 3 位随机数，填充 0
        $business_id = "${today}_${formattedSequence}";

        AdminMessageHelper::pushMessage([
            'business_id' => $business_id,
            'business_type' => BusinessDef::ADMIN_BUSINESS_TYPE_RECHARGE,
            'reference_id' => $newRecharge->id,
            'title' => '',
            'content' => '',
        ]);

        // 通知管理员业务变动
        event(new BusinessUpdated());

        return ApiResponse::success([
            'recharge' => $newRecharge,
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
