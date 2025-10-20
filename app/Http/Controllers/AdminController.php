<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Exceptions\ApiException;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use App\Helpers\ApiResponse;
use App\Enums\ApiCode;

use App\Models\User;
use App\Models\Recharge;
use App\Models\UserAccount;

class AdminController extends Controller
{
    // 登录
    public function login(Request $request)
    {
        $request->validate([
            'user_name' => 'required',
            'password' => 'required'
        ]);

        $admin = Admin::where('user_name', $request->user_name)->first();

        if (!$admin || !Hash::check($request->password, $admin->password)) {
            return ApiResponse::error(ApiCode::ADMIN_NAME_PASSWORD_WRONG);
        }

        $google2fa = new Google2FA();
        // 判断是否已绑定Google Authenticator
        if (!$admin->two_factor_secret) {
            // 首次绑定逻辑（生成secret并存入用户信息）
            $secret = $google2fa->generateSecretKey();

            $admin->two_factor_secret = $secret;
            $admin->save();
        }

        // 以是否成功登录过，作为是否绑定过的依据
        if (!$admin->last_login_time) {
            $qrCodeUrl = $google2fa->getQRCodeUrl(
                'JhShopAdmin',
                $admin->user_name,
                $admin->two_factor_secret,
            );
            return ApiResponse::success([
                'qr_code_url' => $qrCodeUrl
            ]);
        }
        
        // 返回需要输入OTP
        return ApiResponse::success([
            'need_otp' => true,
        ]);
    }

    // 校验otp
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'user_name' => 'required',
            'otp' => 'required'
        ]);

        $admin = Admin::where('user_name', $request->user_name)->first();
        if (!$admin) {
            return ApiResponse::error(ApiCode::ADMIN_NOT_FOUND);
        }

        $google2fa = new Google2FA();
        $valid = $google2fa->verifyKey($admin->two_factor_secret, $request->otp);
        if (!$valid) {
            return ApiResponse::error(ApiCode::ADMIN_2FA_INVALID);
        }

        $now = Carbon::now();
        $ip = $request->ip();
        $admin->last_login_ip = $ip;
        $admin->last_login_time = $now;
        $admin->save();

        // OTP验证通过后，生成Token并存入Redis
        $token = Str::random(64);
        Redis::setex("admin:login:token:$token", 3600, $admin->id); // 1800秒

        return ApiResponse::success([
            'token' => $token,
            'admin' => $admin
        ]);
    }

    // 退出登录
    public function logout(Request $request)
    {
        $token = $request->bearerToken();
        if ($token) {
            Redis::del("admin:login:token:$token");
        }
        return ApiResponse::success([]);
    }
}