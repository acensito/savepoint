<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Platform;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Papelera de reciclaje de juegos, separada de GameController igual que ya
 * se hizo con IgdbController: acciones propias de un concepto (juegos
 * borrados), no CRUD del juego en uso. Mismo patrón que
 * WishlistController/ForSaleController/SalesController: index() lista "su"
 * sección de la colección.
 */
class GameTrashController extends Controller
{
    /**
     * Papelera: juegos borrados (soft delete) del usuario autenticado, con
     * búsqueda por título/EAN y filtro por plataforma (?q=, ?platform_id=),
     * igual que el listado principal.
     */
    public function index(Request $request)
    {
        $query = trim((string) $request->input('q', ''));
        $platformId = (string) $request->input('platform_id', '');

        $games = Game::onlyTrashed()
            ->where('user_id', auth()->id())
            // Los juegos vendidos (ver SalesController::markAsSold()) también
            // son un borrado blando, pero tienen su propia página (/sales):
            // la papelera se queda solo para borrados accidentales, si no un
            // vistazo rápido a "lo borrado" se llenaría de ventas normales.
            ->where('status', '!=', 'sold')
            ->with([
                'platform:id,name,label,bg_color,text_color,border_color,manufacturer_id',
                'platform.manufacturer:id,bg_color,text_color,border_color',
            ])
            ->when($query !== '', function ($q) use ($query) {
                $q->where(function ($sub) use ($query) {
                    $sub->whereLike('title', '%'.$query.'%', caseSensitive: false)
                        ->orWhere('ean', $query);
                });
            })
            ->when($platformId !== '', fn ($q) => $q->where('platform_id', $platformId))
            ->orderByDesc('deleted_at')
            ->paginate(20)
            ->withQueryString();

        $platforms = Platform::orderBy('name')->get();

        return view('games.trash', compact('games', 'query', 'platformId', 'platforms'));
    }

    /**
     * Restaura un juego de la papelera.
     */
    public function restore(int $id)
    {
        $game = Game::onlyTrashed()->findOrFail($id);

        Gate::authorize('restore', $game);

        $game->restore();

        return redirect()->route('web.games.trash')->with('success', 'Juego restaurado correctamente.');
    }

    /**
     * Elimina un juego definitivamente, saltándose la papelera.
     */
    public function forceDelete(int $id)
    {
        $game = Game::onlyTrashed()->findOrFail($id);

        Gate::authorize('forceDelete', $game);

        if ($game->cover) {
            Storage::disk('public')->delete($game->cover);
        }

        $game->forceDelete();

        return redirect()->route('web.games.trash')->with('success', 'Juego eliminado definitivamente.');
    }
}
