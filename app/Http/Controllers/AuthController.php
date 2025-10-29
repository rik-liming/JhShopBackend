<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserAccount;
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

class AuthController extends Controller
{
    // 注册
    public function register(Request $request)
    {
        $request->validate([
            'email' => 'required|email|unique:jh_user,email',
            'password' => 'required|min:6',
            'invite_code' => 'required',
        ], [
            'email.required' => '邮箱不能为空',
            'email.email' => '邮箱格式不正确',
            'email.unique' => '邮箱已经被注册',
            'password.required' => '密码不能为空',
            'password.min' => '密码长度不能少于6位',
            'invite_code.required' => '邀请码不能为空',
        ]);

        $inviter = User::where('invite_code', $request->invite_code)->first();
        if (!$inviter) {
            throw new ApiException(ApiCode::INVALID_INVITE_CODE);
        }

        // 使用事务，以防创建失败生成脏数据
        $newUser = DB::transaction(function() use ($request, $inviter) {
            $user = User::create([
                'inviter_id' => $inviter->id,
                'inviter_name' => $inviter->user_name,
                'user_name' => $request->email,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'default',
            ]);

            $userAccount = UserAccount::create([
                'user_id' => $user->id,
            ]);

            return $user;
        });

        return ApiResponse::success([
            'user' => $newUser
        ]);
    }

    // 登录
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return ApiResponse::error(ApiCode::USER_EMAIL_PASSWORD_WRONG);
        }

        if ($user->status !== 1) {
            return ApiResponse::error(ApiCode::USER_ILLEGAL);
        }

        $firstBindSecret = false;
        $google2fa = new Google2FA();
        // 判断是否已绑定Google Authenticator
        if (!$user->two_factor_secret) {
            // 首次绑定逻辑（生成secret并存入用户信息）
            $secret = $google2fa->generateSecretKey();

            $user->two_factor_secret = $secret;
            $user->save();

            $firstBindSecret = true;
        }

        // 以是否成功登录过，或者是否新生成密钥，作为是否绑定过的依据
        if (!$user->last_login_time || $firstBindSecret) {
            $qrCodeUrl = $google2fa->getQRCodeUrl(
                'JhShop',
                $user->email,
                $user->two_factor_secret,
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
            'email' => 'required|email',
            'otp' => 'required'
        ]);

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return ApiResponse::error(ApiCode::USER_NOT_FOUND);
        }

        $google2fa = new Google2FA();
        $valid = $google2fa->verifyKey($user->two_factor_secret, $request->otp);
        if (!$valid) {
            return ApiResponse::error(ApiCode::USER_2FA_INVALID);
        }

        $now = Carbon::now();
        $ip = $request->ip();
        $user->last_login_ip = $ip;
        $user->last_login_time = $now;
        $user->save();

        // OTP验证通过后，生成Token并存入Redis
        $token = Str::random(64);
        Redis::setex("login:token:$token", 3600, $user->id); // 600秒 = 10分钟

        return ApiResponse::success([
            'token' => $token,
            'user' => $user
        ]);
    }

    // 退出登录
    public function logout(Request $request)
    {
        $token = $request->bearerToken();
        if ($token) {
            Redis::del("login:token:$token");
        }
        return ApiResponse::success([]);
    }
}