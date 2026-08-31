<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Game;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SalesController extends Controller
{
    /**
     * Histórico de juegos vendidos (ver markAsSold() más abajo), agrupado
     * por año de venta. Un juego vendido es un borrado blando con
     * status=sold, así que se lee con onlyTrashed() en vez de la colección
     * normal (que ya no lo verá nunca).
     */
    public function index(): View
    {
        $games = Game::onlyTrashed()
            ->where('user_id', auth()->id())
            ->where('status', 'sold')
            ->whereNotNull('sold_at')
            ->with([
                'platform:id,name,label,bg_color,text_color,border_color,manufacturer_id',
                'platform.manufacturer:id,bg_color,text_color,border_color',
                'edition:id,name',
            ])
            ->orderByDesc('sold_at')
            ->get();

        $byYear = $games
            ->groupBy(fn (Game $game) => $game->sold_at->format('Y'))
            ->sortKeysDesc()
            ->map(function ($yearGames) {
                $paid = (float) $yearGames->sum('price_paid');
                $sold = (float) $yearGames->sum('sale_price');
                $profit = $sold - $paid;

                return [
                    'games' => $yearGames,
                    'count' => $yearGames->count(),
                    'paid' => $paid,
                    'sold' => $sold,
                    'profit' => $profit,
                    'profit_percent' => $paid > 0 ? round($profit / $paid * 100, 1) : null,
                ];
            });

        return view('sales.index', compact('byYear'));
    }

    /**
     * Deshace una venta: restaura el juego de la papelera y limpia los datos
     * de la venta, no solo el soft delete (si no, volvería a la colección
     * marcado como vendido y con precio de venta puestos).
     */
    public function restore(int $id): RedirectResponse
    {
        $game = Game::onlyTrashed()->findOrFail($id);

        Gate::authorize('restore', $game);

        $game->restore();
        $game->update([
            'status' => 'owned',
            'for_sale' => false,
            'sale_price' => null,
            'sold_at' => null,
        ]);

        return redirect()->route('web.sales.index')->with('success', 'Venta deshecha: el juego ha vuelto a tu colección.');
    }

    /**
     * Marca un juego como vendido: pide precio y fecha de venta (a diferencia
     * del resto de Propiedad, "Vendido" ya no es una opción libre del
     * desplegable del formulario, ver games/_form.blade.php) y lo envía a la
     * papelera igual que GameController::destroy() — recuperable desde
     * /sales si el usuario se equivoca, no un borrado a lo bruto. Movido
     * aquí desde GameController: es la otra mitad de restore() de arriba,
     * el ciclo completo de una venta vive en un único controlador.
     */
    public function markAsSold(Request $request, Game $game): RedirectResponse
    {
        Gate::authorize('update', $game);

        $validated = $request->validate([
            // max:99999999.99: tope real de la columna decimal(10,2) — ver
            // GameController::validated() para el mismo motivo.
            'sale_price' => 'required|numeric|min:0|max:99999999.99',
            'sold_at' => 'required|date',
            'notes' => 'sometimes|nullable|string',
        ]);

        $id = $game->id;
        $title = $game->title;

        $game->update([
            ...$validated,
            'status' => 'sold',
            'for_sale' => false,
        ]);
        $game->delete();

        return redirect()->route('web.games.index')
            ->with('success', "«{$title}» marcado como vendido.")
            ->with('undoUrl', route('web.sales.restore', $id));
    }
}
