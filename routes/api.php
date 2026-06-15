<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\PaymentRequestController;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Http\Middleware\CheckToken;

Route::get('/countries', [CountryController::class, 'index']);
Route::get('/currencies', [CurrencyController::class, 'index']);

Route::post('/register', [AuthController::class, 'register'])
    ->middleware('throttle:5,1');

Route::middleware('auth:api')->get('/user', [AuthController::class, 'currentUser']);

Route::middleware('auth:api')->delete('/tokens/current', [AuthController::class, 'revokeToken']);

Route::middleware(['auth:api', CheckToken::class.':payments:read'])->group(function (): void {
    Route::get('/payment-requests', [PaymentRequestController::class, 'index']);
    Route::get('/payment-requests/{paymentRequest}', [PaymentRequestController::class, 'show']);
});

Route::middleware(['auth:api', CheckToken::class.':payments:create'])->post('/payment-requests', [PaymentRequestController::class, 'store']);

Route::middleware(['auth:api', CheckToken::class.':payments:approve'])->group(function (): void {
    Route::post('/payment-requests/{paymentRequest}/approval', [PaymentRequestController::class, 'approve']);
    Route::post('/payment-requests/{paymentRequest}/rejection', [PaymentRequestController::class, 'reject']);
});
