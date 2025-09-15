<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function getPermissions(Request $request)
    {
        $user = $request->user();
        $role = $user->role;

        $permissions = $role->rules()->where('status', 1)->get();

        return response()->json([
            'menus' => $permissions->where('type', 'menu')->values(),
            'actions' => $permissions->where('type', 'action')->pluck('router_key')->values(),
        ]);
    }
}
