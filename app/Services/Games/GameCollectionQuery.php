<?php

namespace App\Services\Games;

use App\Http\Controllers\Web\GameController;
use App\Models\Game;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Condiciones de búsqueda/filtro/orden compartidas entre el listado paginado
 * (GameController::index) y la exportación imprimible/CSV
 * (GameExportController::print/export). Cada llamante decide aparte qué
 * columnas/relaciones necesita y si pagina o trae todos los resultados.
 *
 * Extraído de GameController (antes dos métodos privados) para que
 * GameExportController pueda reutilizar exactamente el mismo filtrado sin
 * duplicarlo ni depender de GameController.
 */
class GameCollectionQuery
{
    public function query(Request $request): Builder
    {
        $query = trim((string) $request->input('q', ''));
        $platformId = (string) $request->input('platform_id', '');
        $playStatus = (string) $request->input('play_status', '');
        $status = (string) $request->input('status', '');
        $forSale = (string) $request->input('for_sale', '');
        [$sort, $dir] = $this->resolveSort($request);
        $sortColumn = GameController::SORTABLE_COLUMNS[$sort] ?? null;

        return Game::where('user_id', auth()->id())
            // La lista de deseos tiene su propia página (/wishlist): un juego
            // deseado todavía no forma parte de "tu colección", así que nunca
            // aparece aquí, ni siquiera con el filtro de Propiedad a mano.
            ->where('status', '!=', 'wishlist')
            ->when($query !== '', fn ($q) => $q->search($query))
            ->when(
                $platformId !== '',
                fn ($q) => $platformId === 'none'
                    ? $q->whereNull('platform_id')
                    : $q->where('platform_id', $platformId),
            )
            ->when($playStatus !== '', fn ($q) => $q->where('play_status', $playStatus))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when(
                $forSale === '1',
                fn ($q) => $q->where('for_sale', true),
                // Sin filtrar a propósito por "en venta": si la cuenta ha
                // activado "Ocultar de la colección" en Ajustes (ver
                // ForSaleController, su sección dedicada), se excluyen del
                // listado sin filtrar — pero ?for_sale=1 arriba siempre
                // sigue funcionando, es justo la vía para verlos ahí cuando
                // se quiere.
                fn ($q) => $q->when(auth()->user()->hide_for_sale_from_collection, fn ($q) => $q->where('for_sale', false)),
            )
            ->when(
                $sortColumn !== null,
                fn ($q) => $q->orderBy($sortColumn, $dir)->orderByDesc('id'),
                // orderByDesc('id') como desempate: dos juegos dados de alta en
                // el mismo segundo (created_at empatado) sin esto quedarían en
                // un orden no determinista entre carga y carga/página y página.
                fn ($q) => $q->latest()->orderByDesc('id'),
            );
    }

    /**
     * Orden efectivo del listado: si la URL trae ?sort=/?dir= (aunque sea
     * vacío, que es una elección válida = "más recientes"), gana sobre
     * cualquier otra cosa; si no trae esas claves en absoluto, se usa el
     * ajuste "Orden por defecto" de Ajustes (ver PanelController), y si el
     * usuario tampoco lo ha configurado, se cae a 'desc'. Compartido entre
     * el listado y la exportación para que respeten el mismo criterio.
     */
    public function resolveSort(Request $request): array
    {
        $sort = $request->has('sort')
            ? (string) $request->input('sort', '')
            : (string) (auth()->user()->default_sort ?? '');

        $dir = $request->has('dir')
            ? ($request->input('dir') === 'asc' ? 'asc' : 'desc')
            : (auth()->user()->default_dir === 'asc' ? 'asc' : 'desc');

        return [$sort, $dir];
    }
}
