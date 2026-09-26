<?php

use App\Http\Controllers\Api\SiteSettingController;
use Illuminate\Support\Facades\Route;

Route::get('settings/flash-sale', [SiteSettingController::class, 'flashSale']);
