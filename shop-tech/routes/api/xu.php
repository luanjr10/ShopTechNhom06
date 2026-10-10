<?php

use App\Http\Controllers\Api\XuController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->prefix('xu')->group(function () {
    Route::get('summary', [XuController::class, 'summary']);
    Route::post('checkin', [XuController::class, 'checkIn']);
    Route::get('transactions', [XuController::class, 'transactions']);
    Route::post('redeemable', [XuController::class, 'redeemable']);
});
