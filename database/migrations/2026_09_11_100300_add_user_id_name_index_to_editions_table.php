<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo por cuenta (issue #175): a diferencia de platforms/manufacturers,
 * editions no tiene ninguna unicidad de la que colarse un índice por
 * user_id (ver 2026_09_11_100000_add_user_id_to_catalog_tables.php) — la
 * sola FOREIGN KEY no crea uno en Postgres. Con Edition::where('user_id',
 * ...) ya en casi una decena de sitios (GameController, WishlistController,
 * PanelController, EditionController, CommissionController, el importador
 * CSV...), y (name) detrás porque casi todos combinan el filtro con
 * orderBy('name') o una búsqueda por nombre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('editions', function (Blueprint $table) {
            $table->index(['user_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('editions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'name']);
        });
    }
};
