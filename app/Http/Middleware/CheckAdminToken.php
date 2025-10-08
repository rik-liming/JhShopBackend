<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Redis;
use App\Helpers\ApiResponse;
use App\Enums\ApiCode;

class CheckAdminToken
{
    public function handle($request, Closure $next)
    {
        $token = $request->bearerToken();

        if (!$token || !Redis::exists("admin:login:token:$token")) {
            return ApiResponse::error(ApiCode::LOGIN_TOKEN_INVALID);
        }

        // 取出admin ID
        $adminId = Redis::get("admin:login:token:$token");

        // 滑动续期 30 分钟
        Redis::expire("admin:login:token:$token", 3600);

        // 将 adminId 放到 request 中，方便后续使用
        $request->merge(['admin_id_from_token' => $adminId]);

        return $next($request);
    }
}
