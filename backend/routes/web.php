<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\GameController;

Route::get('/', [GameController::class, 'index'])->name('games.index');