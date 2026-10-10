<?php

use App\Http\Controllers\Api\WishlistController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->prefix('wishlist')->group(function () {
    Route::get('/', [WishlistController::class, 'index']);
    Route::get('ids', [WishlistController::class, 'ids']);
    Route::post('/', [WishlistController::class, 'store']);
    Route::patch('{productId}', [WishlistController::class, 'update'])->whereNumber('productId');
    Route::delete('{productId}', [WishlistController::class, 'destroy'])->whereNumber('productId');
});
