<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Services\GameLookup\AgeRatingResolver;
use App\Services\GameLookup\IgdbGameMatch;
use App\Services\GameLookup\IgdbLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Acciones de IGDB sobre un juego ya guardado, separadas de GameController
 * (que se estaba quedando con demasiadas responsabilidades: CRUD, IGDB, CEX,
 * papelera y acciones en bloque mezcladas — ver README, "Mejoras técnicas").
 * El match automático de la ficha (GameController::show()) vive aparte, en
 * Jobs\MatchGameWithIgdb: aquí solo las acciones explícitas del usuario
 * (buscar/corregir/elegir fondo).
 */
class IgdbController extends Controller
{
    public function __construct(
        private readonly IgdbLookupService $igdbLookup,
        private readonly AgeRatingResolver $ageRatingResolver,
    ) {}

    /**
     * Búsqueda manual en IGDB desde la ficha de un juego, para corregir el
     * resultado automático (Jobs\MatchGameWithIgdb) cuando no es el correcto
     * (remaster distinto, plataforma equivocada, sin match...). Solo lista
     * candidatos, no cambia nada todavía (ver apply()).
     */
    public function search(Request $request, Game $game): JsonResponse
    {
        Gate::authorize('update', $game);

        $query = trim((string) $request->query('q', ''));
        if ($query === '') {
            $query = $game->title;
        }

        $results = collect($this->igdbLookup->search($query, $game->platform?->name, limit: 8))
            ->map(fn (IgdbGameMatch $match) => [
                'igdb_id' => $match->igdbId,
                'title' => $match->title,
                'platforms' => $match->platforms,
                'developer' => $match->developer,
                'release_date' => $match->releaseDate,
                'genres' => $match->genres,
                'rating' => $match->rating,
                'age_ratings' => $match->ageRatings,
            ])
            ->values();

        return response()->json(['results' => $results]);
    }

    /**
     * Aplica a mano el resultado de IGDB elegido en search(): a diferencia
     * del match automático (que solo rellena developer/release_date/
     * age_rating si estaban vacíos), aquí sí se sobrescriben porque es una
     * corrección explícita del usuario — salvo que el resultado elegido no
     * traiga ese dato, en cuyo caso se conserva el que ya hubiera para no
     * borrar algo bueno con un match peor. age_rating se elige entre las
     * clasificaciones del resultado según la región del juego (ver
     * AgeRatingResolver, issue #46).
     *
     * También sirve como mecanismo de "reemparejar" para refrescar campos
     * IGDB añadidos después del primer match automático (ver
     * IgdbGameMatcher::matchIfNeeded(), que solo corre una vez por juego):
     * el usuario corrige/reconfirma aquí y se reaplican todos los campos de
     * IGDB, no solo los que existían cuando se emparejó por primera vez
     * (issue #49).
     */
    public function apply(Request $request, Game $game): RedirectResponse
    {
        Gate::authorize('update', $game);

        $validated = $request->validate([
            'igdb_id' => 'required|integer',
            'developer' => 'nullable|string',
            'release_date' => 'nullable|date',
            'genres' => 'nullable|array',
            'genres.*' => 'string',
            'rating' => 'nullable|numeric',
            // JSON-encoded por el JS de show.blade.php antes de enviar (ver
            // applyResult()): un <input type="hidden"> no puede llevar un
            // array anidado {organization, value} tal cual.
            'age_ratings' => 'nullable|string',
        ]);

        $ageRatings = json_decode($validated['age_ratings'] ?? '', true);
        $ageRatings = is_array($ageRatings) && $ageRatings !== [] ? $ageRatings : null;

        $game->update([
            'developer' => $validated['developer'] ?? $game->developer,
            'release_date' => $validated['release_date'] ?? $game->release_date,
            'age_rating' => $this->ageRatingResolver->pick($ageRatings, $game->region) ?? $game->age_rating,
            'igdb_id' => $validated['igdb_id'],
            'igdb_genres' => $validated['genres'] ?? null,
            'igdb_rating' => $validated['rating'] ?? null,
            // Petición aparte (ver IgdbGameMatcher e IgdbLookupService::
            // timeToBeat()): solo tiene sentido pedirla ya con un igdb_id
            // concreto, no para los hasta 8 candidatos de search().
            'igdb_time_to_beat' => $this->igdbLookup->timeToBeat($validated['igdb_id']),
            'igdb_age_ratings' => $ageRatings,
            'igdb_matched_at' => now(),
        ]);

        return redirect()->route('web.games.show', $game)->with('success', 'Datos de IGDB actualizados.');
    }

    /**
     * Arte promocional de IGDB para elegir como fondo de la ficha (botón
     * "Elegir fondo"): a diferencia de search(), no busca por título, pide
     * directamente el arte del juego ya identificado (games.igdb_id). Sin
     * match todavía, no hay nada que ofrecer.
     */
    public function artworks(Game $game): JsonResponse
    {
        Gate::authorize('update', $game);

        if ($game->igdb_id === null) {
            return response()->json(['results' => []]);
        }

        $results = collect($this->igdbLookup->artworks($game->igdb_id))
            ->map(fn (string $imageId) => [
                'image_id' => $imageId,
                'thumb_url' => "https://images.igdb.com/igdb/image/upload/t_screenshot_med/{$imageId}.jpg",
            ])
            ->values();

        return response()->json(['results' => $results]);
    }

    /**
     * Fija (o quita, con image_id vacío) el fondo entre las opciones de
     * artworks(): siempre disponible como elección explícita del usuario,
     * tanto si el ajuste "Fondo automático" (ver PanelController::
     * updateSettings) está activo como si no.
     */
    public function setBackground(Request $request, Game $game): RedirectResponse
    {
        Gate::authorize('update', $game);

        // Los image_id de IGDB son siempre alfanuméricos en minúscula (ver
        // ejemplos en IgdbControllerTest/IgdbLookupServiceTest, p. ej.
        // "ar1abc"). Sin esta regex, un image_id con comilla simple se
        // interpolaba tal cual en el atributo `style` de games/show.blade.php
        // (Game::backgroundUrl()) y permitía inyectar CSS: Blade escapa la
        // comilla a su entidad HTML, pero el navegador la decodifica antes
        // de parsear `style` como CSS, así que la entidad no protege nada ahí.
        $validated = $request->validate([
            'image_id' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9]+$/'],
        ]);

        $game->update(['igdb_background' => $validated['image_id'] ?? null]);

        return redirect()->route('web.games.show', $game)
            ->with('success', $validated['image_id'] ? 'Fondo actualizado.' : 'Fondo quitado.');
    }
}
