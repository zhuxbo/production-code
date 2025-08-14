<?php

use App\Http\Controllers\User\AuthController;
use App\Http\Controllers\User\CertController;
use App\Http\Controllers\User\ContactController;
use App\Http\Controllers\User\DashboardController;
use App\Http\Controllers\User\FundController;
use App\Http\Controllers\User\InvoiceController;
use App\Http\Controllers\User\InvoiceLimitController;
use App\Http\Controllers\User\OrderController;
use App\Http\Controllers\User\OrganizationController;
use App\Http\Controllers\User\ProductController;
use App\Http\Controllers\User\SettingController;
use App\Http\Controllers\User\TopUpController;
use App\Http\Controllers\User\TransactionController;
use App\Http\Controllers\User\VerifyCodeController;
use App\Utils\RouteHelper;
use Illuminate\Support\Facades\Route;

// 无需认证
Route::middleware('login.limiter:user')->post('login', [AuthController::class, 'login']);
Route::post('register', [AuthController::class, 'register']);
Route::post('reset-password', [AuthController::class, 'resetPassword']);

// 发送邮箱验证码 无需认证
Route::post('send-email-code', [VerifyCodeController::class, 'sendEmail']);

// 刷新Token
Route::middleware('api.user.refresh')->group(function () {
    Route::post('refresh-token', [AuthController::class, 'refreshToken']);
});

// 需要认证的用户接口
Route::middleware('api.user')->group(function () {
    Route::get('me', [AuthController::class, 'me']);
    Route::patch('update-username', [AuthController::class, 'updateUsername']);
    Route::patch('update-password', [AuthController::class, 'updatePassword']);
    Route::patch('bind-email', [AuthController::class, 'bindEmail']);
    Route::patch('bind-mobile', [AuthController::class, 'bindMobile']);
    Route::delete('logout', [AuthController::class, 'logout']);
    // 发送短信验证码 需要认证
    Route::post('send-sms-code', [VerifyCodeController::class, 'sendSms']);
});

// 首页统计数据路由
Route::prefix('dashboard')->middleware('api.user')->group(function () {
    Route::get('overview', [DashboardController::class, 'overview']);
    Route::get('assets', [DashboardController::class, 'assets']);
    Route::get('orders', [DashboardController::class, 'orders']);
    Route::get('trend', [DashboardController::class, 'trend']);
    Route::get('monthly-comparison', [DashboardController::class, 'monthlyComparison']);
});

// 订单路由
Route::prefix('order')->middleware('api.user')->group(function () {
    Route::get('/', [OrderController::class, 'index']);
    Route::get('{id}', [OrderController::class, 'show'])->where('id', '[0-9]+');
    Route::get('batch', [OrderController::class, 'batchShow']);
    Route::post('new', [OrderController::class, 'new']);
    Route::post('batch-new', [OrderController::class, 'batchNew']);
    Route::post('renew', [OrderController::class, 'renew']);
    Route::post('reissue', [OrderController::class, 'reissue']);
    Route::post('pay/{id}', [OrderController::class, 'pay'])->where('id', '[0-9]+');
    Route::post('commit/{id}', [OrderController::class, 'commit'])->where('id', '[0-9]+');
    Route::post('revalidate/{id}', [OrderController::class, 'revalidate'])->where('id', '[0-9]+');
    Route::post('update-dcv/{id}', [OrderController::class, 'updateDCV'])->where('id', '[0-9]+');
    Route::post('remove-mdc-domain/{id}', [OrderController::class, 'removeMdcDomain'])->where('id', '[0-9]+');
    Route::post('sync/{id}', [OrderController::class, 'sync'])->where('id', '[0-9]+');
    Route::post('commit-cancel/{id}', [OrderController::class, 'commitCancel'])->where('id', '[0-9]+');
    Route::post('revoke-cancel/{id}', [OrderController::class, 'revokeCancel'])->where('id', '[0-9]+');
    Route::post('remark/{id}', [OrderController::class, 'remark'])->where('id', '[0-9]+');
    Route::get('download', [OrderController::class, 'download']);
    Route::get('download-validate-file/{id}', [OrderController::class, 'downloadValidateFile'])->where('id', '[0-9]+');
    Route::get('send-active/{id}', [OrderController::class, 'sendActive'])->where('id', '[0-9]+');
    Route::get('send-expire/{userId}', [OrderController::class, 'sendExpire'])->where('userId', '[0-9]+');
    Route::post('batch-pay', [OrderController::class, 'batchPay']);
    Route::post('batch-commit', [OrderController::class, 'batchCommit']);
    Route::post('batch-revalidate', [OrderController::class, 'batchRevalidate']);
    Route::post('batch-sync', [OrderController::class, 'batchSync']);
    Route::post('batch-commit-cancel', [OrderController::class, 'batchCommitCancel']);
    Route::post('batch-revoke-cancel', [OrderController::class, 'batchRevokeCancel']);
});

// 证书路由
Route::prefix('cert')->middleware('api.user')->group(function () {
    Route::get('/', [CertController::class, 'index']);
    Route::get('{id}', [CertController::class, 'show'])->where('id', '[0-9]+');
    Route::get('batch', [CertController::class, 'batchShow']);
});

// 联系人路由
Route::prefix('contact')->middleware('api.user')->group(function () {
    RouteHelper::registerResourceRoutes('', ContactController::class);
});

// 资金管理路由
Route::prefix('fund')->middleware('api.user')->group(function () {
    Route::get('/', [FundController::class, 'index']);
    Route::post('check/{id}', [FundController::class, 'check'])->where('id', '[0-9]+');
    Route::post('platform-recharge', [FundController::class, 'platformRecharge']);
});

// 发票路由
Route::prefix('invoice')->middleware('api.user')->group(function () {
    RouteHelper::registerResourceRoutes('', InvoiceController::class);
});

// 发票额度路由
Route::prefix('invoice-limit')->middleware('api.user')->group(function () {
    Route::get('/', [InvoiceLimitController::class, 'index']);
});

// 组织路由
Route::prefix('organization')->middleware('api.user')->group(function () {
    RouteHelper::registerResourceRoutes('', OrganizationController::class);
});

// 产品路由
Route::prefix('product')->middleware('api.user')->group(function () {
    Route::get('/', [ProductController::class, 'index']);
    Route::get('{id}', [ProductController::class, 'show'])->where('id', '[0-9]+');
});

// 交易记录路由
Route::prefix('transaction')->middleware('api.user')->group(function () {
    Route::get('/', [TransactionController::class, 'index']);
});

// 设置路由
Route::prefix('setting')->middleware('api.user')->group(function () {
    Route::get('api-token', [SettingController::class, 'getApiToken']);
    Route::put('api-token', [SettingController::class, 'updateApiToken']);
    Route::get('callback', [SettingController::class, 'getCallback']);
    Route::put('callback', [SettingController::class, 'updateCallback']);
});

// 支付宝支付
Route::prefix('top-up')->middleware('api.user')->group(function () {
    Route::post('alipay', [TopUpController::class, 'alipay']);
    Route::post('wechat', [TopUpController::class, 'wechat']);
    Route::get('check/{id}', [TopUpController::class, 'check'])->where('id', '[0-9]+');
    Route::get('get-bank-account', [TopUpController::class, 'getBankAccount']);
    // 清除支付配置缓存
    Route::get('clear-pay-config', [TopUpController::class, 'clearConfigCache']);
});
