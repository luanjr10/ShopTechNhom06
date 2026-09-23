<?php

use App\Http\Controllers\Api\AiChatController;
use Illuminate\Support\Facades\Route;

// Chatbot AI tư vấn sản phẩm — public (khách chưa đăng nhập vẫn chat được),
// throttle để tránh lạm dụng quota OpenAI.
Route::post('/ai/chat', [AiChatController::class, 'chat'])->middleware('throttle:20,1');
