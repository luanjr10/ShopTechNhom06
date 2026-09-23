<?php

use App\Http\Controllers\Api\CategoryCommentController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\UseCaseController;
use Illuminate\Support\Facades\Route;

Route::prefix('categories')->group(function () {
    Route::get('/', [CategoryController::class, 'index']);
    Route::get('/{id}', [CategoryController::class, 'getCategoryById']);
    Route::get('/{categoryId}/use-cases', [UseCaseController::class, 'indexByCategory']);

    // "Hỏi & đáp" theo danh mục — xem public, gửi cần đăng nhập (cùng khuôn mẫu products.php).
    Route::get('/{category}/comments', [CategoryCommentController::class, 'index']);
    Route::middleware('auth:api')->group(function () {
        Route::post('/{category}/comments', [CategoryCommentController::class, 'store']);
    });

    // Ghi — admin hoặc nhân viên có quyền module "categories".
    Route::middleware(['auth:api', 'role:admin,employee'])->group(function () {
        Route::post('/', [CategoryController::class, 'store'])->middleware('permission:categories,create');
        Route::patch('/{id}', [CategoryController::class, 'editCategory'])->middleware('permission:categories,edit');
        Route::delete('/{id}', [CategoryController::class, 'deleteCategory'])->middleware('permission:categories,delete');

        Route::middleware('permission:categories,edit')->group(function () {
            Route::post('/{categoryId}/use-cases', [UseCaseController::class, 'store']);
            Route::match(['put', 'patch'], '/{categoryId}/use-cases/{useCaseId}', [UseCaseController::class, 'update']);
            Route::delete('/{categoryId}/use-cases/{useCaseId}', [UseCaseController::class, 'destroy']);

            // Ảnh đại diện danh mục (thay icon) — upload Cloudinary, lưu MongoDB.
            Route::post('/{id}/image', [CategoryController::class, 'uploadImage']);
            Route::delete('/{id}/image', [CategoryController::class, 'deleteImage']);
        });
    });
});
