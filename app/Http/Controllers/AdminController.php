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

class AdminController extends Controller
{
    // 登录
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required',
            'password' => 'required'
        ]);

        $admin = Admin::where('user_name', $request->username)->first();

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
            'username' => 'required',
            'otp' => 'required'
        ]);

        $admin = Admin::where('user_name', $request->username)->first();
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
     * 分页获取用户信息
     */
    public function getUserByPage(Request $request)
    {
        // 获取分页参数
        $page = $request->input('page', 1);  // 当前页，默认是第1页
        $pageSize = $request->input('pagesize', 10);  // 每页显示的记录数，默认是10条

        // 获取关键词和角色过滤参数
        $keyword = $request->input('keyword', '');  // 搜索关键词，默认空字符串
        $role = $request->input('role', '');  // 角色，默认空字符串

        // 构建查询
        $query = User::select(
                'id',
                'email',
                'user_name',
                'real_name',
                'email',
                'avatar',
                'role',
                'status',
                'created_at',
            )
            ->where('status', '!=', -1);  // 过滤掉status为-1的用户

        // 如果有传入 keyword 参数，进行模糊搜索
        if ($keyword) {
            $query->where(function($q) use ($keyword) {
                $q->where('user_name', 'like', "%{$keyword}%")
                ->orWhere('real_name', 'like', "%{$keyword}%");
            });
        }

        // 如果有传入 role 参数，按角色过滤
        if ($role) {
            $query->where('role', $role);
        }

        // 获取符合条件的用户总数
        $totalCount = $query->count();

        // 分页
        $users = $query->skip(($page - 1) * $pageSize)  // 计算分页的偏移量
                    ->take($pageSize)  // 每页获取指定数量的用户
                    ->get();

        return ApiResponse::success([
            'total' => $totalCount,  // 总记录数
            'current_page' => $page,  // 当前页
            'page_size' => $pageSize,  // 每页记录数
            'users' => $users,  // 当前页的用户列表
        ]);
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

    /**
     * 更新用户信息
     */
    public function updateUser(Request $request)
    {
        // 获取传入的更新参数
        $userName = $request->input('user_name', null);  // 用户名
        $realName = $request->input('real_name', null);  // 真实姓名
        $role = $request->input('role', null);  // 角色
        $status = $request->input('status', null);  // 状态

        // 查找指定ID的用户
        $user = User::find($request->id);

        if (!$user) {
            return ApiResponse::error(ApiCode::USER_NOT_FOUND);
        }

        // 更新用户信息
        if ($userName) {
            $user->user_name = $userName;
        }
        if ($realName) {
            $user->real_name = $realName;
        }
        if ($role) {
            $user->role = $role;
            // 如果变更为代理，那么rootAgent就是自己；如果是商家，rootAgent就是代理
            if ($role == 'agent' && !$user->invite_code) {
                $user->invite_code = $this->generateUniqueInviteCode($role);
                $user->root_agent_id = $user->id;
                $user->root_agent_name = $user->user_name;
            } else if ($role == 'seller') {
                $user->root_agent_id = $user->inviter_id;
                $user->root_agent_name = $user->inviter_name;
            }
        }
        if ($status !== $user->status) {
            $user->status = $status;
        }

        // 保存更新
        $user->save();

        return ApiResponse::success([
            'user' => $user
        ]);
    }
}