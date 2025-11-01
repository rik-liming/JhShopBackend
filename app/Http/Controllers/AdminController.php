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

        $firstBindSecret = false;
        $google2fa = new Google2FA();
        // 判断是否已绑定Google Authenticator
        if (!$admin->two_factor_secret) {
            // 首次绑定逻辑（生成secret并存入用户信息）
            $secret = $google2fa->generateSecretKey();

            $admin->two_factor_secret = $secret;
            $admin->save();

            $firstBindSecret = true;
        }

        // 以是否成功登录过，作为是否绑定过的依据
        if (!$admin->last_login_time || $firstBindSecret) {
            $qrCodeUrl = $google2fa->getQRCodeUrl(
                'JhAdmin',
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

    /**
     * 获取管理员信息
     */
    public function getAdminInfo(Request $request)
    {
        // 从中间件获取的用户ID
        $adminId = $request->admin_id_from_token ?? null;
        $admin = Admin::where('id', $adminId)->first()->makeVisible(['password', 'two_factor_secret']);

        if (!$admin) {
            return ApiResponse::error(ApiCode::ADMIN_NOT_FOUND);
        }

        $google2fa = new Google2FA();
        $qrCodeUrl = $google2fa->getQRCodeUrl(
            'JhAdmin',
            $admin->user_name,
            $admin->two_factor_secret,
        );

        return ApiResponse::success([
            'admin' => $admin,
            'qrCodeUrl' => $qrCodeUrl,
        ]);
    }

    /**
     * 更新管理员信息
     */
    public function updateAdmin(Request $request)
    {
        // 获取传入的更新参数
        $validated = $request->validate([
            'password' => 'string',
            'two_factor_secret' => 'nullable|string',
        ]);

        // 从中间件获取的用户ID
        $adminId = $request->admin_id_from_token ?? null;
        $admin = Admin::where('id', $adminId)->first();

        if (!$admin) {
            return ApiResponse::error(ApiCode::ADMIN_NOT_FOUND);
        }

        // 保存更新
        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }
        $admin->update($validated);

        return ApiResponse::success([
            'admin' => $admin
        ]);
    }

    /**
     * 更新密钥信息
     */
    public function regenSecret(Request $request)
    {
        // 从中间件获取的用户ID
        $adminId = $request->admin_id_from_token ?? null;
        $admin = Admin::where('id', $adminId)->first();

        if (!$admin) {
            return ApiResponse::error(ApiCode::ADMIN_NOT_FOUND);
        }

        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();
        $qrCodeUrl = $google2fa->getQRCodeUrl(
            'JhAdmin',
            $admin->user_name,
            $secret,
        );

        return ApiResponse::success([
            'secret' => $secret,
            'qrCodeUrl' => $qrCodeUrl,
        ]);
    }
}