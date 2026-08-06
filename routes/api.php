<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\GameController;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;

// Rutas PÚBLICAS
Route::post('/login', [AuthController::class, 'login']);

// Rutas PROTEGIDAS (Requieren Token)
Route::middleware('auth:sanctum')->group(function () {
    
    Route::post('/logout', [AuthController::class, 'logout']);
    
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::apiResource('games', GameController::class);
    
});