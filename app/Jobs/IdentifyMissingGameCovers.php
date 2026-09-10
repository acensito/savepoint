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

    /**
     * Cada juego sin EAN implica una llamada HTTP a CEX (hasta 4s de
     * timeout) más una pausa de cortesía de 200ms — una plataforma con
     * varios cientos de juegos pendientes puede tardar varios minutos. El
     * timeout por defecto de Laravel (60s) se quedaba corto: un run real de
     * 107 juegos ya tardó 29s (auditoría de rendimiento del 2026-09-10).
     */
    public int $timeout = 1800;

    /**
     * Un solo intento a propósito: si Redis considerara "perdido" el job
     * por tardar más que retry_after (config('queue.connections.redis.
     * retry_after'), 90s) y lo redistribuyera mientras el original sigue
     * vivo, este $tries=1 hace que esa segunda entrega se descarte sin
     * ejecutar handle() otra vez — sin esto, una plataforma grande podría
     * disparar dos ráfagas completas contra CEX a la vez.
     */
    public int $tries = 1;

    public function __construct(
        public readonly int $userId,
        public readonly int $platformId,
        public readonly string $batchId,
    ) {}

    public function handle(GameCoverMatcher $matcher): void
    {
        $base = Game::where('user_id', $this->userId)
            ->where('platform_id', $this->platformId)
            ->where('status', '!=', 'wishlist')
            ->whereNull('cover');

        $total = (clone $base)->count();
        $candidates = [];
        $unmatched = [];
        $processed = 0;

        // with('platform'): todos los juegos del lote comparten la misma
        // plataforma (ya filtrada arriba), así que sale en una sola query
        // aparte en vez de una por juego al acceder a $game->platform dentro
        // de GameCoverMatcher::matchByTitle(). lazyById(), no get(): no hace
        // falta cargar en memoria de golpe una plataforma con cientos de
        // juegos pendientes.
        foreach ($base->with('platform')->lazyById() as $game) {
            $match = $game->ean !== null ? $matcher->matchByEan($game) : $matcher->matchByTitle($game);

            if ($match === null || $match->coverUrl === null) {
                // Sin candidato razonable (o sin carátula en el que hay): se
                // deja el juego tal cual, a la espera de una corrección
                // manual — no se marca de ninguna forma especial, así que una
                // pasada futura lo vuelve a intentar. Se apunta aquí (issue
                // #128, seguimiento del 2026-09-10) para que la cola de
                // revisión pueda enlazarlo directamente en vez de perderlo
                // de vista en cuanto se confirma el lote.
                $unmatched[] = ['game_id' => $game->id, 'title' => $game->title];
                $processed++;

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
            $processed++;

            // Cortesía con un servicio externo no oficial (ver
            // CexGameLookupService): sin esto, identificar una plataforma
            // con muchos juegos pendientes dispararía una ráfaga de
            // peticiones seguidas sin ninguna pausa entre ellas.
            usleep(200_000);

            // Progreso real cada pocos juegos, no solo al terminar: sin
            // esto, una plataforma grande deja el sondeo del navegador (ver
            // initAutoIdentifyStatusPolling en app.js) viendo "done: false"
            // sin ningún indicio de avance durante minutos, y si el job
            // muriera a mitad no quedaría ni rastro de lo ya encontrado.
            if ($processed % 5 === 0) {
                $this->saveProgress($total, $processed, $candidates, $unmatched, done: false);
            }
        }

        $this->saveProgress($total, $processed, $candidates, $unmatched, done: true);
    }

    /**
     * @param  array<int, array<string, mixed>>  $candidates
     * @param  array<int, array{game_id: int, title: string}>  $unmatched
     */
    private function saveProgress(int $total, int $processed, array $candidates, array $unmatched, bool $done): void
    {
        Cache::put(
            GameAutoIdentifyController::cacheKey($this->batchId),
            [
                'user_id' => $this->userId,
                'phase' => 'identify',
                'done' => $done,
                'total' => $total,
                'processed' => $processed,
                'candidates' => $candidates,
                'unmatched' => $unmatched,
            ],
            GameAutoIdentifyController::cacheTtl(),
        );
    }
}
