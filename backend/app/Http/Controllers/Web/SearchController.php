<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Game;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * La paleta de búsqueda rápida (Ctrl+K) solo necesita un puñado de
     * coincidencias para saltar directamente a un juego, no el listado
     * completo con paginación.
     */
    private const MAX_RESULTS = 8;

    /**
     * Resultados en vivo para la búsqueda rápida (Ctrl+K): mismo criterio de
     * coincidencia que el buscador de la colección (título o EAN exacto),
     * acotado al usuario autenticado. Devuelve un fragmento Blade (no JSON)
     * para poder reutilizar los mismos componentes de carátula/chip/estrellas
     * que el resto de la app.
     */
    public function quick(Request $request)
    {
        $query = trim((string) $request->input('q', ''));

        $games = $query === ''
            ? collect()
            : Game::where('user_id', auth()->id())
                ->select(['id', 'title', 'cover', 'platform_id', 'rating', 'price_paid'])
                ->with([
                    'platform:id,name,label,bg_color,text_color,border_color,manufacturer_id',
                    'platform.manufacturer:id,bg_color,text_color,border_color',
                ])
                ->where(function ($q) use ($query) {
                    $q->whereLike('title', '%' . $query . '%', caseSensitive: false)
                        ->orWhere('ean', $query);
                })
                ->orderBy('title')
                ->limit(self::MAX_RESULTS)
                ->get();

        return view('games._quick-search-results', compact('games', 'query'));
    }
}