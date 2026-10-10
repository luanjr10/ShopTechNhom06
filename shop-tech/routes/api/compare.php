<?php

use App\Http\Controllers\Api\CompareController;
use Illuminate\Support\Facades\Route;

Route::prefix('compare')->group(function () {
    Route::get('/', [CompareController::class, 'show']);
    Route::get('verdict', [CompareController::class, 'verdict'])->middleware('throttle:20,1');
});
