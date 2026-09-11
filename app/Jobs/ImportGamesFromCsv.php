<?php

namespace App\Jobs;

use App\Http\Controllers\Web\GameImportController;
use App\Http\Controllers\Web\StatsController;
use App\Models\Game;
use App\Models\Platform;
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
 *
 * $mode/$scope/$duplicateDecisions son de #143: modo Reemplazar (borra en
 * bloque el alcance elegido antes de importar) y decisiones de duplicados
 * del modo Añadir, ya resueltas en GameImportController::store() antes de
 * despachar este job — aquí no se pregunta nada, todo llega decidido.
 */
class ImportGamesFromCsv implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array{type: string, id: ?int}|null  $scope
     * @param  array<int, string>  $duplicateDecisions
     */
    public function __construct(
        public readonly int $userId,
        public readonly string $path,
        public readonly string $importId,
        public readonly string $mode = 'add',
        public readonly ?array $scope = null,
        public readonly array $duplicateDecisions = [],
    ) {}

    public function handle(GameCsvImporter $importer): void
    {
        try {
            if ($this->mode === 'replace') {
                $this->clearScope();
            }

            $result = $importer->import(
                Storage::path($this->path),
                $this->userId,
                $this->mode,
                $this->scopePlatformName(),
                $this->duplicateDecisions,
            );

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

    /**
     * Nombre de la plataforma del alcance de Reemplazar, para que
     * GameCsvImporter::import() pueda omitir filas de otra plataforma — se
     * resuelve aquí, en el momento de procesar el job, en vez de fiarse de un
     * nombre ya resuelto en el payload de la cola (podría haber cambiado
     * entre que se despachó y se procesó). null si el modo no es 'replace' o
     * el alcance es toda la colección: sin restricción por fila.
     */
    private function scopePlatformName(): ?string
    {
        if ($this->mode !== 'replace') {
            return null;
        }

        $type = $this->scope['type'] ?? 'all';

        if ($type === 'none') {
            return '';
        }

        if ($type === 'platform') {
            $platform = Platform::where('user_id', $this->userId)->find($this->scope['id']);

            return $platform !== null ? $platform->name : '';
        }

        return null;
    }

    /**
     * Envía a la papelera (soft-delete) el alcance elegido antes de importar
     * el CSV como colección nueva — mismo idioma que
     * PanelController::clearPlatformGames()/clearAllGames() (Zona de
     * peligro), incluyendo la invalidación manual de la caché de
     * estadísticas: un borrado en bloque por query builder no dispara el
     * evento 'deleted' de Eloquent.
     */
    private function clearScope(): void
    {
        $query = Game::where('user_id', $this->userId);

        $type = $this->scope['type'] ?? 'all';
        if ($type === 'platform') {
            $query->where('platform_id', $this->scope['id']);
        } elseif ($type === 'none') {
            $query->whereNull('platform_id');
        }

        $query->delete();
        Cache::forget(StatsController::cacheKey($this->userId));
    }
}
