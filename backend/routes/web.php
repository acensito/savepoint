<?php

use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\EditionController;
use App\Http\Controllers\Web\GameController;
use App\Http\Controllers\Web\ManufacturerController;
use App\Http\Controllers\Web\PlatformController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas públicas (solo invitados)
|--------------------------------------------------------------------------
| El middleware 'guest' evita que alguien ya logueado vuelva al formulario
| de acceso. La ruta DEBE llamarse 'login': es a donde Laravel redirige
| automáticamente cuando el middleware 'auth' bloquea una petición.
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('web.login.attempt');
});

/*
|--------------------------------------------------------------------------
| Rutas protegidas (requieren sesión iniciada)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout'])->name('web.logout');

    // Colección (con búsqueda opcional por título/EAN vía ?q=)
    Route::get('/', [GameController::class, 'index'])->name('web.games.index');

    // OJO con el orden: las rutas estáticas van ANTES que las que llevan
    // un parámetro {game}, o '/games/create' acabaría entrando por
    // '/games/{game}' y buscando un juego con id "create".
    Route::get('/games/create', [GameController::class, 'create'])->name('web.games.create');

    Route::post('/games', [GameController::class, 'store'])->name('web.games.store');
    Route::get('/games/{game}/edit', [GameController::class, 'edit'])->name('web.games.edit');
    Route::put('/games/{game}', [GameController::class, 'update'])->name('web.games.update');
    Route::delete('/games/{game}', [GameController::class, 'destroy'])->name('web.games.destroy');

    // Panel de catálogo: fabricantes, plataformas y ediciones (normal/especial/coleccionista/...)
    Route::resource('manufacturers', ManufacturerController::class)->except('show')->names('web.manufacturers');
    Route::resource('platforms', PlatformController::class)->except('show')->names('web.platforms');
    Route::resource('editions', EditionController::class)->except('show')->names('web.editions');
});