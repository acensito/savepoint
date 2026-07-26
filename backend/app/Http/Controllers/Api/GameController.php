<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Http\Resources\Api\GameResource;
use Illuminate\Http\Request;

class GameController extends Controller
{
    /**
     * Muestra una lista de los juegos.
     */
    public function index()
    {
        // Traemos todos los juegos, cargando la relación 'platform' de golpe para optimizar
        $games = Game::with('platform')->latest()->get();

        // Devolvemos la colección pasada por el "filtro" de nuestro Resource
        return GameResource::collection($games);
    }

    // Aquí irían el resto de métodos (store, show, update, destroy)...
}