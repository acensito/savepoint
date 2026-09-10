<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Game;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Acciones en bloque sobre varios juegos a la vez desde el listado,
 * separadas de GameController igual que ya se hizo con IgdbController:
 * acciones propias de un concepto (varios juegos de golpe), no CRUD de uno.
 */
class GameBulkActionController extends Controller
{
    /**
     * Envía a la papelera de golpe todos los juegos seleccionados en el listado.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $ids = $this->ownedSelectedIds($request);

        Game::whereIn('id', $ids)->delete();

        // Mass delete por query builder: no dispara el evento 'deleted' de
        // Eloquent, así que GameObserver no la ve. Los IDs ya están acotados
        // al usuario autenticado (ver ownedSelectedIds()).
        Cache::forget(StatsController::cacheKey(auth()->id()));

        return redirect()->route('web.games.index')->with(
            'success',
            count($ids).' '.Str::plural('juego', count($ids)).' '.(count($ids) === 1 ? 'enviado' : 'enviados').' a la papelera.'
        );
    }

    /**
     * Cambia el estado de juego (pendiente/jugando/terminado) de golpe a
     * todos los juegos seleccionados en el listado.
     */
    public function updatePlayStatus(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'play_status' => ['required', 'string', Rule::in(Game::PLAY_STATUSES)],
        ]);

        $ids = $this->ownedSelectedIds($request);

        Game::whereIn('id', $ids)->update(['play_status' => $validated['play_status']]);

        // Mismo motivo que en destroy(): mass update por query builder, sin
        // evento 'saved' que GameObserver pueda escuchar.
        Cache::forget(StatsController::cacheKey(auth()->id()));

        return redirect()->route('web.games.index')->with(
            'success',
            'Estado actualizado en '.count($ids).' '.Str::plural('juego', count($ids)).'.'
        );
    }

    /**
     * Marca de golpe como "en venta" todos los juegos seleccionados en el
     * listado de la colección (#123).
     */
    public function markForSale(Request $request): RedirectResponse
    {
        $ids = $this->ownedSelectedIds($request);

        Game::whereIn('id', $ids)->update(['for_sale' => true]);

        // Mismo motivo que en destroy()/updatePlayStatus(): mass update por
        // query builder, sin evento 'saved' que GameObserver pueda escuchar.
        Cache::forget(StatsController::cacheKey(auth()->id()));

        return redirect()->route('web.games.index')->with(
            'success',
            count($ids).' '.Str::plural('juego', count($ids)).' '.(count($ids) === 1 ? 'marcado' : 'marcados').' como en venta.'
        );
    }

    /**
     * Marca de golpe como vendidos todos los juegos seleccionados en el
     * listado, con un único precio y fecha de venta compartidos para todo el
     * lote (issue #183): pedir un precio distinto por juego rompería la
     * ventaja de hacerlo en bloque, y en la práctica un lote catalogado
     * junto (misma bolsa/compra) también suele venderse junto al mismo
     * precio por unidad. Mismo ciclo que SalesController::markAsSold() para
     * uno solo: envía a la papelera, recuperable desde /sales.
     */
    public function bulkMarkAsSold(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            // max:99999999.99: tope real de la columna decimal(10,2) — ver
            // GameController::validated() para el mismo motivo.
            'sale_price' => 'required|numeric|min:0|max:99999999.99',
            'sold_at' => 'required|date',
        ]);

        $ids = $this->ownedSelectedIds($request);

        Game::whereIn('id', $ids)->update([
            'sale_price' => $validated['sale_price'],
            'sold_at' => $validated['sold_at'],
            'status' => 'sold',
            'for_sale' => false,
        ]);

        // Mass delete por query builder, no Model::destroy(): mismo motivo
        // que destroy() más arriba, no dispara el evento 'deleted' de
        // Eloquent, de ahí el Cache::forget() explícito.
        Game::whereIn('id', $ids)->delete();

        Cache::forget(StatsController::cacheKey(auth()->id()));

        return redirect()->route('web.games.index')->with(
            'success',
            count($ids).' '.Str::plural('juego', count($ids)).' '.(count($ids) === 1 ? 'marcado' : 'marcados').' como vendido.'
        );
    }

    /**
     * Inversa de markForSale(), pero desde la propia página "En venta" (#123
     * seguimiento): antes solo se podía quitar un juego cada vez, desde su
     * ficha (ver GameController::quickUpdate()) — al vivir esta página
     * pensada justo para hacerles mantenimiento en bloque, tenía sentido
     * poder vaciarla de golpe.
     */
    public function unmarkForSale(Request $request): RedirectResponse
    {
        $ids = $this->ownedSelectedIds($request);

        Game::whereIn('id', $ids)->update(['for_sale' => false]);

        Cache::forget(StatsController::cacheKey(auth()->id()));

        return redirect()->route('web.for-sale.index')->with(
            'success',
            count($ids).' '.Str::plural('juego', count($ids)).' '.(count($ids) === 1 ? 'quitado' : 'quitados').' de en venta.'
        );
    }

    /**
     * IDs seleccionados en el formulario, acotados a los que de verdad
     * pertenecen al usuario autenticado (evita que alguien manipule el HTML
     * y mande el ID de un juego ajeno).
     *
     * @return array<int, int>
     */
    private function ownedSelectedIds(Request $request): array
    {
        $request->validate([
            'game_ids' => 'required|array|min:1',
            'game_ids.*' => 'integer',
        ]);

        return Game::where('user_id', auth()->id())
            ->whereIn('id', $request->input('game_ids'))
            ->pluck('id')
            ->all();
    }
}
