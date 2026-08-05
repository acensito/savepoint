<?php

use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\EditionController;
use App\Http\Controllers\Web\GameController;
use App\Http\Controllers\Web\GameImportController;
use App\Http\Controllers\Web\ManufacturerController;
use App\Http\Controllers\Web\PasswordResetController;
use App\Http\Controllers\Web\PlatformController;
use App\Http\Controllers\Web\ProfileController;
use App\Http\Controllers\Web\SearchController;
use App\Http\Controllers\Web\StatsController;
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

    // Recuperación de contraseña: pedir enlace por email y consumirlo con un token de un solo uso.
    Route::get('/forgot-password', [PasswordResetController::class, 'showForgot'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update');
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

    // Estadísticas de la colección
    Route::get('/stats', [StatsController::class, 'index'])->name('web.stats.index');

    // Búsqueda rápida global (Ctrl+K): unos pocos resultados en vivo por título/EAN.
    Route::get('/search/quick', [SearchController::class, 'quick'])->name('web.search.quick');

    // OJO con el orden: las rutas estáticas van ANTES que las que llevan
    // un parámetro {game}, o '/games/create' acabaría entrando por
    // '/games/{game}' y buscando un juego con id "create".
    Route::get('/games/create', [GameController::class, 'create'])->name('web.games.create');

    // Importación masiva desde CSV (volcado inicial de la colección).
    Route::get('/games/import', [GameImportController::class, 'create'])->name('web.games.import');
    Route::post('/games/import', [GameImportController::class, 'store'])->name('web.games.import.store');
    Route::post('/games/import/preview', [GameImportController::class, 'preview'])->name('web.games.import.preview');
    Route::get('/games/import/template', [GameImportController::class, 'template'])->name('web.games.import.template');

    // Papelera: juegos borrados (soft delete) del usuario, con opción de
    // restaurar o eliminar definitivamente.
    Route::get('/games/trash', [GameController::class, 'trash'])->name('web.games.trash');
    Route::post('/games/{id}/restore', [GameController::class, 'restore'])->name('web.games.restore');
    Route::delete('/games/{id}/force-delete', [GameController::class, 'forceDelete'])->name('web.games.force-delete');

    // Acciones en bloque sobre varios juegos a la vez desde el listado.
    Route::post('/games/bulk-delete', [GameController::class, 'bulkDestroy'])->name('web.games.bulk-delete');
    Route::post('/games/bulk-play-status', [GameController::class, 'bulkUpdatePlayStatus'])->name('web.games.bulk-play-status');

    Route::post('/games', [GameController::class, 'store'])->name('web.games.store');
    Route::get('/games/{game}/edit', [GameController::class, 'edit'])->name('web.games.edit');
    Route::patch('/games/{game}/quick-update', [GameController::class, 'quickUpdate'])->name('web.games.quick-update');
    Route::put('/games/{game}', [GameController::class, 'update'])->name('web.games.update');
    Route::delete('/games/{game}', [GameController::class, 'destroy'])->name('web.games.destroy');
    Route::get('/games/{game}', [GameController::class, 'show'])->name('web.games.show');

    // Panel de catálogo: fabricantes, plataformas y ediciones (normal/especial/coleccionista/...)
    Route::resource('manufacturers', ManufacturerController::class)->except('show')->names('web.manufacturers');
    Route::resource('platforms', PlatformController::class)->except('show')->names('web.platforms');
    Route::resource('editions', EditionController::class)->except('show')->names('web.editions');

    // Perfil del usuario: datos de la cuenta y cambio de contraseña
    Route::get('/profile', [ProfileController::class, 'edit'])->name('web.profile.edit');
    Route::put('/profile', [ProfileController::class, 'updateInfo'])->name('web.profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('web.profile.password');
});