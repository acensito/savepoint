<?php

namespace App\Jobs;

use App\Http\Controllers\Web\GameAutoIdentifyController;
use App\Models\Game;
use App\Services\GameLookup\GameCoverMatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * Recorre, en segundo plano, los juegos sin carátula de una plataforma
 * concreta y busca un candidato de carátula/EAN en el catálogo externo (ver
 * GameCoverMatcher) para cada uno — "identificar en bloque", tipo Plex,
 * issue #128. Nunca aplica nada directamente: dado el riesgo real de un
 * match erróneo en bloque (mismo problema de fondo que el emparejamiento de
 * IGDB, issue #50), los candidatos quedan en caché bajo cacheKey($batchId),
 * a la espera de que el usuario confirme en bloque los que le parezcan bien
 * desde la cola de revisión (ver GameAutoIdentifyController).
 */
class IdentifyMissingGameCovers implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $userId,
        public readonly int $platformId,
        public readonly string $batchId,
    ) {}

    public function handle(GameCoverMatcher $matcher): void
    {
        $games = Game::where('user_id', $this->userId)
            ->where('platform_id', $this->platformId)
            ->whereNull('cover')
            ->get();

        $candidates = [];

        foreach ($games as $game) {
            $match = $game->ean !== null ? $matcher->matchByEan($game) : $matcher->matchByTitle($game);

            if ($match === null || $match->coverUrl === null) {
                // Sin candidato razonable (o sin carátula en el que hay): se
                // deja el juego tal cual, a la espera de una corrección
                // manual — no se marca de ninguna forma especial, así que una
                // pasada futura lo vuelve a intentar.
                continue;
            }

            $candidates[] = [
                'game_id' => $game->id,
                'title' => $game->title,
                'current_ean' => $game->ean,
                'proposed_ean' => $game->ean ?? $match->ean,
                'proposed_cover_url' => $match->coverUrl,
                'matched_title' => $match->title,
                'matched_platform' => $match->platform,
            ];

            // Cortesía con un servicio externo no oficial (ver
            // CexGameLookupService): sin esto, identificar una plataforma
            // con muchos juegos pendientes dispararía una ráfaga de
            // peticiones seguidas sin ninguna pausa entre ellas.
            usleep(200_000);
        }

        Cache::put(
            GameAutoIdentifyController::cacheKey($this->batchId),
            ['user_id' => $this->userId, 'done' => true, 'total' => $games->count(), 'candidates' => $candidates],
            GameAutoIdentifyController::cacheTtl(),
        );
    }
}
