<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Edition;
use App\Models\Game;
use App\Models\Platform;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;

class GameImportController extends Controller
{
    private const STATUS_MAP = [
        'en coleccion' => 'owned',
        'owned' => 'owned',
        'lista de deseos' => 'wishlist',
        'wishlist' => 'wishlist',
        'vendido' => 'sold',
        'sold' => 'sold',
    ];

    private const PLAY_STATUS_MAP = [
        'pendiente' => 'pending',
        'pending' => 'pending',
        'jugando' => 'playing',
        'playing' => 'playing',
        'terminado' => 'finished',
        'finished' => 'finished',
    ];

    private const MANUAL_MAP = [
        'con manual' => 'included',
        'included' => 'included',
        'sin manual' => 'missing',
        'missing' => 'missing',
        'folleto' => 'booklet',
        'booklet' => 'booklet',
    ];

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
            ['Título', 'EAN', 'Desarrollador', 'Plataforma', 'Edición', 'Fecha lanzamiento', 'Géneros', 'Propiedad', 'Estado de juego', 'Valoración', 'Precio pagado', 'Lugar de compra', 'Fecha de compra', 'Manual', 'Región', 'Clasificación por edad', 'Notas'],
            ['Celeste', '0812872018012', 'Maddy Makes Games', 'Nintendo Switch', 'Normal', '2018-01-25', 'Plataformas, Indie', 'En colección', 'Terminado', '5', '19.99', 'Eshop', '2020-05-01', 'Sin Manual', 'PAL-ES', 'PEGI 7', 'Platinado'],
        ];

        $csv = implode("\r\n", array_map(
            fn (array $row) => implode(',', array_map($this->csvEscape(...), $row)),
            $rows
        )) . "\r\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="savepoint-plantilla-importacion.csv"',
        ]);
    }

    /**
     * Procesa el CSV subido: cada fila se guarda de forma independiente (si
     * una falla, no bloquea al resto) y las plataformas/ediciones que no
     * existan todavía en el catálogo se crean sobre la marcha.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');

        if ($handle === false) {
            return back()->withErrors(['file' => 'No se ha podido leer el fichero.']);
        }

        $headerLine = fgets($handle);

        if ($headerLine === false) {
            fclose($handle);

            return back()->withErrors(['file' => 'El fichero está vacío.']);
        }

        // Excel exporta CSV en UTF-8 con BOM; si no se quita, la primera cabecera
        // ("Título") no coincide con ninguna columna esperada.
        $headerLine = preg_replace('/^\xEF\xBB\xBF/', '', $headerLine);

        // Excel en español suele exportar CSV con ';' en vez de ',' como separador.
        $delimiter = substr_count($headerLine, ';') > substr_count($headerLine, ',') ? ';' : ',';

        $header = array_map($this->normalizeHeader(...), str_getcsv($headerLine, $delimiter));
        $columns = array_flip($header);

        if (! isset($columns['titulo'])) {
            fclose($handle);

            return back()->withErrors(['file' => 'El CSV debe tener una columna "Título".']);
        }

        $imported = 0;
        $createdPlatforms = 0;
        $createdEditions = 0;
        $errors = [];
        $rowNumber = 1;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowNumber++;

            // Ignora líneas totalmente vacías (frecuentes al final de un export de Excel).
            if (count($row) === 1 && trim((string) $row[0]) === '') {
                continue;
            }

            $get = fn (string $key): ?string => isset($columns[$key], $row[$columns[$key]])
                ? trim((string) $row[$columns[$key]])
                : null;

            $title = $get('titulo');

            if (blank($title)) {
                $errors[] = "Fila {$rowNumber}: sin título, se ha omitido.";
                continue;
            }

            try {
                $platformId = null;
                if (filled($get('plataforma'))) {
                    [$platformId, $wasCreated] = $this->resolvePlatform($get('plataforma'));
                    $createdPlatforms += $wasCreated ? 1 : 0;
                }

                $editionId = null;
                if (filled($get('edicion'))) {
                    [$editionId, $wasCreated] = $this->resolveEdition($get('edicion'));
                    $createdEditions += $wasCreated ? 1 : 0;
                }

                Game::create([
                    'user_id' => $request->user()->id,
                    'title' => $title,
                    'ean' => $get('ean') ?: null,
                    'developer' => $get('desarrollador') ?: null,
                    'platform_id' => $platformId,
                    'edition_id' => $editionId,
                    'release_date' => $this->parseDate($get('fecha lanzamiento')),
                    'genres' => $this->parseGenres($get('generos')),
                    'status' => $this->mapValue($get('propiedad'), self::STATUS_MAP, 'owned'),
                    'play_status' => $this->mapValue($get('estado de juego'), self::PLAY_STATUS_MAP, 'pending'),
                    'rating' => $this->parseRating($get('valoracion')),
                    'price_paid' => $this->parseDecimal($get('precio pagado')),
                    'purchase_place' => $get('lugar de compra') ?: null,
                    'purchase_date' => $this->parseDate($get('fecha de compra')),
                    'manual_status' => $this->mapValue($get('manual'), self::MANUAL_MAP, null),
                    'region' => $get('region') ?: null,
                    'age_rating' => $get('clasificacion por edad') ?: null,
                    'notes' => $get('notas') ?: null,
                ]);

                $imported++;
            } catch (\Throwable $e) {
                $errors[] = "Fila {$rowNumber} («{$title}»): no se ha podido importar ({$e->getMessage()}).";
            }
        }

        fclose($handle);

        return redirect()->route('web.games.import')->with('importResult', [
            'imported' => $imported,
            'createdPlatforms' => $createdPlatforms,
            'createdEditions' => $createdEditions,
            'errors' => $errors,
        ]);
    }

    /**
     * Busca una plataforma por nombre (sin distinguir mayúsculas) o la crea
     * si no existe todavía. Devuelve [id, se_ha_creado].
     */
    private function resolvePlatform(string $name): array
    {
        $platform = Platform::whereRaw('LOWER(name) = ?', [Str::lower($name)])->first();

        if ($platform) {
            return [$platform->id, false];
        }

        $platform = Platform::create([
            'name' => $name,
            'slug' => $this->uniqueSlug(Platform::class, $name),
        ]);

        return [$platform->id, true];
    }

    /**
     * Igual que resolvePlatform() pero para ediciones (sin fabricante ni colores que asignar).
     */
    private function resolveEdition(string $name): array
    {
        $edition = Edition::whereRaw('LOWER(name) = ?', [Str::lower($name)])->first();

        if ($edition) {
            return [$edition->id, false];
        }

        $edition = Edition::create(['name' => $name]);

        return [$edition->id, true];
    }

    private function uniqueSlug(string $modelClass, string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 1;

        while ($modelClass::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    private function normalizeHeader(string $value): string
    {
        $value = Str::lower(trim($value));
        $value = Str::ascii($value); // quita acentos: 'título' -> 'titulo'

        return $value;
    }

    private function mapValue(?string $value, array $map, ?string $default): ?string
    {
        if (blank($value)) {
            return $default;
        }

        return $map[Str::lower(trim($value))] ?? $default;
    }

    private function parseGenres(?string $raw): ?array
    {
        if (blank($raw)) {
            return null;
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    private function parseDate(?string $raw): ?string
    {
        if (blank($raw)) {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $raw)->format('Y-m-d');
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse($raw)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseRating(?string $raw): ?int
    {
        if (blank($raw) || ! is_numeric($raw)) {
            return null;
        }

        $rating = (int) round((float) $raw);

        return ($rating >= 1 && $rating <= 5) ? $rating : null;
    }

    private function parseDecimal(?string $raw): ?float
    {
        if (blank($raw)) {
            return null;
        }

        // Admite tanto "19.99" como "19,99" (formato español).
        $normalized = str_replace(',', '.', $raw);

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    private function csvEscape(string $value): string
    {
        if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
            return '"' . str_replace('"', '""', $value) . '"';
        }

        return $value;
    }
}
