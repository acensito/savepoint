<?php

namespace App\Jobs;

use App\Models\Game;
use App\Services\GameLookup\AgeRatingResolver;
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
 *
 * $assignBackground (issue #180, seguimiento de la auditoría de rendimiento
 * del 2026-09-10): GameController::store() también usaba esta misma lógica,
 * pero síncrona dentro de la petición de alta cuando el ajuste "Fondo
 * automático desde IGDB" está activo — hasta ~16s de latencia visible al
 * guardar (búsqueda + timeToBeat + artworks, cada una con su propio timeout).
 * Con esto en true, además de matchIfNeeded() se pide el primer artwork
 * disponible y se fija como fondo, igual que hacía
 * GameController::autoAssignIgdbBackground() antes de moverse aquí.
 */
class MatchGameWithIgdb implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $gameId,
        public readonly bool $assignBackground = false,
    ) {}

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

        (new IgdbGameMatcher($igdbLookup, new AgeRatingResolver))->matchIfNeeded($game);

        if (! $this->assignBackground || $game->igdb_id === null) {
            return;
        }

        $artworkId = $igdbLookup->artworks($game->igdb_id)[0] ?? null;

        if ($artworkId !== null) {
            $game->update(['igdb_background' => $artworkId]);
        }
    }
}
