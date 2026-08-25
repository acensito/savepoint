<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GameController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Rutas PÚBLICAS
Route::post('/login', [AuthController::class, 'login']);

// Segundo paso del login cuando la cuenta tiene 2FA activo (ver
// AuthController::login()): reciben el "two_factor_token" de un solo uso
// devuelto por /login, no un token de acceso, así que van fuera de
// auth:sanctum igual que /login.
Route::post('/login/verify-2fa', [AuthController::class, 'verifyTwoFactor'])
    ->middleware('throttle:api-two-factor-verify');
Route::post('/login/resend-2fa', [AuthController::class, 'resendTwoFactor'])
    ->middleware('throttle:api-two-factor-resend');

// Rutas PROTEGIDAS (Requieren Token)
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::apiResource('games', GameController::class);

});
