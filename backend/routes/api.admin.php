<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AgisoController;
use App\Http\Controllers\Admin\ApiTokenController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CallbackController;
use App\Http\Controllers\Admin\CertController;
use App\Http\Controllers\Admin\ChainController;
use App\Http\Controllers\Admin\ContactController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FreeCertQuotaController;
use App\Http\Controllers\Admin\FundController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\InvoiceLimitController;
use App\Http\Controllers\Admin\LogsController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\OrganizationController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductPriceController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SettingGroupController;
use App\Http\Controllers\Admin\TaskController;
use App\Http\Controllers\Admin\TransactionController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserLevelController;
use App\Utils\RouteHelper;
use Illuminate\Support\Facades\Route;

// 无需认证
Route::prefix('admin')->group(function () {
    Route::middleware('login.limiter:admin')->post('login', [AuthController::class, 'login']);
});

// 刷新Token
Route::prefix('admin')->middleware('api.admin.refresh')->group(function () {
    Route::post('refresh-token', [AuthController::class, 'refreshToken']);
});

// 需要认证的路由
Route::prefix('admin')->middleware('api.admin')->group(function () {
    Route::get('me', [AuthController::class, 'me']);
    Route::patch('update-profile', [AuthController::class, 'updateProfile']);
    Route::patch('update-password', [AuthController::class, 'updatePassword']);
    Route::delete('logout', [AuthController::class, 'logout']);

    // 管理端首页统计数据路由
    Route::prefix('dashboard')->group(function () {
        Route::get('overview', [DashboardController::class, 'overview']);
        Route::get('system-overview', [DashboardController::class, 'systemOverview']);
        Route::get('realtime', [DashboardController::class, 'realtime']);
        Route::get('trends', [DashboardController::class, 'trends']);
        Route::get('top-products', [DashboardController::class, 'topProducts']);
        Route::get('brand-stats', [DashboardController::class, 'brandStats']);
        Route::get('user-level-distribution', [DashboardController::class, 'userLevelDistribution']);
        Route::get('health-status', [DashboardController::class, 'healthStatus']);
        Route::post('clear-cache', [DashboardController::class, 'clearCache']);
    });

    // 使用工具类注册标准资源路由
    RouteHelper::registerResourceRoutes('admin', AdminController::class);
    RouteHelper::registerResourceRoutes('user', UserController::class);
    Route::prefix('user')->group(function () {
        Route::post('direct-login', [UserController::class, 'directLogin']);
        Route::post('create-user', [UserController::class, 'createUser']);
    });
    RouteHelper::registerResourceRoutes('user-level', UserLevelController::class);
    Route::prefix('user-level')->group(function () {
        Route::get('batch-codes', [UserLevelController::class, 'batchShowInCodes']);
    });

    // 资源路由
    RouteHelper::registerResourceRoutes('chain', ChainController::class);
    RouteHelper::registerResourceRoutes('contact', ContactController::class);
    RouteHelper::registerResourceRoutes('organization', OrganizationController::class);
    RouteHelper::registerResourceRoutes('callback', CallbackController::class);
    RouteHelper::registerResourceRoutes('api-token', ApiTokenController::class);
    RouteHelper::registerResourceRoutes('product', ProductController::class);
    Route::prefix('product')->group(function () {
        Route::post('import', [ProductController::class, 'import']);
        Route::get('cost/{id}', [ProductController::class, 'getCost'])->where('id', '[0-9]+');
        Route::patch('cost/{id}', [ProductController::class, 'updateCost'])->where('id', '[0-9]+');
        Route::get('source', [ProductController::class, 'getSourceList']);
        Route::post('export', [ProductController::class, 'export']);
    });
    RouteHelper::registerResourceRoutes('product-price', ProductPriceController::class);
    Route::prefix('product-price')->group(function () {
        Route::get('get', [ProductPriceController::class, 'get']);
        Route::put('set', [ProductPriceController::class, 'set']);
        Route::get('export', [ProductPriceController::class, 'export']);
    });

    // 订单路由
    Route::prefix('order')->group(function () {
        Route::get('/', [OrderController::class, 'index']);
        Route::get('{id}', [OrderController::class, 'show'])->where('id', '[0-9]+');
        Route::get('batch', [OrderController::class, 'batchShow']);
        Route::post('new', [OrderController::class, 'new']);
        Route::post('batch-new', [OrderController::class, 'batchNew']);
        Route::post('renew', [OrderController::class, 'renew']);
        Route::post('reissue', [OrderController::class, 'reissue']);
        Route::post('transfer', [OrderController::class, 'transfer']);
        Route::post('input', [OrderController::class, 'input']);
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
    Route::prefix('cert')->group(function () {
        Route::get('/', [CertController::class, 'index']);
        Route::get('{id}', [CertController::class, 'show'])->where('id', '[0-9]+');
        Route::get('batch', [CertController::class, 'batchShow']);
    });

    // 免费证书配额路由
    Route::prefix('free-cert-quota')->group(function () {
        Route::get('/', [FreeCertQuotaController::class, 'index']);
        Route::post('/', [FreeCertQuotaController::class, 'store']);
    });

    // 任务管理
    Route::prefix('task')->group(function () {
        Route::get('/', [TaskController::class, 'index']);
        Route::get('/{id}', [TaskController::class, 'show'])->where('id', '[0-9]+');
        Route::delete('/{id}', [TaskController::class, 'destroy'])->where('id', '[0-9]+');
        Route::delete('/batch', [TaskController::class, 'batchDestroy']);
        Route::post('/batch-start', [TaskController::class, 'batchStart']);
        Route::post('/batch-stop', [TaskController::class, 'batchStop']);
        Route::post('/batch-execute', [TaskController::class, 'batchExecute']);
    });

    // 财务管理路由
    RouteHelper::registerResourceRoutes('invoice', InvoiceController::class);
    RouteHelper::registerResourceRoutes('fund', FundController::class);
    Route::prefix('fund')->group(function () {
        Route::post('reverse/{id}', [FundController::class, 'reverse'])->where('id', '[0-9]+');
        Route::post('refunds/{id}', [FundController::class, 'refunds'])->where('id', '[0-9]+');
        Route::post('check/{id}', [FundController::class, 'check'])->where('id', '[0-9]+');
    });
    Route::get('transaction', [TransactionController::class, 'index']);
    Route::get('invoice-limit', [InvoiceLimitController::class, 'index']);

    // 阿奇索路由
    Route::prefix('agiso')->group(function () {
        Route::get('/', [AgisoController::class, 'index']);
        Route::get('{id}', [AgisoController::class, 'show'])->where('id', '[0-9]+');
        Route::delete('{id}', [AgisoController::class, 'destroy'])->where('id', '[0-9]+');
        Route::delete('/', [AgisoController::class, 'batchDestroy']);
    });

    // 设置组和设置项路由
    RouteHelper::registerResourceRoutes('setting-group', SettingGroupController::class);
    RouteHelper::registerResourceRoutes('setting', SettingController::class);
    Route::prefix('setting')->group(function () {
        Route::get('group/{groupId}', [SettingController::class, 'getByGroup']);
        Route::get('config', [SettingController::class, 'getConfig']);
        Route::put('batch-update', [SettingController::class, 'batchUpdate']);
    });

    // 日志路由
    Route::prefix('logs')->group(function () {
        Route::get('{type}/{id}', [LogsController::class, 'get'])
            ->where('type', 'admin|user|api|callback|easy|ca|error')
            ->where('id', '[0-9]+');
        Route::get('admin', [LogsController::class, 'admin']);
        Route::get('user', [LogsController::class, 'user']);
        Route::get('api', [LogsController::class, 'api']);
        Route::get('callback', [LogsController::class, 'callback']);
        Route::get('easy', [LogsController::class, 'easy']);
        Route::get('ca', [LogsController::class, 'ca']);
        Route::get('error', [LogsController::class, 'errors']);
    });
});
