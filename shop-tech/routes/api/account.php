<?php

use App\Http\Controllers\Api\Account\AddressController;
use App\Http\Controllers\Api\Account\ProfileController;
use App\Http\Controllers\Api\Account\SessionController;
use Illuminate\Support\Facades\Route;

// Khu vực tài khoản: cần đăng nhập (JWT cookie) và token_version hợp lệ.
Route::middleware(['auth:api', 'token.version'])->group(function () {
    // Hồ sơ
    Route::patch('profile', [ProfileController::class, 'update']);
    Route::post('profile/avatar', [ProfileController::class, 'updateAvatar']);
    Route::post('change-password', [ProfileController::class, 'changePassword']);

    // Phiên đăng nhập
    Route::post('logout-others', [SessionController::class, 'logoutOthers']);

    // Địa chỉ giao hàng
    Route::get('addresses', [AddressController::class, 'index']);
    Route::post('addresses', [AddressController::class, 'store']);
    Route::patch('addresses/{address}', [AddressController::class, 'update']);
    Route::delete('addresses/{address}', [AddressController::class, 'destroy']);
    Route::post('addresses/{address}/default', [AddressController::class, 'setDefault']);
});
