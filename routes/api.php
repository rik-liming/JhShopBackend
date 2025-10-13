<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\OrderListingController;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\RechargeController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\WithdrawController;

use App\Http\Controllers\AdminController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/verify_otp', [AuthController::class, 'verifyOtp']);
Route::post('/logout', [AuthController::class, 'logout']);

Route::middleware(['check.api.token'])->group(function () {
    Route::get('/user/info', [UserController::class, 'getUserInfo']);

    Route::post('/order_listing', [OrderListingController::class, 'createOrderListing']);
    Route::get('/order_listing/page', [OrderListingController::class, 'getOrderListingByPage']);
    Route::get('/order_listing', [OrderListingController::class, 'getOrderListing']);

    Route::get('/config/info', [ConfigController::class, 'getConfigInfo']);

    Route::post('/order', [OrderController::class, 'create']);
    Route::get('/order/buyer/my', [OrderController::class, 'getMyBuyerOrders']);
    Route::get('/order/seller/my', [OrderController::class, 'getMySellerOrders']);
    Route::get('/order/detail', [OrderController::class, 'getOrderDetail']);
    Route::post('/order/confirm', [OrderController::class, 'orderConfirm']);

    Route::post('/recharge', [RechargeController::class, 'createRecharge']);
    Route::get('/recharge/detail', [RechargeController::class, 'getRechargeByTranaction']);
    Route::post('/transfer', [TransferController::class, 'createTransfer']);
    Route::get('/transfer/detail', [TransferController::class, 'getTransferByTranaction']);
    Route::post('/withdraw', [WithdrawController::class, 'createWithdraw']);
    Route::get('/withdraw/detail', [WithdrawController::class, 'getWithdrawByTranaction']);
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
