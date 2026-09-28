<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

// El login es POST /oauth/token (password grant), lo registra Passport.

Route::middleware('auth:api')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
});
