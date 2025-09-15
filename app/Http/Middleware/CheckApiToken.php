<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Redis;
use App\Helpers\ApiResponse;
use App\Enums\ApiCode;

class CheckApiToken
{
    public function handle($request, Closure $next)
    {
        $token = $request->bearerToken();

        if (!$token || !Redis::exists("login:token:$token")) {
            return ApiResponse::error(ApiCode::LOGIN_TOKEN_INVALID);
        }

        // 取出用户 ID
        $userId = Redis::get("login:token:$token");

        // 滑动续期 10 分钟
        Redis::expire("login:token:$token", 600);

        // 将 userId 放到 request 中，方便后续使用
        $request->merge(['user_id_from_token' => $userId]);

        return $next($request);
    }
}
