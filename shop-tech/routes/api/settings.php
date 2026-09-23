<?php

use App\Http\Controllers\Api\SiteSettingController;
use Illuminate\Support\Facades\Route;

// Public — storefront cần biết giờ kết thúc flash sale để đếm ngược.
Route::get('settings/flash-sale', [SiteSettingController::class, 'flashSale']);
