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
     * Ids de plataforma/edición ya resueltos en la corrida de import() en
     * curso, por "userId:nombre en minúsculas" — una colección real repite la
     * misma plataforma/edición en la inmensa mayoría de sus filas, y sin esto
     * cada una disparaba su propia consulta whereRaw('LOWER(name) = ?') (issue
     * #185, auditoría de rendimiento del 2026-09-10). Se reinician al
     * principio de cada import() en vez de vivir solo en el constructor: el
     * importador se resuelve por inyección de dependencias y no hay garantía
     * de que cada import() se ejecute sobre una instancia nueva. El userId
     * entra en la clave (no solo el nombre) por si esta misma instancia
     * llegara a reutilizarse entre usuarios distintos en la misma request
     * (catálogo por cuenta, issue #175).
     *
     * @var array<string, int>
     */
    private array $platformIdsByName = [];

    /**
     * @var array<string, int>
     */
    private array $editionIdsByName = [];

    /**
     * Abre el CSV, detecta separador y cabeceras. Devuelve
     * ['handle' => resource, 'delimiter' => string, 'columns' => array] o
     * ['error' => string] si el fichero no se puede leer/está vacío/no tiene
     * columna "Título". El caller es responsable de cerrar el handle.
     *
     * @return array{handle: resource, delimiter: string, columns: array<string, int>}|array{error: string}
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
     * Escanea el CSV completo (a diferencia de las 5 filas de ejemplo de
     * GameImportController::preview()) en busca de filas que ya existan en la
     * colección por título+plataforma+edición, sin distinguir mayúsculas
     * (#143). A propósito NO crea ninguna plataforma/edición/juego — si el
     * nombre de plataforma o edición de una fila no existe todavía en el
     * catálogo, no puede haber ya un juego duplicado bajo ese id, así que se
     * trata como "sin coincidencia" (ver findPlatformId()/findEditionId(),
     * variantes de solo lectura de resolvePlatform()/resolveEdition()). El
     * modo Añadir vuelve a comprobar cada fila contra la colección en el
     * import real (import()) antes de crear nada — esto es solo para poder
     * enseñarle al usuario los duplicados antes de confirmar.
     *
     * @return array<int, array{row: int, title: string, platform: ?string, edition: ?string, existingGameId: int}>
     */
    public function findDuplicates(string $path, int $userId): array
    {
        $parsed = $this->openFile($path);

        if (isset($parsed['error'])) {
            return [];
        }

        ['handle' => $handle, 'delimiter' => $delimiter, 'columns' => $columns] = $parsed;

        $this->platformIdsByName = [];
        $this->editionIdsByName = [];

        $duplicates = [];
        $rowNumber = 1;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowNumber++;

            if (count($row) === 1 && trim((string) $row[0]) === '') {
                continue;
            }

            $get = fn (string $key): ?string => isset($columns[$key], $row[$columns[$key]])
                ? trim((string) $row[$columns[$key]])
                : null;

            $title = $get('titulo');

            if (blank($title)) {
                continue;
            }

            $platformName = $get('plataforma');
            $platformId = filled($platformName) ? $this->findPlatformId($platformName, $userId) : null;
            if (filled($platformName) && $platformId === null) {
                continue; // la plataforma no existe todavía: no puede haber duplicado.
            }

            $editionName = $get('edicion');
            $editionId = filled($editionName) ? $this->findEditionId($editionName, $userId) : null;
            if (filled($editionName) && $editionId === null) {
                continue; // igual que arriba, con la edición.
            }

            $existing = Game::where('user_id', $userId)
                ->whereRaw('LOWER(title) = ?', [Str::lower($title)])
                ->where('platform_id', $platformId)
                ->where('edition_id', $editionId)
                ->first();

            if ($existing) {
                $duplicates[] = [
                    'row' => $rowNumber,
                    'title' => $title,
                    'platform' => $platformName ?: null,
                    'edition' => $editionName ?: null,
                    'existingGameId' => $existing->id,
                ];
            }
        }

        fclose($handle);

        return $duplicates;
    }

    /**
     * Importa fila a fila un CSV ya subido/almacenado: cada fila se procesa
     * de forma independiente (si una falla, no bloquea al resto) y las
     * plataformas/ediciones que no existan todavía en el catálogo se crean
     * sobre la marcha.
     *
     * $mode/$scopePlatformName/$duplicateDecisions son de #143 (modo
     * Reemplazar y duplicados de Añadir) — ImportGamesFromCsv ya se encarga
     * de borrar el alcance elegido antes de llamar aquí cuando $mode es
     * 'replace'; esta función solo necesita saber el nombre de la plataforma
     * del alcance (null = toda la colección, sin restricción; '' = "Sin
     * plataforma") para omitir filas que no encajen.
     *
     * @param  array<int, string>  $duplicateDecisions  fila => 'overwrite'|'skip'
     * @return array{imported: int, createdPlatforms: int, createdEditions: int, errors: string[], platformIds: int[], duplicatesOverwritten: int, duplicatesSkipped: int, skippedScope: int}
     */
    public function import(string $path, int $userId, string $mode = 'add', ?string $scopePlatformName = null, array $duplicateDecisions = []): array
    {
        $parsed = $this->openFile($path);

        if (isset($parsed['error'])) {
            return ['imported' => 0, 'createdPlatforms' => 0, 'createdEditions' => 0, 'errors' => [$parsed['error']], 'platformIds' => [], 'duplicatesOverwritten' => 0, 'duplicatesSkipped' => 0, 'skippedScope' => 0];
        }

        ['handle' => $handle, 'delimiter' => $delimiter, 'columns' => $columns] = $parsed;

        $this->platformIdsByName = [];
        $this->editionIdsByName = [];

        $imported = 0;
        $createdPlatforms = 0;
        $createdEditions = 0;
        $duplicatesOverwritten = 0;
        $duplicatesSkipped = 0;
        $skippedScope = 0;
        $errors = [];
        $rowNumber = 1;
        $platformIds = [];

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

            // Reemplazar acotado a una plataforma (o a "Sin plataforma"): una
            // fila de otra plataforma se omite en vez de colarse fuera del
            // alcance que el usuario eligió sustituir.
            if ($scopePlatformName !== null) {
                $rowPlatformName = (string) $get('plataforma');
                $matchesScope = $scopePlatformName === ''
                    ? blank($rowPlatformName)
                    : Str::lower(trim($rowPlatformName)) === Str::lower($scopePlatformName);

                if (! $matchesScope) {
                    $errors[] = "Fila {$rowNumber} omitida: plataforma distinta a la seleccionada.";
                    $skippedScope++;

                    continue;
                }
            }

            try {
                $platformId = null;
                if (filled($get('plataforma'))) {
                    [$platformId, $wasCreated] = $this->resolvePlatform($get('plataforma'), $userId);
                    $createdPlatforms += $wasCreated ? 1 : 0;
                }

                $editionId = null;
                if (filled($get('edicion'))) {
                    [$editionId, $wasCreated] = $this->resolveEdition($get('edicion'), $userId);
                    $createdEditions += $wasCreated ? 1 : 0;
                }

                $status = $this->mapValue($get('propiedad'), self::STATUS_MAP, 'owned');

                $attributes = [
                    'user_id' => $userId,
                    'title' => $title,
                    'ean' => $get('ean') ?: null,
                    'developer' => $get('desarrollador') ?: null,
                    'platform_id' => $platformId,
                    'edition_id' => $editionId,
                    'release_date' => $this->parseDate($get('fecha lanzamiento')),
                    'genres' => $this->parseGenres($get('generos')),
                    'status' => $status,
                    'play_status' => $this->mapValue($get('estado de juego'), self::PLAY_STATUS_MAP, 'pending'),
                    'rating' => $this->parseRating($get('conservacion')),
                    'price_paid' => $this->parseDecimal($get('precio pagado')),
                    'purchase_place' => $get('lugar de compra') ?: null,
                    'purchase_date' => $this->parseDate($get('fecha de compra')),
                    'manual_status' => $this->mapValue($get('manual'), self::MANUAL_MAP, null),
                    'region' => $get('region') ?: null,
                    'age_rating' => $get('clasificacion por edad') ?: null,
                    'notes' => $get('notas') ?: null,
                ];

                // Añadir revisa duplicados en tiempo real (no solo se fía de
                // lo que GameImportController::preview() detectó antes de
                // confirmar): mismo criterio que findDuplicates(), por si el
                // catálogo cambió entre la vista previa y la confirmación.
                $existing = $mode === 'add'
                    ? Game::where('user_id', $userId)
                        ->whereRaw('LOWER(title) = ?', [Str::lower($title)])
                        ->where('platform_id', $platformId)
                        ->where('edition_id', $editionId)
                        ->first()
                    : null;

                if ($existing) {
                    // Por defecto Omitir si no hay decisión (fila sin marcar
                    // en la vista previa, o decisiones perdidas/manipuladas):
                    // más seguro que crear un duplicado sin que nadie lo pida.
                    if (($duplicateDecisions[$rowNumber] ?? 'skip') === 'overwrite') {
                        $existing->update($attributes);
                        $duplicatesOverwritten++;

                        if ($platformId !== null && $status !== 'wishlist') {
                            $platformIds[$platformId] = true;
                        }
                    } else {
                        $duplicatesSkipped++;
                    }

                    continue;
                }

                Game::create($attributes);

                // Plataformas de la importación con al menos un juego que
                // "Identificar en bloque" (issue #128) sí recogería después
                // (nunca wishlist, ver IdentifyMissingGameCovers): permite
                // encadenar directamente a esa pantalla al terminar (issue
                // #184, auditoría de flujos del 2026-09-10) sin tener que ir
                // al Panel a mano a elegir la plataforma otra vez.
                if ($platformId !== null && $status !== 'wishlist') {
                    $platformIds[$platformId] = true;
                }

                $imported++;
            } catch (Throwable $e) {
                $errors[] = "Fila {$rowNumber} («{$title}»): no se ha podido importar ({$e->getMessage()}).";
            }
        }

        fclose($handle);

        $platformIds = array_keys($platformIds);

        return compact('imported', 'createdPlatforms', 'createdEditions', 'errors', 'platformIds', 'duplicatesOverwritten', 'duplicatesSkipped', 'skippedScope');
    }

    /**
     * Busca una plataforma por nombre (sin distinguir mayúsculas) o la crea
     * si no existe todavía. Devuelve [id, se_ha_creado].
     *
     * @return array{0: int, 1: bool}
     */
    private function resolvePlatform(string $name, int $userId): array
    {
        $key = $userId.':'.Str::lower($name);

        if (isset($this->platformIdsByName[$key])) {
            return [$this->platformIdsByName[$key], false];
        }

        // Rule::exists()->where(...) no aplica aquí (no es una validación de
        // formulario): where('user_id', ...) a mano, catálogo por cuenta
        // (issue #175).
        $platform = Platform::where('user_id', $userId)->whereRaw('LOWER(name) = ?', [Str::lower($name)])->first();

        if (! $platform) {
            $platform = Platform::create([
                'user_id' => $userId,
                'name' => $name,
                'slug' => $this->uniqueSlug(Platform::class, $name, $userId),
            ]);
        }

        $this->platformIdsByName[$key] = $platform->id;

        return [$platform->id, $platform->wasRecentlyCreated];
    }

    /**
     * Igual que resolvePlatform() pero para ediciones (sin fabricante ni
     * colores que asignar). El CSV no trae ninguna columna de soporte
     * (issue #142: Físico se desglosó en disco/cartucho/diskette/...), así
     * que un nombre de edición que ya exista en varios soportes a la vez
     * (p. ej. "Normal" en cartucho, disco y diskette) es ambiguo por nombre
     * solo — se prefiere el soporte 'physical_disc' porque es, con
     * diferencia, el más habitual entre las plataformas que se importan por
     * CSV, en vez de dejar la elección al orden sin definir que devolvería
     * la consulta sin este desempate.
     *
     * @return array{0: int, 1: bool}
     */
    private function resolveEdition(string $name, int $userId): array
    {
        $key = $userId.':'.Str::lower($name);

        if (isset($this->editionIdsByName[$key])) {
            return [$this->editionIdsByName[$key], false];
        }

        $edition = Edition::where('user_id', $userId)
            ->whereRaw('LOWER(name) = ?', [Str::lower($name)])
            ->orderByRaw("CASE WHEN format = 'physical_disc' THEN 0 ELSE 1 END")
            ->first();

        if (! $edition) {
            $edition = Edition::create(['user_id' => $userId, 'name' => $name]);
        }

        $this->editionIdsByName[$key] = $edition->id;

        return [$edition->id, $edition->wasRecentlyCreated];
    }

    /**
     * Igual que resolvePlatform() pero de solo lectura, para findDuplicates()
     * (#143): no crea la plataforma si no existe todavía — devuelve null en
     * ese caso, en vez de crearla antes de que el usuario haya confirmado
     * nada.
     */
    private function findPlatformId(string $name, int $userId): ?int
    {
        $key = $userId.':'.Str::lower($name);

        if (isset($this->platformIdsByName[$key])) {
            return $this->platformIdsByName[$key];
        }

        $platform = Platform::where('user_id', $userId)->whereRaw('LOWER(name) = ?', [Str::lower($name)])->first();

        if ($platform) {
            $this->platformIdsByName[$key] = $platform->id;
        }

        return $platform?->id;
    }

    /**
     * Igual que findPlatformId() pero para ediciones (mismo desempate por
     * soporte que resolveEdition() cuando el nombre es ambiguo).
     */
    private function findEditionId(string $name, int $userId): ?int
    {
        $key = $userId.':'.Str::lower($name);

        if (isset($this->editionIdsByName[$key])) {
            return $this->editionIdsByName[$key];
        }

        $edition = Edition::where('user_id', $userId)
            ->whereRaw('LOWER(name) = ?', [Str::lower($name)])
            ->orderByRaw("CASE WHEN format = 'physical_disc' THEN 0 ELSE 1 END")
            ->first();

        if ($edition) {
            $this->editionIdsByName[$key] = $edition->id;
        }

        return $edition?->id;
    }

    private function uniqueSlug(string $modelClass, string $name, int $userId): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 1;

        while ($modelClass::where('user_id', $userId)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    /**
     * @param  array<string, string>  $map
     */
    private function mapValue(?string $value, array $map, ?string $default): ?string
    {
        if (blank($value)) {
            return $default;
        }

        return $map[Str::lower(trim($value))] ?? $default;
    }

    /**
     * @return array<int, string>|null
     */
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
