<?php

use App\Http\Controllers\Api\AffiliateController;
use App\Http\Controllers\Api\CustomerReturnController;
use App\Http\Controllers\Api\CustomerVoucherController;
use App\Http\Controllers\Api\LoyaltyController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\SellerApplicationController;
use Illuminate\Support\Facades\Route;

Route::get('affiliate/products', [AffiliateController::class, 'products']);

Route::middleware('auth:api')->group(function () {
    Route::get('loyalty/summary', [LoyaltyController::class, 'summary']);
    Route::get('vouchers/mine', [CustomerVoucherController::class, 'index']);
    Route::post('vouchers/{coupon}/claim', [CustomerVoucherController::class, 'claim']);

    Route::prefix('affiliate')->group(function () {
        Route::get('summary', [AffiliateController::class, 'summary']);
        Route::get('commissions', [AffiliateController::class, 'commissions']);
        Route::get('withdrawals', [AffiliateController::class, 'withdrawals']);
        Route::post('withdrawals', [AffiliateController::class, 'requestWithdrawal']);
    });

    Route::post('orders', [OrderController::class, 'place']);
    Route::get('orders/mine', [OrderController::class, 'mine']);
    Route::get('orders/{order}', [OrderController::class, 'show']);
    Route::post('orders/{order}/cancel', [OrderController::class, 'cancel']);
    Route::post('orders/{order}/seller-orders/{sellerOrder}/complete', [OrderController::class, 'completeSellerOrder']);

    Route::post('seller-applications', [SellerApplicationController::class, 'store']);
    Route::get('seller-applications/mine', [SellerApplicationController::class, 'mine']);

    Route::get('returns/mine', [CustomerReturnController::class, 'index']);
    Route::get('returns/{returnRequest}', [CustomerReturnController::class, 'show']);
    Route::post('order-items/{orderItem}/returns', [CustomerReturnController::class, 'store']);
});
