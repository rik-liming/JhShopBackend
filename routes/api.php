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
use App\Http\Controllers\FinancialRecordController;
use App\Http\Controllers\PaymentMethodController;

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AdminRechargeController;
use App\Http\Controllers\AdminWithdrawController;
use App\Http\Controllers\AdminTransferController;
use App\Http\Controllers\AdminOrderListingController;
use App\Http\Controllers\AdminStatController;
use App\Http\Controllers\AdminOrderController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/verify_otp', [AuthController::class, 'verifyOtp']);
Route::post('/logout', [AuthController::class, 'logout']);
Route::get('/config/info', [ConfigController::class, 'getConfigInfo']);
Route::get('/user/auto_buyer/verify', [UserController::class, 'autoBuyerVerify']);
Route::post('/order/auto_buyer', [OrderController::class, 'autoBuyerCreate']);
Route::get('/order/auto_buyer/detail', [OrderController::class, 'getOrderDetail']);
Route::post('/order/auto_buyer/confirm', [OrderController::class, 'autoBuyerOrderConfirm']);

Route::middleware(['check.api.token'])->group(function () {
    Route::get('/user/info', [UserController::class, 'getUserInfo']);
    Route::get('/user/account/info', [UserController::class, 'getAccountInfo']);
    Route::put('/user/password', [UserController::class, 'updatePassword']);

    Route::get('/payment_method/my', [PaymentMethodController::class, 'getMyList']);
    Route::get('/payment_method', [PaymentMethodController::class, 'getInfo']);
    Route::post('/payment_method', [PaymentMethodController::class, 'create']);
    Route::post('/payment_method/update', [PaymentMethodController::class, 'update']);
    Route::delete('/payment_method', [PaymentMethodController::class, 'delete']);

    Route::post('/order_listing', [OrderListingController::class, 'createOrderListing']);
    Route::get('/order_listing/page', [OrderListingController::class, 'getOrderListingByPage']);
    Route::get('/order_listing', [OrderListingController::class, 'getOrderListing']);

    Route::post('/order', [OrderController::class, 'create']);
    Route::get('/order/buyer/my', [OrderController::class, 'getMyBuyerOrders']);
    Route::get('/order/seller/my', [OrderController::class, 'getMySellerOrders']);
    Route::get('/order/detail', [OrderController::class, 'getOrderDetail']);
    Route::post('/order/confirm', [OrderController::class, 'orderConfirm']);
    Route::get('/order/report/my', [OrderController::class, 'getMyOrderReport']);
    Route::get('/order/report/group', [OrderController::class, 'getGroupOrderReport']);

    Route::post('/recharge', [RechargeController::class, 'createRecharge']);
    Route::get('/recharge/detail', [RechargeController::class, 'getRechargeByTranaction']);
    Route::post('/transfer', [TransferController::class, 'createTransfer']);
    Route::get('/transfer/detail', [TransferController::class, 'getTransferByTranaction']);
    Route::post('/withdraw', [WithdrawController::class, 'createWithdraw']);
    Route::get('/withdraw/detail', [WithdrawController::class, 'getWithdrawByTranaction']);

    Route::get('/financial_record/my', [FinancialRecordController::class, 'getMyRecords']);

    
});

Route::post('/admin/login', [AdminController::class, 'login']);
Route::post('/admin/verify_otp', [AdminController::class, 'verifyOtp']);
Route::post('/admin/logout', [AdminController::class, 'logout']);

Route::middleware(['check.admin.token'])->group(function () {
    Route::get('/admin/user/page', [AdminUserController::class, 'getUserByPage']);
    Route::put('/admin/user', [AdminUserController::class, 'updateUser']);

    Route::get('/admin/recharge/page', [AdminRechargeController::class, 'getRechargeByPage']);
    Route::put('/admin/recharge', [AdminRechargeController::class, 'updateRecharge']);

    Route::get('/admin/withdraw/page', [AdminWithdrawController::class, 'getWithdrawByPage']);
    Route::put('/admin/withdraw', [AdminWithdrawController::class, 'updateWithdraw']);

    Route::get('/admin/transfer/page', [AdminTransferController::class, 'getTransferByPage']);
    Route::put('/admin/transfer', [AdminTransferController::class, 'updateTransfer']);

    Route::get('/admin/order_listing/page', [AdminOrderListingController::class, 'getOrderListingByPage']);
    Route::put('/admin/order_listing', [AdminOrderListingController::class, 'updateOrderListing']);

    Route::get('/admin/order/page', [AdminOrderController::class, 'getOrderByPage']);

    Route::get('/admin/stat/dashboard', [AdminStatController::class, 'getDashboard']);
});

// Route::middleware('auth:sanctum')->group(function () {
//     Route::get('/permissions', [PermissionController::class, 'getPermissions']);

//     // 受权限保护的路由
//     Route::get('/admin/dashboard', function () {
//         return response()->json(['message' => '欢迎访问后台']);
//     })->middleware('permission:index');
// });
