<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\AdminRoleRule;

class AdminRoleController extends Controller
{
	/**
	 * 获取rule id的集合
	 */
    public function getRoleRules($role)
    {
        $rules = AdminRoleRule::where('role', $role)
            ->pluck('rule_id');

        return response()->json($rules);
	}

	/**
	 * 获取router_key的集合
	 */
	public function getRoleRouterKeys($role)
	{
		$ruleIds = AdminRoleRule::where('role', $role)
			->pluck('rule_id');

		$routerKeys = AdminPrivilegeRule::whereIn('id', $ruleIds)
			->whereNotNull('router_key')
			->pluck('router_key');

		return response()->json($routerKeys);
	}

	/**
	 * 更新权限
	 */	
	public function updateRoleRules(Request $request, $role)
	{
		$ruleIds = $request->input('ruleIds', []);

		DB::transaction(function () use ($role, $ruleIds) {
			AdminRoleRule::where('role', $role)->delete();

			$data = array_map(fn($id) => ['role' => $role, 'rule_id' => $id], $ruleIds);
			if (!empty($data)) {
				AdminRoleRule::insert($data);
			}
		});

		return response()->json(['message' => '权限更新成功']);
	}
}
