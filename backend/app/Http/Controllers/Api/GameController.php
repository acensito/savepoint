<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Http\Resources\Api\GameResource;
use Illuminate\Http\Request;
use App\Http\Requests\Api\StoreGameRequest;
use App\Http\Requests\Api\UpdateGameRequest;
use Illuminate\Support\Facades\Gate;

class GameController extends Controller
{
    /**
     * Muestra una lista de los juegos.
     */
    public function index()
    {
        // Solo los juegos del usuario autenticado, cargando 'platform' de golpe para optimizar
        $games = Game::where('user_id', auth()->id())->with('platform')->latest()->get();

        // Devolvemos la colección pasada por el "filtro" de nuestro Resource
        return GameResource::collection($games);
    }

    /**
     * Muestra un juego concreto.
     */
    public function show(Game $game)
    {
        Gate::authorize('view', $game);

        return new GameResource($game->load('platform'));
    }

    /**
     * Guarda un nuevo juego en la base de datos.
     */
    public function store(StoreGameRequest $request)
    {
        // Si el código llega aquí, significa que StoreGameRequest ya ha validado todo.
        // Solo cogemos los datos validados (evitamos que nos inyecten campos maliciosos).
        $validatedData = $request->validated();

        $validatedData['user_id'] = $request->user()->id;

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
        Gate::authorize('update', $game);

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
        Gate::authorize('delete', $game);

        // Gracias al trait SoftDeletes que pusimos en el modelo,
        // esto no lo borra de Postgres, solo rellena la columna 'deleted_at'
        $game->delete();

        return response()->json([
            'message' => 'Juego eliminado correctamente'
        ]);
    }
}