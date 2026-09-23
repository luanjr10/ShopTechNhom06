<?php

use App\Http\Controllers\Stub\SePayStubController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Stub SePay — chỉ hoạt động khi services.sepay.stub=true. Mount trong
// web.php (chứ không phải api.php) vì trang stub render Blade view cho khách.
Route::middleware([])->prefix('sepay-stub')->group(function () {
    Route::get('checkout', [SePayStubController::class, 'checkout'])->name('sepay.stub.checkout');
    Route::post('confirm', [SePayStubController::class, 'confirm'])->name('sepay.stub.confirm');
    Route::post('cancel', [SePayStubController::class, 'cancel'])->name('sepay.stub.cancel');
});
