<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\ImportGamesFromCsv;
use App\Models\Game;
use App\Models\Platform;
use App\Services\GameImport\GameCsvImporter;
use App\Services\Games\CsvFieldEscaper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;

class GameImportController extends Controller
{
    public function __construct(
        private readonly GameCsvImporter $importer,
    ) {}

    /**
     * Formulario de importación. $selectedPlatformId llega como ?platform_id=
     * desde la tarjeta del Panel de control (#143) y solo preselecciona el
     * alcance del modo Reemplazar aquí — no filtra la subida en sí.
     */
    public function create(Request $request): View
    {
        $platforms = Platform::where('user_id', auth()->id())->withCount('games')->orderBy('name')->get();
        $noPlatformCount = Game::where('user_id', auth()->id())->whereNull('platform_id')->count();
        $totalGames = Game::where('user_id', auth()->id())->count();
        $selectedPlatformId = $request->query('platform_id');

        return view('games.import', compact('platforms', 'noPlatformCount', 'totalGames', 'selectedPlatformId'));
    }

    /**
     * Plantilla CSV descargable con las cabeceras esperadas y una fila de ejemplo.
     */
    public function template(): Response
    {
        $rows = [
            ['Título', 'EAN', 'Desarrollador', 'Plataforma', 'Edición', 'Fecha lanzamiento', 'Géneros', 'Propiedad', 'Estado de juego', 'Conservación', 'Precio pagado', 'Lugar de compra', 'Fecha de compra', 'Manual', 'Región', 'Clasificación por edad', 'Notas'],
            ['Celeste', '0812872018012', 'Maddy Makes Games', 'Nintendo Switch', 'Normal', '2018-01-25', 'Plataformas, Indie', 'En colección', 'Terminado', '5', '19.99', 'Eshop', '2020-05-01', 'Sin Manual', 'PAL-ES', 'PEGI 7', 'Platinado'],
        ];

        $csv = implode("\r\n", array_map(
            fn (array $row) => implode(',', array_map(CsvFieldEscaper::escape(...), $row)),
            $rows
        ))."\r\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="savepoint-plantilla-importacion.csv"',
        ]);
    }

    /**
     * Cabeceras conocidas por el importador, con su etiqueta legible, para
     * el resumen de columnas detectadas/no detectadas de preview().
     */
    private const KNOWN_COLUMNS = [
        'titulo' => 'Título', 'ean' => 'EAN', 'desarrollador' => 'Desarrollador',
        'plataforma' => 'Plataforma', 'edicion' => 'Edición', 'fecha lanzamiento' => 'Fecha lanzamiento',
        'generos' => 'Géneros', 'propiedad' => 'Propiedad', 'estado de juego' => 'Estado de juego',
        'conservacion' => 'Conservación', 'precio pagado' => 'Precio pagado', 'lugar de compra' => 'Lugar de compra',
        'fecha de compra' => 'Fecha de compra', 'manual' => 'Manual', 'region' => 'Región',
        'clasificacion por edad' => 'Clasificación por edad', 'notas' => 'Notas',
    ];

    /**
     * Vista previa del CSV antes de importar de verdad: qué columnas
     * conocidas se han detectado (y cuáles no) y las primeras filas, tal
     * como las entendería el import real, para poder corregir el fichero
     * antes de subirlo en firme si algo no cuadra.
     */
    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $parsed = $this->importer->openFile($request->file('file')->getRealPath());

        if (isset($parsed['error'])) {
            return response()->json(['error' => $parsed['error']], 422);
        }

        ['handle' => $handle, 'delimiter' => $delimiter, 'columns' => $columns] = $parsed;

        $matched = [];
        $unmatched = [];
        foreach (self::KNOWN_COLUMNS as $key => $label) {
            if (isset($columns[$key])) {
                $matched[] = $label;
            } else {
                $unmatched[] = $label;
            }
        }

        $rows = [];
        while (count($rows) < 5 && ($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (count($row) === 1 && trim((string) $row[0]) === '') {
                continue;
            }

            $get = fn (string $key): string => isset($columns[$key], $row[$columns[$key]])
                ? trim((string) $row[$columns[$key]])
                : '';

            $rows[] = [
                'titulo' => $get('titulo'),
                'plataforma' => $get('plataforma'),
                'ean' => $get('ean'),
                'precio pagado' => $get('precio pagado'),
            ];
        }

        fclose($handle);

        // #143: escaneo completo del CSV (no solo las filas de ejemplo de
        // arriba) para detectar filas que ya existen en la colección
        // (título+plataforma+edición) antes de confirmar la importación real.
        $duplicates = $this->importer->findDuplicates($request->file('file')->getRealPath(), $request->user()->id);

        return response()->json([
            'matchedColumns' => $matched,
            'unmatchedColumns' => $unmatched,
            'rows' => $rows,
            'duplicates' => $duplicates,
        ]);
    }

    /**
     * Valida y guarda el CSV subido, y despacha su procesamiento al worker
     * de cola (ver Jobs\ImportGamesFromCsv) en vez de recorrer las filas
     * aquí mismo: con la colección real (1000+ juegos) que sigue pendiente
     * de cargar, hacerlo dentro de la petición arriesgaba el timeout de
     * PHP-FPM/nginx. Se valida ya aquí (no solo dentro del job) para poder
     * devolver el error de "sin columna Título" en el propio formulario,
     * igual que antes.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'mode' => ['nullable', 'in:add,replace'],
            'scope_platform_id' => ['nullable'],
            'confirm' => ['required_if:mode,replace', 'nullable', 'string'],
            'duplicate_decisions' => ['nullable', 'string'],
        ]);

        $parsed = $this->importer->openFile($request->file('file')->getRealPath());

        if (isset($parsed['error'])) {
            return back()->withErrors(['file' => $parsed['error']]);
        }

        fclose($parsed['handle']);

        $mode = $validated['mode'] ?? 'add';

        // Reemplazar borra en bloque antes de importar (ver ImportGamesFromCsv):
        // el alcance y la confirmación tecleada se resuelven y comprueban aquí,
        // no dentro del job, para poder devolver el error al propio formulario
        // sin llegar a despachar nada (mismo patrón que
        // PanelController::clearPlatformGames()/clearAllGames(), #143).
        $scope = ['type' => 'all', 'id' => null];

        if ($mode === 'replace') {
            $scopePlatformId = $validated['scope_platform_id'] ?? null;

            if (blank($scopePlatformId)) {
                $expectedName = PanelController::CLEAR_ALL_CONFIRM_TEXT;
            } elseif ((string) $scopePlatformId === PanelController::NO_PLATFORM_VALUE) {
                $scope = ['type' => 'none', 'id' => null];
                $expectedName = 'Sin plataforma';
            } else {
                $platform = Platform::where('user_id', auth()->id())->find($scopePlatformId);

                if (! $platform) {
                    return back()->withInput()->withErrors(['scope_platform_id' => 'Esa plataforma ya no existe.']);
                }

                $scope = ['type' => 'platform', 'id' => $platform->id];
                $expectedName = $platform->name;
            }

            if ($validated['confirm'] !== $expectedName) {
                return back()->withInput()->withErrors(['confirm' => 'El nombre no coincide con «'.$expectedName.'», no se ha importado nada.']);
            }
        }

        $duplicateDecisions = [];
        if (filled($validated['duplicate_decisions'] ?? null)) {
            $decoded = json_decode($validated['duplicate_decisions'], true);
            // Decisiones inválidas se ignoran (cada duplicado real vuelve a
            // comprobarse en el import real y por defecto se omite, ver
            // GameCsvImporter::import()) en vez de bloquear la importación.
            if (is_array($decoded)) {
                $duplicateDecisions = $decoded;
            }
        }

        // El directorio de subidas temporal de PHP no sobrevive a la
        // petición, así que el job (que se procesa después de que esta
        // respuesta ya se haya devuelto) necesita su propia copia persistida.
        $path = $request->file('file')->store('imports');

        $importId = (string) Str::uuid();
        Cache::put(self::cacheKey($importId), ['user_id' => $request->user()->id, 'done' => false], self::cacheTtl());

        ImportGamesFromCsv::dispatch($request->user()->id, $path, $importId, $mode, $scope, $duplicateDecisions);

        return redirect()->route('web.games.import')->with('importId', $importId);
    }

    /**
     * Sondeado por el formulario de importación (ver import.blade.php)
     * mientras Jobs\ImportGamesFromCsv procesa el CSV despachado en store():
     * {done: false} todavía en curso, {done: true, imported, ...} con el
     * mismo resumen que antes se devolvía ya listo en la propia redirección.
     */
    public function importStatus(Request $request, string $importId): JsonResponse
    {
        $status = Cache::get(self::cacheKey($importId));

        if ($status === null || $status['user_id'] !== $request->user()->id) {
            return response()->json(['message' => 'Importación no encontrada.'], 404);
        }

        return response()->json(Arr::except($status, ['user_id']));
    }

    /**
     * Compartida con Jobs\ImportGamesFromCsv, que escribe aquí el resultado
     * final una vez procesa el CSV.
     */
    public static function cacheKey(string $importId): string
    {
        return "game-import:{$importId}";
    }

    /**
     * TTL de la caché de estado, compartida con Jobs\ImportGamesFromCsv
     * (ver cacheKey()). Antes era una hora, escasa para la colección real
     * (1000+ juegos, ver README): si el job tardaba más en pasar por la
     * cola en un servidor cargado, la clave expiraba mientras la
     * importación seguía en curso de verdad, y el sondeo de importStatus()
     * devolvía 404 — indistinguible de un fallo real (#119). El propio
     * import no debería tardar tanto (sin llamadas de red por fila, ver
     * GameCsvImporter), pero un TTL generoso cuesta poco y cubre también un
     * worker de cola momentáneamente parado/con backlog.
     */
    public static function cacheTtl(): \DateTimeInterface
    {
        return now()->addDay();
    }
}
