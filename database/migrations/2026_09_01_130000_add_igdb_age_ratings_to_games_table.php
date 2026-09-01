<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            // Lista cruda de {organization, value} que IGDB devolvió para el
            // juego (varios sistemas regionales a la vez, ver IgdbLookupService::
            // search()) — no es lo que se muestra (eso sigue siendo age_rating,
            // texto libre), sirve para acotar el desplegable del formulario a
            // solo las combinaciones reales del juego (ver issue #46).
            $table->json('igdb_age_ratings')->nullable()->after('igdb_time_to_beat');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('igdb_age_ratings');
        });
    }
};
