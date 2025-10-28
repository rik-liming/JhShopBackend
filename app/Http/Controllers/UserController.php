<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\ApiResponse;
use App\Enums\ApiCode;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\UserAccount;

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
            'invite_code',
        )->find($userId);

        $userAccount = UserAccount::select(
            'total_balance',
            'available_balance',
        )
        ->where('user_id', $userId)
        ->first();

        User::where('email', $request->email)->first();

        if (!$user) {
            return ApiResponse::error(ApiCode::USER_NOT_FOUND);
        }

        return ApiResponse::success([
            'user' => $user,
            'account' => $userAccount,
        ]);
    }

    /**
     * 获取当前用户账户信息
     */
    public function getAccountInfo(Request $request)
    {
        // 从中间件获取的用户ID
        $userId = $request->user_id_from_token ?? null;

        if (!$userId) {
            return ApiResponse::error(ApiCode::USER_NOT_FOUND);
        }

        $userAccount = UserAccount::select(
            'total_balance',
            'available_balance',
        )
        ->where('user_id', $userId)
        ->first();

        return ApiResponse::success([
            'account' => $userAccount,
        ]);
    }

    public function autoBuyerVerify(Request $request)
    {
        $userId = $request->auto_buyer_id ?? null;

        if (!$userId) {
            return ApiResponse::error(ApiCode::USER_AUTO_BUYER_VERIFY_FAIL);
        }

        $autoBuyer = User::where('id', $userId)
            ->where('role', 'autoBuyer')
            ->where('status', 1)
            ->first();

        if (!$autoBuyer) {
            return ApiResponse::error(ApiCode::USER_AUTO_BUYER_VERIFY_FAIL);
        }

        return ApiResponse::success([]);
    }

    /**
     * 更新用户密码
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'type' => 'required|in:login,payment',
            'password' => 'required|min:6',
        ], [
            'type.required' => '密码类型不能为空',
            'password.required' => '密码不能为空',
            'password.min' => '密码长度不能少于6位',
        ]);

        // 获取传入的更新参数
        $type = $request->type;
        $password = $request->password;

        // 从中间件获取的用户ID
        $userId = $request->user_id_from_token ?? null;

        if (!$userId) {
            return ApiResponse::error(ApiCode::USER_NOT_FOUND);
        }

        $user = User::where('id', $userId)->first();
        if (!$user) {
            return ApiResponse::error(ApiCode::USER_NOT_FOUND);
        }

        $userAccount = UserAccount::where('user_id', $userId)->first();
        if (!$userAccount) {
            return ApiResponse::error(ApiCode::USER_ACCOUNT_NOT_FOUND);
        }

        if ($type == 'login') {
            $user->password = Hash::make($password);
            $user->save();
        } else if ($type == 'payment') {
            $userAccount->payment_password = Hash::make($password);
            $userAccount->save();
        }

        return ApiResponse::success();
    }
}
