<?php

use App\Http\Controllers\Api\Shipping\GhnWebhookController;
use App\Http\Controllers\Api\ShippingController;
use Illuminate\Support\Facades\Route;

// Tính phí vận chuyển THẬT qua GHN — public (khách chưa cần đăng nhập để xem giá ở checkout).
Route::post('shipping/fee', [ShippingController::class, 'fee']);

// Webhook GHN — public (GHN gọi tới, không có JWT), xác thực bằng header tuỳ
// chỉnh riêng (xem GhnWebhookController). Đăng ký URL này trên GHN Developer
// Portal (Cấu hình webhook) khi có domain public.
Route::post('shipping/ghn/webhook', [GhnWebhookController::class, 'handle']);
