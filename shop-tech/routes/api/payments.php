<?php

use App\Http\Controllers\Api\Admin\WithdrawalPaymentController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\Seller\AffiliateController as SellerAffiliateController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->prefix('payments')->group(function () {
    Route::post('momo/create', [PaymentController::class, 'momoCreate']);
    Route::post('vnpay/create', [PaymentController::class, 'vnpayCreate']);
    Route::post('onepay/create', [PaymentController::class, 'onepayCreate']);
    Route::post('sepay/create', [PaymentController::class, 'sepayCreate']);
});

Route::prefix('payments')->group(function () {
    Route::get('momo/return', [PaymentController::class, 'momoReturn'])->name('payments.momo.return');
    Route::post('momo/notify', [PaymentController::class, 'momoNotify'])->name('payments.momo.notify');
    Route::get('vnpay/return', [PaymentController::class, 'vnpayReturn'])->name('payments.vnpay.return');
    Route::get('onepay/return', [PaymentController::class, 'onepayReturn'])->name('payments.onepay.return');
    Route::get('sepay/return', [PaymentController::class, 'sepayReturn'])->name('payments.sepay.return');
    Route::get('sepay/redirect/{order}', [PaymentController::class, 'sepayRedirect'])->name('payments.sepay.redirect');
    Route::post('sepay/webhook', [PaymentController::class, 'sepayWebhook']);
});

Route::prefix('payments')->group(function () {
    Route::post('sepay/webhook/stub', [PaymentController::class, 'sepayStubWebhook'])->name('payments.sepay.webhook.stub');
});

Route::prefix('payments')->group(function () {
    Route::get('momo/affiliate-return', [SellerAffiliateController::class, 'momoReturn'])->name('payments.momo.affiliate-return');
    Route::get('momo/withdrawal-return', [WithdrawalPaymentController::class, 'momoReturn'])->name('payments.momo.withdrawal-return');
    Route::get('vnpay/withdrawal-return', [WithdrawalPaymentController::class, 'vnpayReturn'])->name('payments.vnpay.withdrawal-return');
    Route::get('onepay/withdrawal-return', [WithdrawalPaymentController::class, 'onepayReturn'])->name('payments.onepay.withdrawal-return');
    Route::get('sepay/withdrawal-return/{withdrawal}', [WithdrawalPaymentController::class, 'sepayReturn'])->name('payments.sepay.withdrawal-return');
});
