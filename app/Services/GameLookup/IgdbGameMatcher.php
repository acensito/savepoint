<?php

namespace App\Services\GameLookup;

use App\Models\Game;

/**
 * Match automático de IGDB por título para un juego que todavía no se ha
 * intentado emparejar (games.igdb_matched_at null) — mismo mapeo de campos
 * usado tanto por GameController::autoAssignIgdbBackground() (síncrono, al
 * dar de alta un juego con ese ajuste activo) como por el Job
 * Jobs\MatchGameWithIgdb (asíncrono, al abrir la ficha de un juego), para no
 * duplicarlo entre los dos sitios.
 */
class IgdbGameMatcher
{
    public function __construct(
        private readonly IgdbLookupService $igdbLookup,
        private readonly AgeRatingResolver $ageRatingResolver,
    ) {}

    /**
     * developer/release_date/age_rating solo se rellenan si estaban vacíos
     * (nunca pisan lo que ya haya escrito el usuario a mano); igdb_genres/
     * igdb_rating/igdb_time_to_beat/igdb_age_ratings/igdb_id se sobrescriben
     * siempre porque son campos exclusivos de IGDB, sin equivalente manual
     * que proteger — igdb_age_ratings se guarda aunque age_rating ya
     * estuviera puesto a mano: es la lista cruda que trajo IGDB, informativa,
     * no pisa nada visible (el desplegable del formulario siempre ofrece
     * todas las combinaciones conocidas, no solo estas — IGDB puede
     * equivocarse, ver games/_form.blade.php e issue #46). Se marca
     * igdb_matched_at haya habido match o no, para no repetir la búsqueda
     * automática después.
     */
    public function matchIfNeeded(Game $game): void
    {
        if ($game->igdb_matched_at !== null) {
            return;
        }

        // limit 10, no 1: search() reordena por título exacto/plataforma
        // dentro de lo que devuelva IGDB (ver IgdbLookupService::search()),
        // así que hace falta margen para que esa prioridad sirva de algo.
        $matches = $this->igdbLookup->search($game->title, $game->platform?->name, limit: 10);

        // search() ya devuelve $matches ordenado de mejor a peor, pero no
        // expone la puntuación — se recalcula aquí (misma lógica, ver
        // IgdbLookupService::matchScore()) solo para saber si el primer y el
        // segundo puesto están empatados. Con empate real en la mejor
        // puntuación (p. ej. varios candidatos sin coincidencia exacta de
        // título y sin nota IGDB, indistinguibles entre sí) no hay forma
        // fiable de elegir uno solo automáticamente — mejor no arriesgarse a
        // quedarse con un bundle/DLC y dejar que el usuario elija a mano
        // desde la ficha (ver issue #50 e IgdbController::search()/apply()).
        $best = $matches[0] ?? null;
        $runnerUp = $matches[1] ?? null;
        $ambiguous = $best !== null && $runnerUp !== null
            && $this->igdbLookup->matchScore($runnerUp, $game->title, $game->platform?->name)
                === $this->igdbLookup->matchScore($best, $game->title, $game->platform?->name);

        $match = $ambiguous ? null : $best;

        // Petición aparte (ver IgdbLookupService::timeToBeat()): solo tiene
        // sentido pedirla si ya se sabe el id de IGDB del juego.
        $timeToBeat = $match !== null ? $this->igdbLookup->timeToBeat($match->igdbId) : null;

        $game->fill([
            'developer' => $game->developer ?: $match?->developer,
            'release_date' => $game->release_date ?: $match?->releaseDate,
            'age_rating' => $game->age_rating ?: $this->ageRatingResolver->pick($match?->ageRatings, $game->region),
            'igdb_id' => $match?->igdbId,
            'igdb_genres' => $match?->genres,
            'igdb_rating' => $match?->rating,
            'igdb_time_to_beat' => $timeToBeat,
            'igdb_age_ratings' => $match?->ageRatings,
            'igdb_matched_at' => now(),
            'igdb_match_ambiguous' => $ambiguous,
        ]);

        $game->save();
    }
}
