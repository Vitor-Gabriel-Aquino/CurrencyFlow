<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])
    ->middleware('throttle:5,1');

Route::middleware('auth:api')->get('/user', [AuthController::class, 'currentUser']);

Route::middleware('auth:api')->delete('/tokens/current', [AuthController::class, 'revokeToken']);
