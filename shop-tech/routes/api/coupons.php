<?php

use App\Http\Controllers\Api\CouponController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->post('coupons/apply', [CouponController::class, 'apply']);
