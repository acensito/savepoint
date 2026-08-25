<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\ImportGamesFromCsv;
use App\Services\GameImport\GameCsvImporter;
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
     * Formulario de importación.
     */
    public function create(): View
    {
        return view('games.import');
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
            fn (array $row) => implode(',', array_map($this->csvEscape(...), $row)),
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

        return response()->json([
            'matchedColumns' => $matched,
            'unmatchedColumns' => $unmatched,
            'rows' => $rows,
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
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $parsed = $this->importer->openFile($request->file('file')->getRealPath());

        if (isset($parsed['error'])) {
            return back()->withErrors(['file' => $parsed['error']]);
        }

        fclose($parsed['handle']);

        // El directorio de subidas temporal de PHP no sobrevive a la
        // petición, así que el job (que se procesa después de que esta
        // respuesta ya se haya devuelto) necesita su propia copia persistida.
        $path = $request->file('file')->store('imports');

        $importId = (string) Str::uuid();
        Cache::put(self::cacheKey($importId), ['user_id' => $request->user()->id, 'done' => false], now()->addHour());

        ImportGamesFromCsv::dispatch($request->user()->id, $path, $importId);

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
     * Ver GameExportController::csvEscape() para el porqué (CWE-1236,
     * CSV/formula injection). Aquí solo escribe las cabeceras fijas de la
     * plantilla (nunca datos de usuario), pero se mantiene el mismo
     * escapado por consistencia y por si en el futuro se usa para algo más.
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
