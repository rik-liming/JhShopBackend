<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PermissionController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/verify_otp', [AuthController::class, 'verifyOtp']);

Route::middleware(['check.api.token'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/user/info', [UserController::class, 'getUserInfo']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/permissions', [PermissionController::class, 'getPermissions']);

    // 受权限保护的路由
    Route::get('/admin/dashboard', function () {
        return response()->json(['message' => '欢迎访问后台']);
    })->middleware('permission:index');
});
