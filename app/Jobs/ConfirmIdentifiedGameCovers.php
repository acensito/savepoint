<?php

namespace App\Jobs;

use App\Http\Controllers\Web\GameAutoIdentifyController;
use App\Models\Game;
use App\Services\GameLookup\ExternalCoverDownloader;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * Aplica en segundo plano los candidatos que el usuario ha confirmado en la
 * cola de revisión del identificador en bloque (ver
 * GameAutoIdentifyController::confirm(), issue #128) — antes se descargaba
 * cada carátula dentro de la propia petición web; confirmar un lote grande
 * (50+ candidatos) podía tardar minutos y arriesgarse al timeout de
 * nginx/PHP-FPM a mitad, dejando algunas carátulas aplicadas y otras no sin
 * que el usuario supiera cuáles (auditoría de rendimiento del 2026-09-10).
 */
class ConfirmIdentifiedGameCovers implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public int $tries = 1;

    /**
     * @param  array<int, array{game_id: int, proposed_cover_url: string|null, proposed_ean: string|null}>  $candidates
     */
    public function __construct(
        public readonly int $userId,
        public readonly string $batchId,
        public readonly array $candidates,
    ) {}

    public function handle(ExternalCoverDownloader $coverDownloader): void
    {
        $total = count($this->candidates);
        $applied = 0;
        $processed = 0;

        // whereIn + keyBy, no find() por candidato: una sola query para
        // todo el lote en vez de una por juego.
        $games = Game::where('user_id', $this->userId)
            ->whereIn('id', array_column($this->candidates, 'game_id'))
            ->get()
            ->keyBy('id');

        foreach ($this->candidates as $candidate) {
            $game = $games->get($candidate['game_id']);

            if ($game !== null && $candidate['proposed_cover_url'] !== null) {
                $cover = $coverDownloader->download($candidate['proposed_cover_url']);

                if ($cover !== null) {
                    $game->update([
                        'cover' => $cover,
                        'ean' => $game->ean ?? $candidate['proposed_ean'],
                    ]);
                    $applied++;
                }
            }

            $processed++;

            if ($processed % 5 === 0) {
                $this->saveProgress($total, $processed, $applied, done: false);
            }
        }

        $this->saveProgress($total, $processed, $applied, done: true);
    }

    private function saveProgress(int $total, int $processed, int $applied, bool $done): void
    {
        Cache::put(
            GameAutoIdentifyController::cacheKey($this->batchId),
            [
                'user_id' => $this->userId,
                'phase' => 'confirm',
                'done' => $done,
                'total' => $total,
                'processed' => $processed,
                'applied' => $applied,
            ],
            GameAutoIdentifyController::cacheTtl(),
        );
    }
}
