<?php

namespace App\Jobs;

use App\Models\Game;
use App\Services\GameLookup\IgdbGameMatcher;
use App\Services\GameLookup\IgdbLookupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Match automático de IGDB de un juego recién abierto en su ficha (ver
 * GameController::show()), que antes se hacía en línea con una llamada HTTP
 * externa síncrona que bloqueaba la primera carga de cada ficha. Se
 * despacha solo si el juego no se ha intentado emparejar todavía
 * (games.igdb_matched_at null); si IGDB no encuentra nada o el usuario no
 * tiene IGDB activado, igual se marca como intentado para no repetirlo en
 * cada visita (ver IgdbGameMatcher).
 */
class MatchGameWithIgdb implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $gameId,
    ) {
    }

    public function handle(): void
    {
        $game = Game::find($this->gameId);

        if ($game === null) {
            return;
        }

        // Construido a mano con IgdbLookupService::forUser(), no inyectado
        // por el contenedor (ver el bind de AppServiceProvider, que resuelve
        // por auth()->user()): el worker de cola no tiene sesión ni petición
        // HTTP en curso, así que las credenciales de IGDB tienen que ser
        // explícitamente las del dueño del juego.
        $igdbLookup = IgdbLookupService::forUser($game->user);

        (new IgdbGameMatcher($igdbLookup))->matchIfNeeded($game);
    }
}
