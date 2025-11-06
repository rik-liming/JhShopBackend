<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Helpers\ApiResponse;
use App\Enums\ApiCode;
use App\Models\AdminRoleRule;
use App\Models\AdminPrivilegeRule;
use App\Models\Admin;

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
}
