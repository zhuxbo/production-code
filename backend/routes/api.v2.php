<?php

use App\Http\Controllers\V2\IndexController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API V2 Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v2')->middleware('api.v2')->group(function () {
    Route::get('health', [IndexController::class, 'health']);
    Route::get('get-products', [IndexController::class, 'getProducts']);
    Route::get('get-orders', [IndexController::class, 'getOrders']);
    Route::post('new', [IndexController::class, 'new']);
    Route::post('renew', [IndexController::class, 'renew']);
    Route::post('reissue', [IndexController::class, 'reissue']);
    Route::get('get', [IndexController::class, 'get']);
    Route::get('get-order-id-by-refer-id', [IndexController::class, 'getOrderIdByReferId']);
    Route::post('cancel', [IndexController::class, 'cancel']);
    Route::post('revalidate', [IndexController::class, 'revalidate']);
    Route::post('update-dcv', [IndexController::class, 'updateDCV']);
    Route::post('remove-mdc-domain', [IndexController::class, 'removeMdcDomain']);
    Route::get('download', [IndexController::class, 'download']);
});
