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

use App\Events\UserRoleChanged;

class AdminUserController extends Controller
{
    /**
     * 分页获取用户信息
     */
    public function getUserByPage(Request $request)
    {
        // 获取分页参数
        $page = $request->input('page', 1);  // 当前页，默认是第1页
        $pageSize = $request->input('page_size', 10);  // 每页显示的记录数，默认是10条

        // 获取关键词和角色过滤参数
        $id = $request->input('id', '');  // 搜索关键词，默认空字符串
        $role = $request->input('role', '');  // 角色，默认空字符串
        $email = $request->input('email', '');  // 邮箱，默认空字符串

        // 构建查询
        $query = User::select(
                'id',
                'email',
                'user_name',
                'real_name',
                'email',
                'avatar',
                'role',
                'invite_code',
                'status',
                'created_at',
            )
            ->where('status', '!=', -1)
            ->orderBy('id', 'desc');

        // 如果有传入 id 参数，进行模糊搜索
        if ($id) {
            $query->where('id', $id);
        }

        // 如果有传入 role 参数，按角色过滤
        if ($role) {
            $query->where('role', $role);
        }

        // 如果有传入 email 参数，按邮箱过滤
        if ($email) {
            $query->where('email', $email);
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

        $hasRoleChanged = false;
        if ($role) {
            if ($user->role !== $role) {
                $hasRoleChanged = true;

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
        }

        $hasStatusChanged = false;
        if ($status !== $user->status) {
            $user->status = $status;
            $hasStatusChanged = true;
        }

        // 保存更新
        $user->save();

        // 处理状态变更，发推送
        if ($hasRoleChanged) {
            event(new UserRoleChanged($user->id, $user->role));
        }
        // if ($hasStatusChanged) {

        // }

        return ApiResponse::success([
            'user' => $user
        ]);
    }
}