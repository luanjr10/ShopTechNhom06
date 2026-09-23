<?php

use App\Http\Controllers\Api\CouponController;
use Illuminate\Support\Facades\Route;

// Áp mã giảm giá lúc checkout — cần đăng nhập (chỉ để nhất quán với luồng đặt hàng).
Route::middleware('auth:api')->post('coupons/apply', [CouponController::class, 'apply']);
