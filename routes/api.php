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
use App\Http\Controllers\MessageController;

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AdminRechargeController;
use App\Http\Controllers\AdminWithdrawController;
use App\Http\Controllers\AdminTransferController;
use App\Http\Controllers\AdminOrderListingController;
use App\Http\Controllers\AdminStatController;
use App\Http\Controllers\AdminOrderController;
use App\Http\Controllers\AdminConfigController;
use App\Http\Controllers\AdminReportController;
use App\Http\Controllers\AdminMessageController;
use App\Http\Controllers\AdminPrivilegeController;
use App\Http\Controllers\AdminRoleController;
use App\Http\Controllers\AdminReddotController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/verify_otp', [AuthController::class, 'verifyOtp']);
Route::post('/logout', [AuthController::class, 'logout']);
Route::get('/config/info', [ConfigController::class, 'getConfigInfo']);
Route::get('/user/auto_buyer/verify', [UserController::class, 'autoBuyerVerify']);
Route::post('/order/auto_buyer', [OrderController::class, 'autoBuyerCreate']);
Route::get('/order/auto_buyer/detail', [OrderController::class, 'getOrderDetail']);
Route::post('/order/auto_buyer/confirm', [OrderController::class, 'autoBuyerConfirm']);
// Route::get('/message/test', [MessageController::class, 'test']);

Route::middleware(['check.api.token'])->group(function () {
    Route::get('/user/info', [UserController::class, 'getUserInfo']);
    Route::get('/user/account/info', [UserController::class, 'getAccountInfo']);
    Route::put('/user/password', [UserController::class, 'updatePassword']);

    Route::get('/payment_method/my', [PaymentMethodController::class, 'getMyList']);
    Route::get('/payment_method', [PaymentMethodController::class, 'getInfo']);
    Route::post('/payment_method', [PaymentMethodController::class, 'create']);
    Route::post('/payment_method/update', [PaymentMethodController::class, 'update']);
    Route::delete('/payment_method', [PaymentMethodController::class, 'delete']);
    Route::post('/payment_method/default', [PaymentMethodController::class, 'setDefault']);

    Route::post('/order_listing', [OrderListingController::class, 'createOrderListing']);
    Route::get('/order_listing/page', [OrderListingController::class, 'getOrderListingByPage']);
    Route::get('/order_listing', [OrderListingController::class, 'getOrderListing']);
    Route::get('/order_listing/my', [OrderListingController::class, 'getMyOrderListing']);
    Route::post('/order_listing/cancel', [OrderListingController::class, 'cancelOrderListing']);

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

    Route::get('/message/list', [MessageController::class, 'getList']);
    Route::get('/message/unread', [MessageController::class, 'getUnreadCount']);
    Route::post('/message/markread', [MessageController::class, 'markAsRead']);
});

Route::post('/admin/login', [AdminController::class, 'login']);
Route::post('/admin/verify_otp', [AdminController::class, 'verifyOtp']);
Route::post('/admin/logout', [AdminController::class, 'logout']);

Route::middleware(['check.admin.token'])->group(function () {
    Route::get('/admin/user/page', [AdminUserController::class, 'getUserByPage']);
    Route::put('/admin/user', [AdminUserController::class, 'updateUser']);
    Route::get('/admin/user/invite_relation', [AdminUserController::class, 'getUserInviteRelation']);
    Route::put('/admin/user/account', [AdminUserController::class, 'updateAccountInfo']);
    Route::get('/admin/user/account', [AdminUserController::class, 'getAccountInfo']);
    Route::get('/admin/user/password', [AdminUserController::class, 'getPasswordInfo']);
    Route::put('/admin/user/password', [AdminUserController::class, 'updatePasswordInfo']);
    Route::put('/admin/user/role', [AdminUserController::class, 'updateRole']);
    Route::put('/admin/user/commission', [AdminUserController::class, 'updateCommissionSwitch']);

    Route::get('/admin/recharge/page', [AdminRechargeController::class, 'getRechargeByPage']);
    Route::put('/admin/recharge', [AdminRechargeController::class, 'updateRecharge']);

    Route::get('/admin/withdraw/page', [AdminWithdrawController::class, 'getWithdrawByPage']);
    Route::put('/admin/withdraw', [AdminWithdrawController::class, 'updateWithdraw']);

    Route::get('/admin/transfer/page', [AdminTransferController::class, 'getTransferByPage']);
    Route::put('/admin/transfer', [AdminTransferController::class, 'updateTransfer']);

    Route::get('/admin/order_listing/page', [AdminOrderListingController::class, 'getOrderListingByPage']);
    Route::put('/admin/order_listing', [AdminOrderListingController::class, 'updateOrderListing']);

    Route::get('/admin/order/page', [AdminOrderController::class, 'getOrderByPage']);
    Route::post('/admin/order/judge', [AdminOrderController::class, 'orderJudge']);

    Route::get('/admin/stat/dashboard', [AdminStatController::class, 'getDashboard']);

    Route::get('/admin/config/info', [AdminConfigController::class, 'getConfigInfo']);
    Route::put('/admin/config', [AdminConfigController::class, 'updateConfig']);

    Route::get('/admin/info', [AdminController::class, 'getAdminInfo']);
    Route::post('/admin', [AdminController::class, 'createAdmin']);
    Route::put('/admin', [AdminController::class, 'updateAdmin']);
    Route::put('/admin/other', [AdminController::class, 'updateOtherAdmin']);
    Route::post('/admin/secret/regen', [AdminController::class, 'regenSecret']);
    Route::get('/admin/page', [AdminController::class, 'getAdminByPage']);
    Route::get('/admin/password', [AdminController::class, 'getPasswordInfo']);
    Route::put('/admin/password', [AdminController::class, 'updatePasswordInfo']);

    Route::get('/admin/report/list', [AdminReportController::class, 'getReportByTime']);
    Route::post('/admin/report/daily', [AdminReportController::class, 'generateTodayReport']);

    Route::get('/admin/message/list', [AdminMessageController::class, 'getList']);
    Route::get('/admin/message/unread', [AdminMessageController::class, 'getUnreadCount']);
    Route::post('/admin/message/markread', [AdminMessageController::class, 'markAsRead']);

    Route::get('/admin/privilege/tree', [AdminPrivilegeController::class, 'tree']);
    Route::get('/admin/role/rules', [AdminRoleController::class, 'getRoleRules']);
    Route::post('/admin/role/rules', [AdminRoleController::class, 'updateRoleRules']);
    Route::get('/admin/role/router_keys', [AdminRoleController::class, 'getRoleRouterKeys']);
    Route::get('/admin/role/list', [AdminRoleController::class, 'getRoleList']);
    Route::get('/admin/role/page', [AdminRoleController::class, 'getRoleByPage']);
    Route::put('/admin/role/other', [AdminRoleController::class, 'updateOtherRole']);
    Route::post('/admin/role', [AdminRoleController::class, 'createRole']);

    Route::get('/admin/reddot', [AdminReddotController::class, 'getReddot']);
});
