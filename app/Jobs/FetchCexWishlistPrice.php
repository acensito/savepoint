<?php

namespace App\Jobs;

use App\Models\Game;
use App\Services\GameLookup\CexGameLookupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Consulta el precio actual en CEX de un juego de la lista de deseos, al
 * abrirla (mismo motivo que Jobs\MatchGameWithIgdb: no bloquear la carga con
 * una llamada HTTP externa síncrona). Se despacha solo si no se ha
 * consultado nunca o hace más de STALE_AFTER_DAYS (ver
 * WishlistController::index()) — CEX no cambia sus precios a diario (ver el
 * campo priceLastChanged de su propio índice), así que no hace falta
 * refrescar en cada visita.
 */
class FetchCexWishlistPrice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const int STALE_AFTER_DAYS = 3;

    public function __construct(
        public readonly int $gameId,
    ) {}

    public function handle(CexGameLookupService $cexLookup): void
    {
        $game = Game::find($this->gameId);

        if ($game === null) {
            return;
        }

        $match = $cexLookup->currentPrice($game->title, $game->platform?->name);

        // Se marca cex_checked_at igual sin coincidencia, para no
        // reintentarlo en cada visita hasta que pase STALE_AFTER_DAYS (mismo
        // criterio que igdb_matched_at en IgdbGameMatcher).
        $game->update([
            'cex_current_price' => $match?->price,
            'cex_checked_at' => now(),
        ]);
    }
}
