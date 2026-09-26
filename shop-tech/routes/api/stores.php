<?php

use App\Http\Controllers\Api\PublicStoreController;
use App\Http\Controllers\Api\StoreFollowController;
use Illuminate\Support\Facades\Route;

Route::get('stores', [PublicStoreController::class, 'index']);
Route::get('stores/{slug}', [PublicStoreController::class, 'show']);

Route::middleware('auth:api')->post('stores/{slug}/follow', [StoreFollowController::class, 'toggle']);
