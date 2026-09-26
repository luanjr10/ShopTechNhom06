<?php

use App\Http\Controllers\Api\UseCaseController;
use Illuminate\Support\Facades\Route;

Route::get('use-cases', [UseCaseController::class, 'index']);
