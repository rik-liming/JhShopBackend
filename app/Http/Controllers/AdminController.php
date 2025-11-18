<?php

namespace App\Http\Controllers;

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
use App\Enums\BusinessDef;

use App\Models\Admin;
use App\Models\AdminRole;

use App\Events\AdminStatusChanged;
use App\Events\AdminPasswordChanged;

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

        if ($admin->status !== BusinessDef::ADMIN_STATUS_ACTIVE) {
            return ApiResponse::error(ApiCode::ADMIN_ILLEGAL);
        }

        $adminRole = AdminRole::where('role', $admin->role)->first();
        if (!$adminRole || $adminRole->status !== BusinessDef::ADMIN_ROLE_STATUS_ACTIVE) {
            return ApiResponse::error(ApiCode::ADMIN_ILLEGAL);
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

        // 更新密码
        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        // 保存更新
        $admin->update($validated);

        return ApiResponse::success([
            'admin' => $admin
        ]);
    }

    /**
     * 更新管理员信息
     */
    public function updateOtherAdmin(Request $request)
    {
        // 获取传入的更新参数
        $validated = $request->validate([
            'admin_id' => 'required|int',
            'password' => 'string',
            'two_factor_secret' => 'nullable|string',
            'status' => 'nullable|int'
        ]);

        $otherAdminId = $request->admin_id;
        $otherAdmin = Admin::where('id', $otherAdminId)->first();

        if (!$otherAdmin) {
            return ApiResponse::error(ApiCode::ADMIN_NOT_FOUND);
        }

        // 更新密码
        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        // 被封禁，需要发推送强制退出
        if (isset($validated['status']) && $validated['status'] === BusinessDef::ADMIN_STATUS_INACTIVE) {
            event(new AdminStatusChanged(
                $otherAdmin->id,
            ));
        }

        // 保存更新
        $otherAdmin->update($validated);

        return ApiResponse::success([
            'other_admin' => $otherAdmin
        ]);
    }

    /**
     * 新建管理员
     */
    public function createAdmin(Request $request)
    {
        // 获取传入的更新参数
        $validated = $request->validate([
            'user_name' => 'required|string',
            'password' => 'string',
            'role' => 'string',
        ]);

        $existingAdmin = Admin::where('user_name', $request->user_name)
            ->where('status', '!=', BusinessDef::ADMIN_STATUS_DELETED)
            ->first();
        if ($existingAdmin) {
            return ApiResponse::error(ApiCode::ADMIN_ALREADY_EXIST);
        }

        $newAdmin = Admin::create([
            'user_name' => $request->user_name,
            'password' => Hash::make($request->password),
            'role' => $request->role,
        ]);

        return ApiResponse::success([
            'admin' => $newAdmin
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

    /**
     * 分页获取用户信息
     */
    public function getAdminByPage(Request $request)
    {
        // 获取分页参数
        $page = $request->input('page', 1);  // 当前页，默认是第1页
        $pageSize = $request->input('page_size', 10);  // 每页显示的记录数，默认是10条

        // 获取关键词和角色过滤参数
        $user_name = $request->input('user_name', '');  // 搜索关键词，默认空字符串
        $role = $request->input('role', '');  // 角色，默认空字符串

        // 构建查询
        $query = Admin::select(
            'id',
            'user_name',
            'role',
            'status',
            'created_at',
        )
        ->where('status', '!=', BusinessDef::ADMIN_STATUS_DELETED)
        ->orderBy('id', 'desc');

        // 如果有传入 id 参数，进行模糊搜索
        if ($user_name) {
            $query->where('user_name', $user_name);
        }

        // 如果有传入 role 参数，按角色过滤
        if ($role) {
            $query->where('role', $role);
        }

        // 获取符合条件的用户总数
        $totalCount = $query->count();

        // 分页
        $admins = $query->skip(($page - 1) * $pageSize)  // 计算分页的偏移量
                    ->take($pageSize)  // 每页获取指定数量的用户
                    ->get();

        return ApiResponse::success([
            'total' => $totalCount,  // 总记录数
            'current_page' => $page,  // 当前页
            'page_size' => $pageSize,  // 每页记录数
            'admins' => $admins,  // 当前页的用户列表
        ]);
    }

    /**
     * 获取当前用户密码信息
     */
    public function getPasswordInfo(Request $request)
    {
        $adminId = $request->input('admin_id', '');  // 搜索关键词，默认空字符串

        if (!$adminId) {
            return ApiResponse::error(ApiCode::ADMIN_NOT_FOUND);
        }

        $admin = Admin::where('id', $adminId)
        ->first();

        return ApiResponse::success([
            'admin_id' => $admin->id,
            'admin_name' => $admin->user_name,
            'login_password' => $admin->password,
            'two_factor_secret' => $admin->two_factor_secret,
        ]);
    }

    /**
     * 修改当前用户密码信息
     */
    public function updatePasswordInfo(Request $request)
    {
        $request->validate([
            'login_password' => 'required|min:6',
        ], [
            'login_password.required' => '登录密码不能为空',
            'login_password.min' => '登录密码长度不能少于6位',
        ]);

        $adminId = $request->input('admin_id', '');
        $loginPassword = $request->input('login_password', '');
        $twoFactorSecret = $request->input('two_factor_secret', '');

        if (!$adminId) {
            return ApiResponse::error(ApiCode::ADMIN_NOT_FOUND);
        }

        $admin = Admin::where('id', $adminId)
        ->first();

        $hasChangeAdmin = false;

        if ($loginPassword !== $admin->password) {
            $admin->password = Hash::make($loginPassword);
            $hasChangeAdmin = true;
        }

        if ($twoFactorSecret !== $admin->two_factor_secret) {
            $admin->two_factor_secret = $twoFactorSecret;
            $hasChangeAdmin = true;
        }

        if ($hasChangeAdmin) {
            $admin->save();

            // 用户信息发生改变，需要推送重新登录
            event(new AdminPasswordChanged($admin->id));
        }

        return ApiResponse::success([]);
    }
}