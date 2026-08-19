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
    ) {
    }

    /**
     * developer/release_date solo se rellenan si estaban vacíos (nunca pisan
     * lo que ya haya escrito el usuario a mano); igdb_genres/igdb_rating/
     * igdb_id se sobrescriben siempre porque son campos exclusivos de IGDB,
     * sin equivalente manual que proteger. Se marca igdb_matched_at haya
     * habido match o no, para no repetir la búsqueda automática después.
     */
    public function matchIfNeeded(Game $game): void
    {
        if ($game->igdb_matched_at !== null) {
            return;
        }

        // limit 10, no 1: search() reordena por título exacto/plataforma
        // dentro de lo que devuelva IGDB (ver IgdbLookupService::search()),
        // así que hace falta margen para que esa prioridad sirva de algo.
        $match = $this->igdbLookup->search($game->title, $game->platform?->name, limit: 10)[0] ?? null;

        $game->fill([
            'developer' => $game->developer ?: $match?->developer,
            'release_date' => $game->release_date ?: $match?->releaseDate,
            'igdb_id' => $match?->igdbId,
            'igdb_genres' => $match?->genres,
            'igdb_rating' => $match?->rating,
            'igdb_matched_at' => now(),
        ]);

        $game->save();
    }
}
