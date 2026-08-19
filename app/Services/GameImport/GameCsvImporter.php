<?php

namespace App\Services\GameImport;

use App\Models\Edition;
use App\Models\Game;
use App\Models\Platform;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

/**
 * Parseo y alta en bloque de un CSV de la colección — compartido por
 * GameImportController::preview() (solo detecta columnas y adelanta las
 * primeras filas, sin dar de alta nada) y Jobs\ImportGamesFromCsv (alta real
 * en segundo plano, ver GameImportController::store()), para no duplicar el
 * mapeo de valores/formato de fecha entre los dos sitios.
 */
class GameCsvImporter
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
     * Abre el CSV, detecta separador y cabeceras. Devuelve
     * ['handle' => resource, 'delimiter' => string, 'columns' => array] o
     * ['error' => string] si el fichero no se puede leer/está vacío/no tiene
     * columna "Título". El caller es responsable de cerrar el handle.
     */
    public function openFile(string $path): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return ['error' => 'No se ha podido leer el fichero.'];
        }

        $headerLine = fgets($handle);

        if ($headerLine === false) {
            fclose($handle);

            return ['error' => 'El fichero está vacío.'];
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

            return ['error' => 'El CSV debe tener una columna "Título".'];
        }

        return ['handle' => $handle, 'delimiter' => $delimiter, 'columns' => $columns];
    }

    public function normalizeHeader(string $value): string
    {
        $value = Str::lower(trim($value));
        $value = Str::ascii($value); // quita acentos: 'título' -> 'titulo'

        return $value;
    }

    /**
     * Importa fila a fila un CSV ya subido/almacenado: cada fila se procesa
     * de forma independiente (si una falla, no bloquea al resto) y las
     * plataformas/ediciones que no existan todavía en el catálogo se crean
     * sobre la marcha.
     *
     * @return array{imported: int, createdPlatforms: int, createdEditions: int, errors: string[]}
     */
    public function import(string $path, int $userId): array
    {
        $parsed = $this->openFile($path);

        if (isset($parsed['error'])) {
            return ['imported' => 0, 'createdPlatforms' => 0, 'createdEditions' => 0, 'errors' => [$parsed['error']]];
        }

        ['handle' => $handle, 'delimiter' => $delimiter, 'columns' => $columns] = $parsed;

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
                    'user_id' => $userId,
                    'title' => $title,
                    'ean' => $get('ean') ?: null,
                    'developer' => $get('desarrollador') ?: null,
                    'platform_id' => $platformId,
                    'edition_id' => $editionId,
                    'release_date' => $this->parseDate($get('fecha lanzamiento')),
                    'genres' => $this->parseGenres($get('generos')),
                    'status' => $this->mapValue($get('propiedad'), self::STATUS_MAP, 'owned'),
                    'play_status' => $this->mapValue($get('estado de juego'), self::PLAY_STATUS_MAP, 'pending'),
                    'rating' => $this->parseRating($get('conservacion')),
                    'price_paid' => $this->parseDecimal($get('precio pagado')),
                    'purchase_place' => $get('lugar de compra') ?: null,
                    'purchase_date' => $this->parseDate($get('fecha de compra')),
                    'manual_status' => $this->mapValue($get('manual'), self::MANUAL_MAP, null),
                    'region' => $get('region') ?: null,
                    'age_rating' => $get('clasificacion por edad') ?: null,
                    'notes' => $get('notas') ?: null,
                ]);

                $imported++;
            } catch (Throwable $e) {
                $errors[] = "Fila {$rowNumber} («{$title}»): no se ha podido importar ({$e->getMessage()}).";
            }
        }

        fclose($handle);

        return compact('imported', 'createdPlatforms', 'createdEditions', 'errors');
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
            } catch (Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse($raw)->format('Y-m-d');
        } catch (Throwable) {
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
}
