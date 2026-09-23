<?php

use App\Http\Controllers\Api\CartController;
use Illuminate\Support\Facades\Route;

// Giỏ hàng — bắt buộc đăng nhập (guest không có cart). Chủ sở hữu luôn lấy từ
// $request->user(), KHÔNG bao giờ tin user_id từ body.
Route::middleware('auth:api')->prefix('cart')->group(function () {
    Route::get('/', [CartController::class, 'index']);
    Route::post('items', [CartController::class, 'store']);
    Route::put('items/{cartItem}', [CartController::class, 'update']);
    Route::delete('items/{cartItem}', [CartController::class, 'destroy']);
    Route::delete('/', [CartController::class, 'clear']);
});
