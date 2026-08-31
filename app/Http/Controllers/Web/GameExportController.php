<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Platform;
use App\Services\Games\GameCollectionQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Exportación de la colección (imprimible/PDF y CSV), separada de
 * GameController igual que ya se hizo con IgdbController: acciones propias
 * de un concepto (sacar la colección fuera de la app), no CRUD del juego.
 */
class GameExportController extends Controller
{
    public function __construct(private readonly GameCollectionQuery $collectionQuery) {}

    /**
     * Exportación imprimible/PDF de la colección (botón "Imprimir" del
     * listado): mismos filtros que GameController::index(), pero sin
     * paginar (una exportación con la página 2, 3... escondida detrás de la
     * paginación no serviría para nada) y en una vista independiente del
     * layout de la app (sin sidebar/header, sin el contenedor de scroll
     * interno de altura fija), para que el navegador la reparta en páginas
     * con normalidad al imprimir o guardar como PDF.
     */
    public function print(Request $request): View
    {
        $games = $this->collectionQuery->query($request)
            ->select(['id', 'title', 'ean', 'platform_id', 'edition_id', 'play_status', 'status', 'rating', 'price_paid', 'purchase_date'])
            ->with(['platform:id,name', 'edition:id,name'])
            ->get();

        $query = trim((string) $request->input('q', ''));
        $platformId = (string) $request->input('platform_id', '');
        $playStatus = (string) $request->input('play_status', '');
        $status = (string) $request->input('status', '');
        $platform = ctype_digit($platformId) ? Platform::find($platformId) : null;

        $totals = [
            'count' => $games->count(),
            'spent' => (float) $games->sum('price_paid'),
        ];

        return view('games.print-collection', compact('games', 'totals', 'query', 'platform', 'playStatus', 'status'));
    }

    /**
     * Cabeceras y mapeos inversos de GameImportController::STATUS_MAP /
     * PLAY_STATUS_MAP / MANUAL_MAP: el CSV exportado usa las mismas
     * etiquetas en español que el importador reconoce, para poder
     * reimportarlo tal cual (edición masiva fuera de la app: exportar,
     * abrir en Excel/Sheets, corregir, volver a importar).
     */
    private const EXPORT_HEADERS = ['Título', 'EAN', 'Desarrollador', 'Plataforma', 'Edición', 'Fecha lanzamiento', 'Géneros', 'Propiedad', 'Estado de juego', 'Conservación', 'Precio pagado', 'Lugar de compra', 'Fecha de compra', 'Manual', 'Región', 'Clasificación por edad', 'Notas'];

    private const EXPORT_STATUS_LABELS = ['owned' => 'En colección', 'sold' => 'Vendido'];

    private const EXPORT_PLAY_STATUS_LABELS = ['pending' => 'Pendiente', 'playing' => 'Jugando', 'finished' => 'Terminado'];

    private const EXPORT_MANUAL_LABELS = ['included' => 'Con Manual', 'missing' => 'Sin Manual', 'booklet' => 'Folleto'];

    /**
     * Exportación a CSV de la colección (botón "Exportar" del panel de
     * control): mismos filtros que index()/print(), sin paginar. A
     * diferencia de print() (pensada para imprimir/PDF), esta genera un CSV
     * con las mismas cabeceras que espera GameImportController::store(), así
     * que el fichero se puede reimportar tal cual tras editarlo.
     */
    public function export(Request $request): Response
    {
        $games = $this->collectionQuery->query($request)
            ->select(['id', 'title', 'ean', 'developer', 'platform_id', 'edition_id', 'release_date', 'genres', 'status', 'play_status', 'rating', 'price_paid', 'purchase_place', 'purchase_date', 'manual_status', 'region', 'age_rating', 'notes'])
            ->with(['platform:id,name', 'edition:id,name'])
            ->get();

        $rows = $games->map(function (Game $game) {
            /** @var array<int, string>|null $genres */
            $genres = $game->genres;

            return [
                $game->title,
                $game->ean,
                $game->developer,
                $game->platform?->name,
                $game->edition?->name,
                $game->release_date?->format('Y-m-d'),
                $genres ? implode(', ', $genres) : '',
                self::EXPORT_STATUS_LABELS[$game->status] ?? '',
                self::EXPORT_PLAY_STATUS_LABELS[$game->play_status] ?? '',
                $game->rating,
                $game->price_paid,
                $game->purchase_place,
                $game->purchase_date?->format('Y-m-d'),
                self::EXPORT_MANUAL_LABELS[$game->manual_status] ?? '',
                $game->region,
                $game->age_rating,
                $game->notes,
            ];
        });

        $csv = "\xEF\xBB\xBF".implode("\r\n", array_map(
            fn (array $row) => implode(',', array_map($this->csvEscape(...), array_map('strval', $row))),
            [self::EXPORT_HEADERS, ...$rows->all()],
        ))."\r\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="savepoint-coleccion-'.now()->format('Y-m-d').'.csv"',
        ]);
    }

    /**
     * CWE-1236 (CSV/formula injection): un valor que empiece por =, +, -, @,
     * tabulador o retorno de carro se interpreta como fórmula al abrir el
     * CSV en Excel/Sheets — justo lo que invita a hacer el comentario de
     * export() de arriba (editar y volver a importar). Anteponer un
     * apóstrofo fuerza a la hoja de cálculo a tratarlo como texto literal
     * (mitigación estándar de OWASP); Excel/Sheets lo retira solo al abrir
     * el fichero, así que no se nota si el CSV se edita ahí antes de
     * reimportarlo. Si en cambio se reimporta el CSV tal cual (sin pasar por
     * una hoja de cálculo), ese apóstrofo sí queda pegado al valor — coste
     * aceptado del fix, y raro en la práctica (un título que empiece
     * literalmente por uno de estos caracteres).
     */
    private function csvEscape(string $value): string
    {
        if (preg_match('/^[=+\-@\t\r]/', $value) === 1) {
            $value = "'".$value;
        }

        if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
            return '"'.str_replace('"', '""', $value).'"';
        }

        return $value;
    }
}
