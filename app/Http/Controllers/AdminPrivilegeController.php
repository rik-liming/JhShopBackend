<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AdminPrivilegeRule;
use App\Helpers\ApiResponse;
use App\Enums\ApiCode;

class AdminPrivilegeController extends Controller
{
    public function tree()
    {
        $rules = AdminPrivilegeRule::where('status', 1)
            ->orderBy('sort_order', 'asc')
            ->get();

        return ApiResponse::success([
            'tree' => $this->buildTree($rules),
        ]);
    }

    private function buildTree($rules, $pid = 0)
    {
        $branch = [];
        foreach ($rules as $rule) {
            if ($rule->pid == $pid) {
                $children = $this->buildTree($rules, $rule->id);
                $item = [
                    'id' => $rule->id,
                    'name' => $rule->name,
                    'type' => $rule->type,
                    'router_key' => $rule->router_key,
                    'children' => $children,
                ];
                $branch[] = $item;
            }
        }
        return $branch;
    }
}
