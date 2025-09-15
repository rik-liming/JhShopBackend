<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckPermission
{
    public function handle(Request $request, Closure $next, $permissionKey)
    {
        $user = $request->user();
        $role = $user->role;

        if (!$role->rules()->where('router_key', $permissionKey)->exists()) {
            return response()->json(['message' => '无访问权限'], 403);
        }

        return $next($request);
    }
}
