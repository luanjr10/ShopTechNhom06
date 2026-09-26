<?php

use App\Http\Controllers\Api\Account\SessionController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\Api\PasswordResetController;
use Illuminate\Support\Facades\Route;

Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);

Route::post('forgot-password', [PasswordResetController::class, 'forgot'])
    ->middleware('throttle:12,1');
Route::post('verify-reset-code', [PasswordResetController::class, 'verify'])
    ->middleware('throttle:10,1');
Route::post('reset-password', [PasswordResetController::class, 'reset'])
    ->middleware('throttle:10,1');

Route::post('refresh', [SessionController::class, 'refresh']);

Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware('signed')
    ->name('verification.verify');

Route::get('auth/google', [GoogleAuthController::class, 'redirect']);
Route::get('auth/google/callback', [GoogleAuthController::class, 'callback']);

Route::middleware(['auth:api', 'token.version'])->group(function () {
    Route::get('me', [AuthController::class, 'me']);
    Route::post('logout', [AuthController::class, 'logout']);

    Route::post('email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:6,1');
});
