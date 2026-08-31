<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreGameRequest;
use App\Http\Requests\Api\UpdateGameRequest;
use App\Http\Resources\Api\GameResource;
use App\Models\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class GameController extends Controller
{
    /**
     * Muestra una lista paginada de los juegos, con los mismos filtros que el
     * listado web (?q=, ?platform_id=, ?play_status=, ?status=).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        // Tope de 100 para que nadie pida una página gigante; 20 por defecto,
        // igual que el listado web.
        $perPage = min((int) $request->integer('per_page', 20), 100);
        $perPage = $perPage > 0 ? $perPage : 20;

        $query = trim((string) $request->input('q', ''));
        $platformId = (string) $request->input('platform_id', '');
        $playStatus = (string) $request->input('play_status', '');
        $status = (string) $request->input('status', '');

        // Solo los juegos del usuario autenticado, cargando 'platform' de golpe para optimizar
        $games = Game::where('user_id', auth()->id())
            ->with('platform')
            ->when($query !== '', function ($q) use ($query) {
                $q->where(function ($sub) use ($query) {
                    $sub->whereLike('title', '%'.$query.'%', caseSensitive: false)
                        ->orWhere('ean', $query);
                });
            })
            ->when($platformId !== '', fn ($q) => $q->where('platform_id', $platformId))
            ->when($playStatus !== '', fn ($q) => $q->where('play_status', $playStatus))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        // Al pasarle un paginador, el Resource añade 'links' y 'meta' con la
        // info de paginación al JSON automáticamente.
        return GameResource::collection($games);
    }

    /**
     * Muestra un juego concreto.
     */
    public function show(Game $game): GameResource
    {
        Gate::authorize('view', $game);

        return new GameResource($game->load('platform'));
    }

    /**
     * Guarda un nuevo juego en la base de datos.
     */
    public function store(StoreGameRequest $request): GameResource
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
    public function update(UpdateGameRequest $request, Game $game): GameResource
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
    public function destroy(Game $game): JsonResponse
    {
        Gate::authorize('delete', $game);

        // Gracias al trait SoftDeletes que pusimos en el modelo,
        // esto no lo borra de Postgres, solo rellena la columna 'deleted_at'
        $game->delete();

        return response()->json([
            'message' => 'Juego eliminado correctamente',
        ]);
    }
}
