<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Game;
use Illuminate\Http\Request;

class GameController extends Controller
{
    public function index()
    {
        // Cargamos los juegos y su plataforma para evitar consultas N+1
        $games = Game::with('platform')->latest()->get();
        
        // Devolvemos una vista Blade pasándole la variable $games
        return view('games.index', compact('games'));
    }
}