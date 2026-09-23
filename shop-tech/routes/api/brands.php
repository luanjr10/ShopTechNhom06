<?php

use App\Http\Controllers\Api\BrandController;
use Illuminate\Support\Facades\Route;

Route::prefix('brands')->group(function () {
    Route::get('/', [BrandController::class, 'index']);
    Route::get('/all', [BrandController::class, 'getAll']);
    Route::get('/{id}', [BrandController::class, 'getBrandDetail']);

    // Ghi — admin hoặc nhân viên có quyền module "brands" (xem CheckModulePermission).
    Route::middleware(['auth:api', 'role:admin,employee'])->group(function () {
        Route::post('/', [BrandController::class, 'createBrand'])->middleware('permission:brands,create');
        Route::patch('/{id}', [BrandController::class, 'updateBrand'])->middleware('permission:brands,edit');
        Route::delete('/{id}', [BrandController::class, 'deleteBrand'])->middleware('permission:brands,delete');
    });
});
