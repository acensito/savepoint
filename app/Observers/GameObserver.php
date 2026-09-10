<?php

namespace App\Observers;

use App\Http\Controllers\Web\StatsController;
use App\Models\Game;
use Illuminate\Support\Facades\Cache;

/**
 * Invalida la caché de /stats (ver StatsController::cacheKey()) en cualquier
 * mutación de un juego por instancia (alta, edición, venta, papelera,
 * restaurar, borrado definitivo...), sin tener que acordarse de hacerlo a
 * mano en cada controlador que llama a $game->save()/delete()/restore().
 *
 * No cubre las acciones en bloque que mutan varios juegos con una query
 * directa (Game::whereIn(...)->update()/delete()): esas no disparan eventos
 * de Eloquent, así que GameController las invalida explícitamente.
 */
class GameObserver
{
    /**
     * Columnas que de verdad entran en algún cálculo de StatsController —
     * auditoría de rendimiento del 2026-09-10: antes saved() invalidaba con
     * CUALQUIER cambio, aunque fuera a un campo que ninguna estadística
     * mira (notes, manual_status, region, igdb_*...). Solo abrir la
     * wishlist ya dispara hasta 20 guardados de cex_current_price/
     * cex_checked_at (ver Jobs\FetchCexWishlistPrice) — con eso, la caché
     * de 15 min casi nunca llegaba a servirse de verdad.
     */
    private const STATS_RELEVANT_COLUMNS = [
        'price_paid', 'rating', 'platform_id', 'status', 'play_status',
        'purchase_date', 'release_date', 'genres', 'sold_at', 'sale_price',
    ];

    public function saved(Game $game): void
    {
        if ($game->wasRecentlyCreated || $game->wasChanged(self::STATS_RELEVANT_COLUMNS)) {
            $this->forget($game);
        }
    }

    public function deleted(Game $game): void
    {
        $this->forget($game);
    }

    public function restored(Game $game): void
    {
        $this->forget($game);
    }

    public function forceDeleted(Game $game): void
    {
        $this->forget($game);
    }

    private function forget(Game $game): void
    {
        Cache::forget(StatsController::cacheKey($game->user_id));
    }
}
