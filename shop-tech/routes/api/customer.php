<?php

use App\Http\Controllers\Api\CustomerReturnController;
use App\Http\Controllers\Api\CustomerVoucherController;
use App\Http\Controllers\Api\LoyaltyController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\SellerApplicationController;
use Illuminate\Support\Facades\Route;

// Nghiệp vụ của customer đã đăng nhập: đặt hàng + đăng ký làm người bán.
Route::middleware('auth:api')->group(function () {
    // Hạng thành viên + ví voucher hạng thành viên
    Route::get('loyalty/summary', [LoyaltyController::class, 'summary']);
    Route::get('vouchers/mine', [CustomerVoucherController::class, 'index']);
    Route::post('vouchers/{coupon}/claim', [CustomerVoucherController::class, 'claim']);

    // Đơn hàng (multi-seller checkout)
    Route::post('orders', [OrderController::class, 'place']);
    Route::get('orders/mine', [OrderController::class, 'mine']);
    Route::get('orders/{order}', [OrderController::class, 'show']);
    Route::post('orders/{order}/cancel', [OrderController::class, 'cancel']);
    // Customer xác nhận ĐÃ NHẬN hàng cho 1 seller_order (khác seller có thể giao
    // xong ở thời điểm khác nhau) — CHỈ hợp lệ khi seller_order đang 'delivered'.
    Route::post('orders/{order}/seller-orders/{sellerOrder}/complete', [OrderController::class, 'completeSellerOrder']);

    // Đăng ký trở thành người bán
    Route::post('seller-applications', [SellerApplicationController::class, 'store']);
    Route::get('seller-applications/mine', [SellerApplicationController::class, 'mine']);

    // Hoàn trả / bảo hành — chỉ cho item đã nhận hàng (seller_order completed).
    Route::get('returns/mine', [CustomerReturnController::class, 'index']);
    Route::get('returns/{returnRequest}', [CustomerReturnController::class, 'show']);
    Route::post('order-items/{orderItem}/returns', [CustomerReturnController::class, 'store']);
});
