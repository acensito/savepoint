<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            // image_id de IGDB (identificador corto, no la URL completa: así se
            // puede pedir el tamaño que haga falta en cada sitio donde se use,
            // ver Game::backgroundUrl()), nunca se rellena solo: el usuario
            // elige el fondo a mano de una muestra (ver GameController::igdbArtworks()),
            // nunca se aplica automáticamente.
            $table->string('igdb_background')->nullable()->after('igdb_matched_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('igdb_background');
        });
    }
};
