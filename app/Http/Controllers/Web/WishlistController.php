<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Edition;
use App\Models\Game;
use App\Models\Platform;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WishlistController extends Controller
{
    /**
     * Columnas por las que se puede ordenar desde ?sort= (mismo motivo que
     * GameController::SORTABLE_COLUMNS: no pasar un nombre de columna
     * arbitrario del usuario directamente a orderBy()).
     */
    private const SORTABLE_COLUMNS = [
        'title' => 'title',
        'wishlist_priority' => 'wishlist_priority',
        'wishlist_estimated_price' => 'wishlist_estimated_price',
    ];

    /**
     * Lista de deseos del usuario: juegos con Propiedad = "wishlist", con
     * búsqueda por título/EAN y orden (por defecto, prioridad ascendente:
     * "alta" primero). Página aparte de la colección principal porque los
     * campos relevantes (prioridad, precio estimado, dónde comprarlo) no
     * tienen sentido para un juego ya comprado, y porque un juego deseado
     * todavía no es parte de "tu colección" (GameController::index lo
     * excluye siempre).
     */
    public function index(Request $request): View
    {
        $query = trim((string) $request->input('q', ''));
        $sort = (string) $request->input('sort', '');
        $dir = $request->input('dir') === 'desc' ? 'desc' : 'asc';
        $sortColumn = self::SORTABLE_COLUMNS[$sort] ?? 'wishlist_priority';

        $games = Game::where('user_id', auth()->id())
            ->where('status', 'wishlist')
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
            ->orderBy($sortColumn, $dir)
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('wishlist.index', compact('games', 'query', 'sort', 'dir'));
    }

    /**
     * Alta rápida en la wishlist: formulario reducido (solo título,
     * plataforma y edición) en vez del formulario completo de siempre, ya
     * que el resto de datos (precio, conservación, manual...) no tienen
     * sentido para un juego que todavía no se tiene.
     */
    public function create(): View
    {
        $platforms = Platform::orderBy('name')->get();
        $editions = Edition::with('platforms')->orderBy('name')->get();

        return view('wishlist.create', compact('platforms', 'editions'));
    }

    /**
     * Guarda el alta rápida. Propiedad y estado de juego se fijan aquí (no
     * los pide el formulario): "wishlist" y "pending" respectivamente, para
     * satisfacer la misma validación que exige play_status en el resto de
     * la app.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'platform_id' => 'nullable|exists:platforms,id',
            'edition_id' => 'nullable|exists:editions,id',
            'wishlist_priority' => 'nullable|integer|min:1|max:3',
            // max:99999999.99: tope real de la columna decimal(10,2) — ver
            // GameController::validated() para el mismo motivo.
            'wishlist_estimated_price' => 'nullable|numeric|min:0|max:99999999.99',
            'wishlist_store' => 'nullable|string|max:255',
        ]);

        Game::create([
            ...$validated,
            'user_id' => auth()->id(),
            'status' => 'wishlist',
            'play_status' => 'pending',
        ]);

        return redirect()->route('web.wishlist.index')->with('success', 'Juego añadido a tu lista de deseos.');
    }
}
