<?php

use App\Http\Controllers\Api\Account\SessionController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\Api\PasswordResetController;
use Illuminate\Support\Facades\Route;

// Guest
Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);

// Quên / đặt lại mật khẩu (mã OTP 6 số)
Route::post('forgot-password', [PasswordResetController::class, 'forgot'])
    ->middleware('throttle:12,1');
Route::post('verify-reset-code', [PasswordResetController::class, 'verify'])
    ->middleware('throttle:10,1');
Route::post('reset-password', [PasswordResetController::class, 'reset'])
    ->middleware('throttle:10,1');

// Gia hạn phiên: dựa vào JWT trong cookie, không đặt sau auth:api để token
// gần hết hạn vẫn refresh được.
Route::post('refresh', [SessionController::class, 'refresh']);

// Xác thực email (link signed gửi qua mail).
Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware('signed')
    ->name('verification.verify');

// Google OAuth
Route::get('auth/google', [GoogleAuthController::class, 'redirect']);
Route::get('auth/google/callback', [GoogleAuthController::class, 'callback']);

// Đã đăng nhập (JWT trong cookie) + kiểm tra token_version.
Route::middleware(['auth:api', 'token.version'])->group(function () {
    Route::get('me', [AuthController::class, 'me']);
    Route::post('logout', [AuthController::class, 'logout']);

    Route::post('email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:6,1');
});
