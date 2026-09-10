<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GameController;
use App\Services\Users\TokenAbility;
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

    // Sin ability concreto a propósito: cerrar sesión es lo mínimo que
    // cualquier token válido tiene que poder hacer, pase lo que pase con sus
    // demás abilities.
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/user', function (Request $request) {
        return $request->user();
    })->middleware('ability:'.TokenAbility::PROFILE_READ->value);

    // apiResource partido en dos grupos, no uno solo (ver TokenAbility): el
    // único token que existe hoy (AuthController::issueTokenResponse) tiene
    // todas las abilities, así que en la práctica no cambia nada — pero deja
    // sentada la separación lectura/escritura para un futuro token más
    // restringido sin tener que retocar las rutas otra vez.
    Route::middleware('ability:'.TokenAbility::GAMES_READ->value)->group(function () {
        Route::get('games', [GameController::class, 'index'])->name('games.index');
        Route::get('games/{game}', [GameController::class, 'show'])->name('games.show');
    });

    Route::middleware('ability:'.TokenAbility::GAMES_WRITE->value)->group(function () {
        Route::post('games', [GameController::class, 'store'])->name('games.store');
        Route::put('games/{game}', [GameController::class, 'update'])->name('games.update');
        Route::patch('games/{game}', [GameController::class, 'update']);
        Route::delete('games/{game}', [GameController::class, 'destroy'])->name('games.destroy');
    });

});
