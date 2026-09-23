<?php

use App\Http\Controllers\Api\PublicStoreController;
use App\Http\Controllers\Api\StoreFollowController;
use Illuminate\Support\Facades\Route;

// Xem gian hàng (public)
Route::get('stores', [PublicStoreController::class, 'index']);
Route::get('stores/{slug}', [PublicStoreController::class, 'show']);

// Theo dõi / bỏ theo dõi gian hàng (toggle) — cần đăng nhập.
Route::middleware('auth:api')->post('stores/{slug}/follow', [StoreFollowController::class, 'toggle']);
