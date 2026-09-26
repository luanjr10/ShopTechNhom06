<?php

use App\Http\Controllers\Api\Shipping\GhnWebhookController;
use App\Http\Controllers\Api\ShippingController;
use Illuminate\Support\Facades\Route;

Route::post('shipping/fee', [ShippingController::class, 'fee']);

Route::post('shipping/ghn/webhook', [GhnWebhookController::class, 'handle']);
