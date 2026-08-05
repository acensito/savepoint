<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Services\GameLookup\GameLookupInterface;
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
     * Por debajo de esta longitud no merece la pena consultar el catálogo
     * externo: recorta las primeras pulsaciones mientras el usuario todavía
     * está escribiendo un título (un EAN escaneado siempre la supera de
     * sobra, son 8+ dígitos).
     */
    private const MIN_EXTERNAL_QUERY_LENGTH = 3;

    public function __construct(private readonly GameLookupInterface $gameLookup)
    {
    }

    /**
     * Resultados en vivo para la búsqueda rápida (Ctrl+K): mismo criterio de
     * coincidencia que el buscador de la colección (título o EAN exacto),
     * acotado al usuario autenticado. Devuelve un fragmento Blade (no JSON)
     * para poder reutilizar los mismos componentes de carátula/chip/estrellas
     * que el resto de la app.
     *
     * Cuando el juego no está en la colección, se ofrecen además sugerencias
     * de un catálogo externo (ver GameLookupInterface) para autorrellenar el
     * alta: es una ayuda opcional, nunca sustituye a "dar de alta a mano".
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

        // Un código escaneado (o tecleado) que parece un EAN/UPC real, para
        // decidir qué campo del alta de juego rellenar cuando no hay
        // resultados: EAN si parece un código de barras, título en cualquier
        // otro caso.
        $isEan = ctype_digit($query) && strlen($query) >= 8;

        $externalResults = $games->isEmpty() && mb_strlen($query) >= self::MIN_EXTERNAL_QUERY_LENGTH
            ? $this->gameLookup->search($query)
            : [];

        return view('games._quick-search-results', compact('games', 'query', 'isEan', 'externalResults'));
    }
}