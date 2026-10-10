<?php

use App\Http\Controllers\Api\Seller\AffiliateController;
use App\Http\Controllers\Api\Seller\ChatController;
use App\Http\Controllers\Api\Seller\CouponController;
use App\Http\Controllers\Api\Seller\CustomerController;
use App\Http\Controllers\Api\Seller\DashboardController;
use App\Http\Controllers\Api\Seller\InstallmentController;
use App\Http\Controllers\Api\Seller\InventoryController;
use App\Http\Controllers\Api\Seller\ReturnController;
use App\Http\Controllers\Api\Seller\RevenueController;
use App\Http\Controllers\Api\Seller\ReviewController;
use App\Http\Controllers\Api\Seller\SellerOrderController;
use App\Http\Controllers\Api\Seller\SellerProductController;
use App\Http\Controllers\Api\Seller\StoreController;
use App\Http\Controllers\Api\Seller\StoreFollowerController;
use App\Http\Controllers\Api\Seller\TradeInController;
use App\Http\Controllers\Api\Seller\WalletController;
use App\Http\Controllers\Api\Seller\WithdrawalController;
use Illuminate\Support\Facades\Route;

Route::prefix('seller')->middleware(['auth:api', 'role:seller', 'seller.approved'])->group(function () {
    Route::get('stores', [StoreController::class, 'index']);
    Route::post('stores', [StoreController::class, 'store']);

    Route::get('wallet', [WalletController::class, 'show']);
    Route::get('wallet/transactions', [WalletController::class, 'transactions']);
    Route::get('withdrawals', [WithdrawalController::class, 'index']);
    Route::post('withdrawals', [WithdrawalController::class, 'store']);

    Route::prefix('stores/{store}')->middleware('store.owner')->group(function () {
        Route::get('/', [StoreController::class, 'show']);
        Route::match(['put', 'patch', 'post'], '/', [StoreController::class, 'update']);
        Route::put('pickup-address', [StoreController::class, 'updatePickupAddress']);

        Route::get('dashboard', [DashboardController::class, 'summary']);

        Route::get('products', [SellerProductController::class, 'index']);
        Route::post('products', [SellerProductController::class, 'store']);
        Route::match(['put', 'patch', 'post'], 'products/{product}', [SellerProductController::class, 'update']);
        Route::delete('products/{product}', [SellerProductController::class, 'destroy']);

        Route::get('orders', [SellerOrderController::class, 'index']);
        Route::get('orders/{sellerOrder}', [SellerOrderController::class, 'show']);
        Route::patch('orders/{sellerOrder}/status', [SellerOrderController::class, 'updateStatus']);
        Route::post('orders/{sellerOrder}/handover', [SellerOrderController::class, 'handover']);

        Route::post('orders/{sellerOrder}/driver-mark-delivered', [SellerOrderController::class, 'driverMarkDelivered']);
        Route::post('orders/{sellerOrder}/driver-mark-cancelled', [SellerOrderController::class, 'driverMarkCancelled']);

        Route::get('inventory', [InventoryController::class, 'index']);
        Route::post('products/{product}/stock-adjustments', [InventoryController::class, 'adjust']);
        Route::get('products/{product}/stock-adjustments', [InventoryController::class, 'history']);

        Route::get('revenue', [RevenueController::class, 'summary']);

        Route::get('customers', [CustomerController::class, 'index']);
        Route::get('customers/{customer}', [CustomerController::class, 'show']);

        Route::get('returns', [ReturnController::class, 'index']);
        Route::get('returns/{returnRequest}', [ReturnController::class, 'show']);
        Route::patch('returns/{returnRequest}/respond', [ReturnController::class, 'respond']);

        Route::get('orders/{sellerOrder}/invoice/pdf', [SellerOrderController::class, 'invoicePdf']);
        Route::post('orders/{sellerOrder}/invoice/email', [SellerOrderController::class, 'emailInvoice']);

        Route::get('reviews', [ReviewController::class, 'index']);

        Route::get('followers', [StoreFollowerController::class, 'index']);

        Route::get('coupons', [CouponController::class, 'index']);
        Route::post('coupons', [CouponController::class, 'store']);
        Route::patch('coupons/{coupon}', [CouponController::class, 'update']);
        Route::delete('coupons/{coupon}', [CouponController::class, 'destroy']);

        Route::get('installments/settings', [InstallmentController::class, 'settings']);
        Route::put('installments/settings', [InstallmentController::class, 'updateSettings']);
        Route::get('installments', [InstallmentController::class, 'index']);
        Route::post('installments/{plan}/decide', [InstallmentController::class, 'decide']);

        Route::get('affiliate/settings', [AffiliateController::class, 'settings']);
        Route::put('affiliate/settings', [AffiliateController::class, 'updateSettings']);
        Route::get('affiliate/products', [AffiliateController::class, 'products']);
        Route::put('affiliate/products', [AffiliateController::class, 'updateProducts']);
        Route::get('affiliate/commissions', [AffiliateController::class, 'commissions']);
        Route::get('affiliate/withdrawals', [AffiliateController::class, 'withdrawals']);
        Route::post('affiliate/withdrawals/{withdrawal}/pay', [AffiliateController::class, 'pay']);
        Route::post('affiliate/withdrawals/{withdrawal}/review', [AffiliateController::class, 'review']);

        Route::get('trade-in/models', [TradeInController::class, 'models']);
        Route::post('trade-in/models', [TradeInController::class, 'storeModel']);
        Route::patch('trade-in/models/{model}', [TradeInController::class, 'updateModel']);
        Route::delete('trade-in/models/{model}', [TradeInController::class, 'destroyModel']);
        Route::get('trade-in/requests', [TradeInController::class, 'requests']);
        Route::post('trade-in/requests/{tradeInRequest}/review', [TradeInController::class, 'review']);

        Route::get('chat/conversations', [ChatController::class, 'index']);
        Route::get('chat/conversations/{conversation}/messages', [ChatController::class, 'messages']);
        Route::post('chat/conversations/{conversation}/messages', [ChatController::class, 'send'])->middleware('throttle:60,1');
    });
});
