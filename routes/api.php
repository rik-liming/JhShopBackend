<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\OrderListingController;

use App\Http\Controllers\AdminController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/verify_otp', [AuthController::class, 'verifyOtp']);
Route::post('/logout', [AuthController::class, 'logout']);

Route::middleware(['check.api.token'])->group(function () {
    Route::get('/user/info', [UserController::class, 'getUserInfo']);

    Route::post('/order-listing', [OrderListingController::class, 'createOrderListing']);
    Route::get('/order-listing/page', [OrderListingController::class, 'getOrderListingByPage']);
});

Route::post('/admin/login', [AdminController::class, 'login']);
Route::post('/admin/verify_otp', [AdminController::class, 'verifyOtp']);
Route::post('/admin/logout', [AdminController::class, 'logout']);

Route::middleware(['check.admin.token'])->group(function () {
    Route::get('/admin/user/page', [AdminController::class, 'getUserByPage']);
    Route::put('/admin/user', [AdminController::class, 'updateUser']);
});

// Route::middleware('auth:sanctum')->group(function () {
//     Route::get('/permissions', [PermissionController::class, 'getPermissions']);

//     // 受权限保护的路由
//     Route::get('/admin/dashboard', function () {
//         return response()->json(['message' => '欢迎访问后台']);
//     })->middleware('permission:index');
// });
