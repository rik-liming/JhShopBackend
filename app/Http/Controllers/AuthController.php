<?php

namespace App\Http\Controllers;

use App\Models\User;
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
            'inviteCode' => 'required',
        ], [
            'email.required' => '邮箱不能为空',
            'email.email' => '邮箱格式不正确',
            'email.unique' => '邮箱已经被注册',
            'password.required' => '密码不能为空',
            'password.min' => '密码长度不能少于6位',
            'inviteCode.required' => '邀请码不能为空',
        ]);

        $inviter = User::where('invite_code', $request->inviteCode)->first();
        if (!$inviter) {
            throw new ApiException(ApiCode::INVALID_INVITE_CODE);
        }

        $role = 'default';
        if ($request->role && in_array($request->role, [
                'buyer',
                'seller',
                'autoBuyer'
            ])) {
            $role = $request->role;

            // inviter是platform时，role是agent
            if ($request->role == 'seller' && $inviter->role == 'platform') {
                $role = 'agent';
            }
        }

        // 使用事务，以防创建失败生成脏数据
        $newUser = DB::transaction(function() use ($request, $role, $inviter) {

            $root_agent_id = 0;
            $root_agent_name = '';
            $now = Carbon::now();
            $ip = $request->ip();
            $new_invite_code = null;

            if ($role == 'seller') {
                $root_agent_id = $inviter->root_agent_id;
                $root_agent_name = $inviter->root_agent_name;
            }

            // agent或者seller，才有专属邀请码
            if ($role == 'seller' || $role == 'agent') {
                $new_invite_code = $this->generateUniqueInviteCode($role);
            }

            $user = User::create([
                'inviter_id' => $inviter->id,
                'inviter_name' => $inviter->user_name,
                'user_name' => $request->email,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => $role,
                'root_agent_id' => $root_agent_id,
                'root_agent_name' => $root_agent_name,
                'last_login_ip'   => $ip,
                'last_login_time' => $now,
                'invite_code' => $new_invite_code,
            ]);

            // 当自己是agent时，rootAgent就是自己
            // 注意必须等user创建成功后，才能拿到自己id
            if ($role == 'agent') {
                $user->root_agent_id = $user->id;
                $user->root_agent_name = $user->user_name;
                $user->save();
            }

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

        // 判断是否已绑定Google Authenticator
        if (!$user->two_factor_secret) {
            // 首次绑定逻辑（生成secret并存入用户信息）
            $google2fa = new Google2FA();
            $secret = $google2fa->generateSecretKey();

            $user->two_factor_secret = $secret;
            $user->save();

            $qrCodeUrl = $google2fa->getQRCodeUrl(
                'JhShop',
                $user->email,
                $secret
            );

            return ApiResponse::success([
                'qrCodeUrl' => $qrCodeUrl
            ]);
        }

        // 返回需要输入OTP
        return ApiResponse::success([
            'needOtp' => true,
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

        // OTP验证通过后，生成Token并存入Redis
        $token = Str::random(64);
        Redis::setex("login:token:$token", 600, $user->id); // 600秒 = 10分钟

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

    /**
     * 生成唯一邀请码
     */
    protected function generateUniqueInviteCode($role)
    {
        if ($role == 'agent') {
            $prefix = '88';
        } else if ($role == 'seller') {
            $prefix = '66';
        }

        do {
            $code = $prefix . mt_rand(100000, 999999); // prefix + 6位随机数字
            $exists = User::where('invite_code', $code)->exists();
        } while ($exists);

        return $code;
    }
}