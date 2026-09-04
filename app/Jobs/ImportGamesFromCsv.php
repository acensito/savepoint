<?php

namespace App\Jobs;

use App\Http\Controllers\Web\GameImportController;
use App\Services\GameImport\GameCsvImporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Alta en bloque de un CSV subido desde /games/import (ver
 * GameImportController::store()), en segundo plano: antes se procesaba fila
 * a fila dentro de la propia petición HTTP, arriesgando el timeout de
 * PHP-FPM/nginx con la colección real (1000+ juegos) que sigue pendiente de
 * cargar (ver README). El resultado (nº importados/creados/errores) se deja
 * en caché bajo cacheKey($importId), sondeado por
 * GameImportController::importStatus() mientras el formulario de
 * importación lo consulta (ver import.blade.php).
 */
class ImportGamesFromCsv implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $userId,
        public readonly string $path,
        public readonly string $importId,
    ) {}

    public function handle(GameCsvImporter $importer): void
    {
        try {
            $result = $importer->import(Storage::path($this->path), $this->userId);

            Cache::put(
                GameImportController::cacheKey($this->importId),
                ['user_id' => $this->userId, 'done' => true, ...$result],
                GameImportController::cacheTtl(),
            );
        } finally {
            // Solo hacía falta mientras el job la procesaba; no tiene sentido
            // dejarla en disco indefinidamente como sí pasa con las carátulas.
            Storage::delete($this->path);
        }
    }
}
