<?php

use App\Http\Controllers\Api\LocationController;
use Illuminate\Support\Facades\Route;

// Dữ liệu hành chính VN theo hệ CŨ của GHN (tỉnh -> quận/huyện -> phường/xã) — public, chỉ đọc.
Route::prefix('locations')->group(function () {
    Route::get('provinces', [LocationController::class, 'provinces']);
    Route::get('provinces/{id}/districts', [LocationController::class, 'districts']);
    Route::get('districts/{id}/wards', [LocationController::class, 'wards']);
});
