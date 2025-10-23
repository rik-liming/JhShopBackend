<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\ApiResponse;
use App\Enums\ApiCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PragmaRX\Google2FA\Google2FA;

use App\Models\UserPaymentMethod;
use App\Models\User;

class PaymentMethodController extends Controller
{
    public function getMyList(Request $request)
    {
        // 从中间件获取的用户ID
        $userId = $request->user_id_from_token ?? null;

        if (!$userId) {
            return ApiResponse::error(ApiCode::USER_NOT_FOUND);
        }

        $paymentMethods = UserPaymentMethod::where('status', 1)
            ->where('user_id', $userId)
            ->get();

        // 按 payment_method 分组
        $groupedData = $paymentMethods->groupBy('payment_method');

        return ApiResponse::success([
            'payments' => $groupedData
        ]);
    }

    public function create(Request $request)
    {
        // 验证输入参数
        $request->validate([
            'payment_method' => 'required|in:bank,alipay,wechat',
            'account_number' => 'required|unique:jh_user_payment_method,account_number',
            'account_name' => 'required',
            'verify_code' => 'required',
        ], [
            'payment_method.required' => '支付渠道不能为空',
            'account_number.required' => '账号不能为空',
            'account_number.unique' => '已存在该支付账号',
            'account_name.required' => '账户名不能为空',
            'verify_code.required' => '账户名不能为空',
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

        $google2fa = new Google2FA();
        $valid = $google2fa->verifyKey($user->two_factor_secret, $request->verify_code);
        if (!$valid) {
            return ApiResponse::error(ApiCode::USER_2FA_INVALID);
        }

        // 处理二维码图保存逻辑
        $image = $request->file('qr_code');
        $qrCodePath = '';
        
        if ($image) {
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
            $qrCodePath = $imageDirectory . '/' . $imageName;
        }

        $newPaymentMethod = DB::transaction(function() use ($request, $userId, $qrCodePath) {
            // 创建支付卡信息
            $paymentMethod = UserPaymentMethod::create([
                'user_id' => $userId,
                'payment_method' => $request->payment_method,
                'account_name' => $request->account_name,
                'account_number' => $request->account_number,
                'bank_name' => $request->bank_name ?? '',
                'issue_bank_name' => $request->issue_bank_name ?? '',
                'qr_code' => $qrCodePath,
                'status' => 1, // 默认状态为启用
            ]);

            return $paymentMethod;
        });

        return ApiResponse::success([
            'id' => $newPaymentMethod->id,
        ]);
    }

    public function update(Request $request)
    {
        // 验证输入参数
        $request->validate([
            'id' => 'required',
            'payment_method' => 'required|in:bank,alipay,wechat',
            'account_number' => 'required',
            'account_name' => 'required',
            'verify_code' => 'required',
        ], [
            'id.required' => '支付渠道ID不能为空',
            'payment_method.required' => '支付渠道不能为空',
            'account_number.required' => '账号不能为空',
            'account_name.required' => '账户名不能为空',
            'verify_code.required' => '账户名不能为空',
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

        $google2fa = new Google2FA();
        $valid = $google2fa->verifyKey($user->two_factor_secret, $request->verify_code);
        if (!$valid) {
            return ApiResponse::error(ApiCode::USER_2FA_INVALID);
        }

        $paymentMethod = UserPaymentMethod::where('id', $request->id)->first();
        if (!$paymentMethod) {
            return ApiResponse::error(ApiCode::USER_PAYMENT_METHOD_NOT_FOUND);
        }

        // 处理二维码图保存逻辑
        $image = $request->file('qr_code');
        $qrCodePath = $paymentMethod->qr_code;
        
        if ($image) {
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
            $qrCodePath = $imageDirectory . '/' . $imageName;
        }

        $newPaymentMethod = DB::transaction(function() use ($request, $userId, $paymentMethod, $qrCodePath) {
            // 创建支付卡信息
            $paymentMethod->account_name = $request->account_name;
            $paymentMethod->account_number = $request->account_number;
            $paymentMethod->bank_name = $request->bank_name ?? '';
            $paymentMethod->issue_bank_name = $request->issue_bank_name ?? '';
            $paymentMethod->qr_code = $qrCodePath;
            $paymentMethod->save();

            return $paymentMethod;
        });

        return ApiResponse::success([
            'id' => $newPaymentMethod->id,
        ]);
    }

    public function delete(Request $request)
    {
        $id = $request->id;

        // 查找是否存在该记录
        $paymentMethod = UserPaymentMethod::find($id);

        if (!$paymentMethod) {
            return ApiResponse::error(ApiCode::USER_PAYMENT_METHOD_NOT_FOUND);
        }

        // 删除记录
        $paymentMethod->delete();

        // 返回成功响应
        return ApiResponse::success([
            'id' => $id,
        ]);
    }

    public function getInfo(Request $request)
    {
        $paymentMethod = UserPaymentMethod::where('id', $request->id)->first();

        if (!$paymentMethod) {
            return ApiResponse::error(ApiCode::USER_PAYMENT_METHOD_NOT_FOUND);
        }

        return ApiResponse::success([
            'payment_method' => $paymentMethod,
        ]);
    }
}
