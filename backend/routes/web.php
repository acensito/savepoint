<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\GameController;

Route::get('/', [GameController::class, 'index'])->name('web.games.index');
Route::get('/games/create', [GameController::class, 'search'])->name('web.games.create');
Route::post('/games', [GameController::class, 'store'])->name('web.games.store');
Route::get('/games/{game}/edit', [GameController::class, 'edit'])->name('web.games.edit');
Route::put('/games/{game}', [GameController::class, 'update'])->name('web.games.update');
Route::delete('/games/{game}', [GameController::class, 'destroy'])->name('web.games.destroy');
Route::get('/games/search', [GameController::class, 'search'])->name('web.games.search');
Route::get('/games/create-manual', [GameController::class, 'createManual'])->name('web.games.create-manual');