<?php

use App\Http\Controllers\Api\UseCaseController;
use Illuminate\Support\Facades\Route;

// Storefront: lấy Quick Link đang hiển thị theo categoryId.
Route::get('use-cases', [UseCaseController::class, 'index']);
