<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Game;
use Illuminate\View\View;

class ForSaleController extends Controller
{
    /**
     * Juegos marcados "en venta" (GameController::quickUpdate), en su propia
     * página para poder darles mantenimiento (quitarlos de venta, marcarlos
     * como vendidos) sin tener que filtrar la colección principal cada vez.
     * Ajustes > "Ocultar de la colección" decide si además desaparecen del
     * listado sin filtrar de ahí (ver GameCollectionQuery::query());
     * aquí siempre se ven, es la vía pensada justo para eso.
     */
    public function index(): View
    {
        $games = Game::where('user_id', auth()->id())
            ->where('for_sale', true)
            ->with([
                'platform:id,name,label,bg_color,text_color,border_color,manufacturer_id',
                'platform.manufacturer:id,bg_color,text_color,border_color',
                'edition:id,name',
            ])
            ->orderBy('title')
            ->get();

        return view('for-sale.index', compact('games'));
    }
}
