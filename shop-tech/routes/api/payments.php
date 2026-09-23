<?php

use App\Http\Controllers\Api\Admin\WithdrawalPaymentController;
use App\Http\Controllers\Api\PaymentController;
use Illuminate\Support\Facades\Route;

// Tạo yêu cầu thanh toán — cần đăng nhập (order phải thuộc user hiện tại).
Route::middleware('auth:api')->prefix('payments')->group(function () {
    Route::post('momo/create', [PaymentController::class, 'momoCreate']);
    Route::post('vnpay/create', [PaymentController::class, 'vnpayCreate']);
    Route::post('onepay/create', [PaymentController::class, 'onepayCreate']);
    Route::post('sepay/create', [PaymentController::class, 'sepayCreate']);
});

// Return/IPN — cổng thanh toán gọi tới, KHÔNG có JWT. Xác thực bằng signature
// riêng của từng cổng (xem MomoService/VnpayService/OnePayService::verifySignature,
// SePay dùng header X-Secret-Key xem SePayService::verifyIpnAuth).
Route::prefix('payments')->group(function () {
    Route::get('momo/return', [PaymentController::class, 'momoReturn'])->name('payments.momo.return');
    Route::post('momo/notify', [PaymentController::class, 'momoNotify'])->name('payments.momo.notify');
    Route::get('vnpay/return', [PaymentController::class, 'vnpayReturn'])->name('payments.vnpay.return');
    Route::get('onepay/return', [PaymentController::class, 'onepayReturn'])->name('payments.onepay.return');
    Route::get('sepay/return', [PaymentController::class, 'sepayReturn'])->name('payments.sepay.return');
    // Trang trung gian auto-submit form POST sang SePay thật (checkout/init chỉ
    // nhận submit form, không phải link GET) — xem PaymentController::sepayRedirect.
    Route::get('sepay/redirect/{order}', [PaymentController::class, 'sepayRedirect'])->name('payments.sepay.redirect');
    // SePay IPN xác thực bằng header X-Secret-Key, KHÔNG cần route name (chỉ URL để đăng ký merchant).
    Route::post('sepay/webhook', [PaymentController::class, 'sepayWebhook']);
});

// Stub SePay — chỉ hoạt động khi SEPAY_STUB=true. Mount trong api.php để
// xử lý webhook nội bộ (route confirm ở web.php dùng Blade view).
Route::prefix('payments')->group(function () {
    Route::post('sepay/webhook/stub', [PaymentController::class, 'sepayStubWebhook'])->name('payments.sepay.webhook.stub');
});

// Return từ sandbox khi ADMIN duyệt rút tiền qua cổng online — public (cổng
// gọi/redirect trình duyệt admin về, không có JWT), xác thực bằng signature
// riêng từng cổng (xem WithdrawalPaymentController).
Route::prefix('payments')->group(function () {
    Route::get('momo/withdrawal-return', [WithdrawalPaymentController::class, 'momoReturn'])->name('payments.momo.withdrawal-return');
    Route::get('vnpay/withdrawal-return', [WithdrawalPaymentController::class, 'vnpayReturn'])->name('payments.vnpay.withdrawal-return');
    Route::get('onepay/withdrawal-return', [WithdrawalPaymentController::class, 'onepayReturn'])->name('payments.onepay.withdrawal-return');
    Route::get('sepay/withdrawal-return/{withdrawal}', [WithdrawalPaymentController::class, 'sepayReturn'])->name('payments.sepay.withdrawal-return');
});
