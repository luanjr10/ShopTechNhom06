<?php

use App\Http\Controllers\Api\ChatController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->prefix('chat')->group(function () {
    Route::get('conversations', [ChatController::class, 'index']);
    Route::post('conversations', [ChatController::class, 'start']);
    Route::post('support', [ChatController::class, 'startSupport']);
    Route::get('unread-count', [ChatController::class, 'unreadCount']);
    Route::get('conversations/{conversation}/messages', [ChatController::class, 'messages']);
    Route::post('conversations/{conversation}/messages', [ChatController::class, 'send'])->middleware('throttle:60,1');
});
