<?php

use App\Http\Controllers\Api\Seller\CustomerController;
use App\Http\Controllers\Api\Seller\DashboardController;
use App\Http\Controllers\Api\Seller\InventoryController;
use App\Http\Controllers\Api\Seller\ReturnController;
use App\Http\Controllers\Api\Seller\RevenueController;
use App\Http\Controllers\Api\Seller\ReviewController;
use App\Http\Controllers\Api\Seller\SellerOrderController;
use App\Http\Controllers\Api\Seller\SellerProductController;
use App\Http\Controllers\Api\Seller\StoreController;
use App\Http\Controllers\Api\Seller\StoreFollowerController;
use App\Http\Controllers\Api\Seller\WalletController;
use App\Http\Controllers\Api\Seller\WithdrawalController;
use Illuminate\Support\Facades\Route;

// Seller Center API — chỉ seller đã approved.
Route::prefix('seller')->middleware(['auth:api', 'role:seller', 'seller.approved'])->group(function () {
    // Gian hàng (không cần store.owner: tạo/list theo seller hiện tại)
    Route::get('stores', [StoreController::class, 'index']);
    Route::post('stores', [StoreController::class, 'store']);

    // Ví + rút tiền (theo SellerProfile, dùng chung mọi store)
    Route::get('wallet', [WalletController::class, 'show']);
    Route::get('wallet/transactions', [WalletController::class, 'transactions']);
    Route::get('withdrawals', [WithdrawalController::class, 'index']);
    Route::post('withdrawals', [WithdrawalController::class, 'store']);

    // Các thao tác gắn với 1 store cụ thể — bắt buộc store.owner.
    Route::prefix('stores/{store}')->middleware('store.owner')->group(function () {
        Route::get('/', [StoreController::class, 'show']);
        Route::match(['put', 'patch', 'post'], '/', [StoreController::class, 'update']);
        Route::put('pickup-address', [StoreController::class, 'updatePickupAddress']);

        // Tổng quan (Dashboard)
        Route::get('dashboard', [DashboardController::class, 'summary']);

        // Sản phẩm của store
        Route::get('products', [SellerProductController::class, 'index']);
        Route::post('products', [SellerProductController::class, 'store']);
        Route::match(['put', 'patch', 'post'], 'products/{product}', [SellerProductController::class, 'update']);
        Route::delete('products/{product}', [SellerProductController::class, 'destroy']);

        // Đơn hàng của store
        Route::get('orders', [SellerOrderController::class, 'index']);
        Route::get('orders/{sellerOrder}', [SellerOrderController::class, 'show']);
        Route::patch('orders/{sellerOrder}/status', [SellerOrderController::class, 'updateStatus']);
        Route::post('orders/{sellerOrder}/handover', [SellerOrderController::class, 'handover']);

        // Driver mode (test/sandbox) — seller tự đóng vai shipper.
        Route::post('orders/{sellerOrder}/driver-mark-delivered', [SellerOrderController::class, 'driverMarkDelivered']);
        Route::post('orders/{sellerOrder}/driver-mark-cancelled', [SellerOrderController::class, 'driverMarkCancelled']);

        // Kho hàng — tồn kho + điều chỉnh (có log lịch sử)
        Route::get('inventory', [InventoryController::class, 'index']);
        Route::post('products/{product}/stock-adjustments', [InventoryController::class, 'adjust']);
        Route::get('products/{product}/stock-adjustments', [InventoryController::class, 'history']);

        // Doanh thu
        Route::get('revenue', [RevenueController::class, 'summary']);

        // Khách hàng của gian hàng — chỉ xem.
        Route::get('customers', [CustomerController::class, 'index']);
        Route::get('customers/{customer}', [CustomerController::class, 'show']);

        // Yêu cầu hoàn trả / bảo hành của khách cho gian hàng này.
        Route::get('returns', [ReturnController::class, 'index']);
        Route::get('returns/{returnRequest}', [ReturnController::class, 'show']);
        Route::patch('returns/{returnRequest}/respond', [ReturnController::class, 'respond']);

        // Hóa đơn — xem/tải PDF + gửi email cho khách (xem InvoiceService).
        Route::get('orders/{sellerOrder}/invoice/pdf', [SellerOrderController::class, 'invoicePdf']);
        Route::post('orders/{sellerOrder}/invoice/email', [SellerOrderController::class, 'emailInvoice']);

        // Đánh giá sản phẩm của gian hàng — chỉ xem.
        Route::get('reviews', [ReviewController::class, 'index']);

        // Người theo dõi gian hàng — chỉ xem.
        Route::get('followers', [StoreFollowerController::class, 'index']);
    });
});
