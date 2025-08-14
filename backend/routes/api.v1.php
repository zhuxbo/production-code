<?php

use App\Http\Controllers\V1\IndexController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API V1 Routes (Deprecated)
|--------------------------------------------------------------------------
*/
Route::prefix('V1')->middleware('api.v1')->group(function () {
    Route::get('health', [IndexController::class, 'health']);
    Route::post('product', [IndexController::class, 'getProducts']);
    Route::post('new', [IndexController::class, 'new']);
    Route::post('renew', [IndexController::class, 'renew']);
    Route::post('reissue', [IndexController::class, 'reissue']);
    Route::post('get', [IndexController::class, 'get']);
    Route::post('getOidByReferId', [IndexController::class, 'getOrderIdByReferId']);
    Route::post('cancel', [IndexController::class, 'cancel']);
    Route::post('revalidate', [IndexController::class, 'revalidate']);
    Route::post('updateDCV', [IndexController::class, 'updateDCV']);
    Route::post('removeMdcDomain', [IndexController::class, 'removeMdcDomain']);
    Route::post('download', [IndexController::class, 'download']);
});
