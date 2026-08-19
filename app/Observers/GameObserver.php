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
    public function saved(Game $game): void
    {
        $this->forget($game);
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
