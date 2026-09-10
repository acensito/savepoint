<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tres huecos encontrados en la auditoría de rendimiento del
     * 2026-09-10, ninguno cubierto por add_sort_indexes_to_games_table.php:
     *
     * - (user_id, created_at): es el orden POR DEFECTO de cada carga del
     *   listado sin ?sort= explícito (ver GameCollectionQuery::resolveSort(),
     *   "latest()->orderByDesc('id')") — justo el caso más frecuente,
     *   curiosamente el único de los "sortables" sin índice hasta ahora.
     * - (user_id, platform_id): platform_id ya tenía índice suelto, pero
     *   toda consulta real lo combina con user_id (GameCollectionQuery,
     *   GameTrashController, Jobs\IdentifyMissingGameCovers, PanelController
     *   al vaciar una plataforma...).
     * - edition_id: Postgres NO indexa automáticamente las columnas de
     *   claves foráneas (a diferencia de MySQL) — EditionController::index()
     *   hace un withCount('games') que sin esto es un escaneo completo de
     *   games por cada edición listada.
     */
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'platform_id']);
            $table->index('edition_id');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropIndex(['user_id', 'platform_id']);
            $table->dropIndex(['edition_id']);
        });
    }
};
