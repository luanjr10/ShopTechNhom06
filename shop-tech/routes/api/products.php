<?php

use App\Http\Controllers\Api\ProductCommentController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductReviewController;
use Illuminate\Support\Facades\Route;

Route::prefix('products')->group(function () {
    Route::get('/', [ProductController::class, 'index']);
    Route::get('/{id}', [ProductController::class, 'getProductDetail']);

    Route::get('/{product}/reviews', [ProductReviewController::class, 'index']);
    Route::get('/{product}/comments', [ProductCommentController::class, 'index']);
    Route::middleware('auth:api')->group(function () {
        Route::post('/{product}/reviews', [ProductReviewController::class, 'store']);
        Route::post('/{product}/comments', [ProductCommentController::class, 'store']);
    });

    Route::middleware(['auth:api', 'role:admin,employee'])->group(function () {
        Route::post('/', [ProductController::class, 'createProduct'])->middleware('permission:products,create');
        Route::match(['put', 'patch'], '/{id}', [ProductController::class, 'updateProduct'])->middleware('permission:products,edit');
        Route::delete('/{id}', [ProductController::class, 'deleteProduct'])->middleware('permission:products,delete');
    });
});
