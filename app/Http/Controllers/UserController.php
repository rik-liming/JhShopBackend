<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Helpers\ApiResponse;
use App\Enums\ApiCode;

class UserController extends Controller
{
    /**
     * 获取当前用户信息
     */
    public function getUserInfo(Request $request)
    {
        // 从中间件获取的用户ID
        $userId = $request->user_id_from_token ?? null;

        if (!$userId) {
            return ApiResponse::error(ApiCode::USER_NOT_FOUND);
        }

        $user = User::select(
            'id',
            'user_name',
            'real_name',
            'email',
            'avatar',
            'role',
        )->find($userId);

        if (!$user) {
            return ApiResponse::error(ApiCode::USER_NOT_FOUND);
        }

        return ApiResponse::success([
            'user' => $user,
        ]);
    }
}
