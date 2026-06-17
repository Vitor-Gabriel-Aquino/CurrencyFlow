<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\OAuthClientController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->prefix('developer')->name('developer.')->group(function (): void {
    Route::get('/oauth-clients', [OAuthClientController::class, 'index'])->name('oauth-clients.index');
    Route::post('/oauth-clients', [OAuthClientController::class, 'store'])->name('oauth-clients.store');
    Route::delete('/oauth-clients/{client}', [OAuthClientController::class, 'destroy'])->name('oauth-clients.destroy');
});
