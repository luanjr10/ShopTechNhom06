<?php

use App\Http\Controllers\Api\TradeInController;
use Illuminate\Support\Facades\Route;

Route::prefix('trade-in')->group(function () {
    Route::get('catalog', [TradeInController::class, 'catalog']);
    Route::post('estimate', [TradeInController::class, 'estimate'])->middleware('throttle:60,1');

    Route::middleware('auth:api')->group(function () {
        Route::get('requests', [TradeInController::class, 'mine']);
        Route::post('requests', [TradeInController::class, 'store'])->middleware('throttle:10,1');
    });
});
