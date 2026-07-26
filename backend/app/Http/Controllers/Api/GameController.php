<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Http\Resources\Api\GameResource;
use Illuminate\Http\Request;
use App\Http\Requests\Api\StoreGameRequest;
use App\Http\Requests\Api\UpdateGameRequest;

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

    /**
     * Guarda un nuevo juego en la base de datos.
     */
    public function store(StoreGameRequest $request)
    {
        // Si el código llega aquí, significa que StoreGameRequest ya ha validado todo.
        // Solo cogemos los datos validados (evitamos que nos inyecten campos maliciosos).
        $validatedData = $request->validated();

        // Como todavía no tenemos el login hecho, vamos a asignar el juego 
        // temporalmente al usuario de prueba (ID 1) que creaste en el Seeder.
        $validatedData['user_id'] = 1; 

        // Creamos el juego en la base de datos
        $game = Game::create($validatedData);

        // Devolvemos el juego recién creado pasándolo por nuestro Resource
        return new GameResource($game);
    }

    /**
     * Actualiza un juego existente.
     */
    public function update(UpdateGameRequest $request, Game $game)
    {
        // Actualizamos el juego con los datos que hayan pasado la validación
        $game->update($request->validated());

        // Devolvemos el juego actualizado, pasado de nuevo por el Resource
        return new GameResource($game);
    }

    /**
     * Elimina un juego (Soft Delete).
     */
    public function destroy(Game $game)
    {
        // Gracias al trait SoftDeletes que pusimos en el modelo, 
        // esto no lo borra de Postgres, solo rellena la columna 'deleted_at'
        $game->delete();

        return response()->json([
            'message' => 'Juego eliminado correctamente'
        ]);
    }
}