<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\GameController;
use Illuminate\Http\Request;

// Rutas protegidas (Requieren Token)
Route::middleware('auth:sanctum')->group(function () {
    
    // Devuelve los datos del usuario logueado actualmente
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Todo el CRUD de juegos protegido
    Route::apiResource('games', GameController::class);
    
});