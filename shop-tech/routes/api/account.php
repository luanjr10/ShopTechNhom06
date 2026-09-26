<?php

use App\Http\Controllers\Api\Account\AddressController;
use App\Http\Controllers\Api\Account\ProfileController;
use App\Http\Controllers\Api\Account\SessionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'token.version'])->group(function () {
    Route::patch('profile', [ProfileController::class, 'update']);
    Route::post('profile/avatar', [ProfileController::class, 'updateAvatar']);
    Route::post('change-password', [ProfileController::class, 'changePassword']);

    Route::post('logout-others', [SessionController::class, 'logoutOthers']);

    Route::get('addresses', [AddressController::class, 'index']);
    Route::post('addresses', [AddressController::class, 'store']);
    Route::patch('addresses/{address}', [AddressController::class, 'update']);
    Route::delete('addresses/{address}', [AddressController::class, 'destroy']);
    Route::post('addresses/{address}/default', [AddressController::class, 'setDefault']);
});
