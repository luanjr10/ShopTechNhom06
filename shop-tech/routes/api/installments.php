<?php

use App\Http\Controllers\Api\InstallmentController;
use Illuminate\Support\Facades\Route;

Route::prefix('installments')->group(function () {
    Route::get('options', [InstallmentController::class, 'options']);

    Route::middleware('auth:api')->group(function () {
        Route::get('summary', [InstallmentController::class, 'summary']);
        Route::get('/', [InstallmentController::class, 'index']);
        Route::post('payments/{payment}/pay', [InstallmentController::class, 'pay']);
    });
});

Route::match(['get', 'post'], 'payments/momo/installment-return', [InstallmentController::class, 'momoReturn'])
    ->name('payments.momo.installment-return');
