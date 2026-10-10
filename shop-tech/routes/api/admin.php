<?php

use App\Http\Controllers\Api\Admin\ChatController;
use App\Http\Controllers\Api\Admin\CommissionController;
use App\Http\Controllers\Api\Admin\CustomerController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\EmployeeController;
use App\Http\Controllers\Api\Admin\HomeHighlightController;
use App\Http\Controllers\Api\Admin\OrderController;
use App\Http\Controllers\Api\Admin\PlatformFundsController;
use App\Http\Controllers\Api\Admin\ReviewController;
use App\Http\Controllers\Api\Admin\SellerApplicationController;
use App\Http\Controllers\Api\Admin\StoreController;
use App\Http\Controllers\Api\Admin\StoreFollowController;
use App\Http\Controllers\Api\Admin\WithdrawalController;
use App\Http\Controllers\Api\Admin\WithdrawalPaymentController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware(['auth:api', 'role:admin,employee'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'summary']);

    Route::middleware('permission:seller_applications,view')->group(function () {
        Route::get('seller-applications', [SellerApplicationController::class, 'index']);
        Route::get('seller-applications/{application}', [SellerApplicationController::class, 'show']);
    });
    Route::middleware('permission:seller_applications,edit')->group(function () {
        Route::post('seller-applications/{application}/approve', [SellerApplicationController::class, 'approve']);
        Route::post('seller-applications/{application}/reject', [SellerApplicationController::class, 'reject']);
    });

    Route::get('stores', [StoreController::class, 'index'])->middleware('permission:stores,view');
    Route::patch('stores/{store}/status', [StoreController::class, 'updateStatus'])->middleware('permission:stores,edit');

    Route::get('commissions', [CommissionController::class, 'index'])->middleware('permission:commissions,view');
    Route::post('commissions', [CommissionController::class, 'upsert'])->middleware('permission:commissions,create');
    Route::delete('commissions/{commission}', [CommissionController::class, 'destroy'])->middleware('permission:commissions,delete');

    Route::middleware('permission:orders,view')->group(function () {
        Route::get('orders', [OrderController::class, 'index']);
        Route::get('orders/{order}', [OrderController::class, 'show']);
        Route::get('orders/{order}/invoice/pdf', [OrderController::class, 'invoicePdf']);
    });
    Route::post('orders/{order}/invoice/email', [OrderController::class, 'emailInvoice'])->middleware('permission:orders,edit');

    Route::middleware('permission:customers,view')->group(function () {
        Route::get('customers', [CustomerController::class, 'index']);
        Route::get('customers/{customer}', [CustomerController::class, 'show']);
    });

    Route::middleware('permission:withdrawals,view')->group(function () {
        Route::get('withdrawals', [WithdrawalController::class, 'index']);
    });
    Route::middleware('permission:withdrawals,edit')->group(function () {
        Route::post('withdrawals/{withdrawal}/approve', [WithdrawalController::class, 'approve']);
        Route::post('withdrawals/{withdrawal}/reject', [WithdrawalController::class, 'reject']);
        Route::post('withdrawals/{withdrawal}/pay', [WithdrawalPaymentController::class, 'create']);
        Route::get('withdrawals/{withdrawal}/sepay-redirect', [WithdrawalPaymentController::class, 'sepayRedirect'])
            ->name('admin.withdrawals.sepay-redirect');
    });

    Route::prefix('chat')->group(function () {
        Route::get('conversations', [ChatController::class, 'index'])->middleware('permission:support_chat,view');
        Route::get('conversations/{conversation}/messages', [ChatController::class, 'messages'])->middleware('permission:support_chat,view');
        Route::post('conversations/{conversation}/messages', [ChatController::class, 'send'])->middleware(['permission:support_chat,edit', 'throttle:60,1']);
    });

    Route::middleware('permission:reviews,view')->group(function () {
        Route::get('reviews', [ReviewController::class, 'index']);
        Route::get('store-follows', [StoreFollowController::class, 'index']);
    });
    Route::delete('reviews/{review}', [ReviewController::class, 'destroy'])->middleware('permission:reviews,delete');

    Route::middleware('permission:platform_funds,view')->group(function () {
        Route::get('platform-funds/summary', [PlatformFundsController::class, 'summary']);
        Route::get('platform-funds/held', [PlatformFundsController::class, 'held']);
        Route::get('platform-funds/settlements', [PlatformFundsController::class, 'settlements']);
    });

    Route::middleware('permission:home_highlights,view')->group(function () {
        Route::get('home-highlights/flash-sale', [HomeHighlightController::class, 'showFlashSale']);
        Route::get('home-highlights/products', [HomeHighlightController::class, 'products']);
    });
    Route::middleware('permission:home_highlights,edit')->group(function () {
        Route::put('home-highlights/flash-sale', [HomeHighlightController::class, 'updateFlashSale']);
        Route::patch('home-highlights/products/{id}', [HomeHighlightController::class, 'updateProductFlags']);
    });

    Route::get('permission-modules', [EmployeeController::class, 'modules']);

    Route::middleware('role:admin')->group(function () {
        Route::get('employees', [EmployeeController::class, 'index']);
        Route::get('employees/{employee}', [EmployeeController::class, 'show']);
        Route::post('employees', [EmployeeController::class, 'store']);
        Route::patch('employees/{employee}', [EmployeeController::class, 'update']);
        Route::delete('employees/{employee}', [EmployeeController::class, 'destroy']);
        Route::put('employees/{employee}/permissions', [EmployeeController::class, 'updatePermissions']);
    });
});
