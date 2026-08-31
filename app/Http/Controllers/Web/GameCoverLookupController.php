<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Services\GameLookup\GameLookupInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Búsqueda de carátula/EAN en el catálogo externo (CEX) durante el alta o la
 * edición de un juego, separada de GameController igual que ya se hizo con
 * IgdbController: acciones propias de un concepto (buscar en CEX), no CRUD
 * del juego en sí.
 */
class GameCoverLookupController extends Controller
{
    public function __construct(private readonly GameLookupInterface $gameLookup) {}

    /**
     * Busca en el catálogo externo (ver GameLookupInterface) un juego que
     * YA está en la colección, para poder sacarle una carátula sin tener
     * que teclear el título a mano en la búsqueda rápida. Por defecto
     * prioriza el EAN del propio juego (identifica la copia exacta) y solo
     * cae al título si no tiene uno registrado, que es más ambiguo (mismo
     * título en varias plataformas, ediciones distintas...). Admite ?q= para
     * que el usuario busque a mano con otras palabras cuando el título
     * guardado no da con ningún resultado (mal escrito, subtítulo distinto
     * al que usa CEX, etc.).
     */
    public function coverLookup(Request $request, Game $game): JsonResponse
    {
        Gate::authorize('update', $game);

        $query = trim((string) $request->query('q', ''));
        if ($query === '') {
            $query = $game->ean ?: $game->title;
        }

        return response()->json(['results' => $this->searchCoverLookup($query)]);
    }

    /**
     * Misma búsqueda que coverLookup() de arriba, pero para un juego que
     * TODAVÍA no existe (durante el alta): no hay ningún Game al que atar la
     * autorización ni un EAN/título ya guardados como valor por defecto, así
     * que "q" es obligatorio aquí (el propio formulario lo rellena con lo
     * que el usuario ya haya tecleado en EAN/título antes de pulsar el
     * botón, ver initCexCoverLookup en games/_form.blade.php).
     */
    public function coverLookupForNew(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        if ($query === '') {
            return response()->json(['results' => []]);
        }

        return response()->json(['results' => $this->searchCoverLookup($query)]);
    }

    /**
     * @return array<int, array{title: string, ean: string|null, cover_url: string|null, platform: string|null}>
     */
    private function searchCoverLookup(string $query): array
    {
        return collect($this->gameLookup->search($query))
            ->map(fn ($result) => [
                'title' => $result->title,
                'ean' => $result->ean,
                'cover_url' => $result->coverUrl,
                'platform' => $result->platform,
            ])
            ->values()
            ->all();
    }
}
