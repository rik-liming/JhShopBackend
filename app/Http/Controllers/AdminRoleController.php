<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Helpers\ApiResponse;
use App\Enums\ApiCode;
use App\Models\AdminRoleRule;
use App\Models\AdminPrivilegeRule;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Enums\BusinessDef;

use App\Events\AdminRoleStatusChanged;

class AdminRoleController extends Controller
{
	/**
	 * 获取rule id的集合
	 */
    public function getRoleRules(Request $request)
    {
		// 从中间件获取的用户ID
        $adminId = $request->admin_id_from_token ?? null;
		$admin = Admin::where('id', $adminId)->first();
		
        $rules = AdminRoleRule::where('role', $request->role)
			->pluck('rule_id');
			
		return ApiResponse::success([
            'rules' => $rules,
        ]);
	}

	/**
	 * 获取router_key的集合
	 */
	public function getRoleRouterKeys(Request $request)
	{
		// 从中间件获取的用户ID
        $adminId = $request->admin_id_from_token ?? null;
		$admin = Admin::where('id', $adminId)->first();

		$ruleIds = AdminRoleRule::where('role', $admin->role)
			->pluck('rule_id');

		$routerKeys = AdminPrivilegeRule::whereIn('id', $ruleIds)
			->whereNotNull('router_key')
			->pluck('router_key');

		return ApiResponse::success([
            'routerKeys' => $routerKeys,
        ]);
	}

	/**
	 * 更新权限
	 */	
	public function updateRoleRules(Request $request)
	{
		$role = $request->input('role', []);
		$ruleIds = $request->input('ruleIds', []);

		DB::transaction(function () use ($role, $ruleIds) {
			AdminRoleRule::where('role', $role)->delete();

			$data = array_map(fn($id) => ['role' => $role, 'rule_id' => $id], $ruleIds);
			if (!empty($data)) {
				AdminRoleRule::insert($data);
			}
		});

		return ApiResponse::success([]);
	}
	
	/**
     * 获取全部角色信息
     */
    public function getRoleList(Request $request)
    {
        // 构建查询
        $query = AdminRole::query()
            ->where('status', '!=', BusinessDef::ADMIN_ROLE_STATUS_DELETED)
            ->orderBy('created_at', 'asc');


        // 分页
        $adminRoles = $query->get();

        return ApiResponse::success([
            'roles' => $adminRoles,  // 当前页的用户列表
        ]);
	}
	
	/**
     * 分页获取角色信息
     */
    public function getRoleByPage(Request $request)
    {
        // 获取分页参数
        $page = $request->input('page', 1);  // 当前页，默认是第1页
        $pageSize = $request->input('page_size', 10);  // 每页显示的记录数，默认是10条

        $role = $request->input('role', '');  // 角色，默认空字符串

        // 构建查询
        $query = AdminRole::query()
			->where('status', '!=', BusinessDef::ADMIN_ROLE_STATUS_DELETED)
			->orderBy('created_at', 'desc');

        // 如果有传入 role 参数，按角色过滤
        if ($role) {
            $query->where('role', $role);
        }

        // 获取符合条件的用户总数
        $totalCount = $query->count();

        // 分页
        $roles = $query->skip(($page - 1) * $pageSize)  // 计算分页的偏移量
                    ->take($pageSize)  // 每页获取指定数量的用户
                    ->get();

        return ApiResponse::success([
            'total' => $totalCount,  // 总记录数
            'current_page' => $page,  // 当前页
            'page_size' => $pageSize,  // 每页记录数
            'roles' => $roles,  // 当前页的用户列表
        ]);
	}
	
	/**
     * 更新角色信息
     */
    public function updateOtherRole(Request $request)
    {
        // 获取传入的更新参数
        $validated = $request->validate([
            'role' => 'required',
            'status' => 'int'
        ]);

        $otherRole = AdminRole::where('role', $request->role)->first();

        if (!$otherRole) {
            return ApiResponse::error(ApiCode::ADMIN_ROLE_NOT_FOUND);
        }

        // 被封禁，需要发推送强制退出
        if (isset($validated['status']) && $validated['status'] === BusinessDef::ADMIN_ROLE_STATUS_INACTIVE) {
            event(new AdminRoleStatusChanged(
                $request->role,
            ));
        }

        // 保存更新
        $otherRole->update($validated);

        return ApiResponse::success([
            'other_role' => $otherRole
        ]);
    }

    /**
     * 新建角色
     */
    public function createRole(Request $request)
    {
        // 获取传入的更新参数
        $validated = $request->validate([
			'role' => 'string',
			'name' => 'string',
			'remark' => 'nullable|string',
        ]);

        $existingRole = AdminRole::where('role', $request->role)
            ->where('status', '!=', BusinessDef::ADMIN_ROLE_STATUS_DELETED)
            ->first();
        if ($existingRole) {
            return ApiResponse::error(ApiCode::ADMIN_ROLE_ALREADY_EXIST);
        }

        $newRole = AdminRole::create([
			'role' => $request->role,
			'name' => $request->name,
			'remark' => $request->remark,
        ]);

        return ApiResponse::success([
            'role' => $newRole
        ]);
    }
}
