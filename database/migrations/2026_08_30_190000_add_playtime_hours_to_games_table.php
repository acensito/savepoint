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
            // Tiempo de juego propio del usuario, en horas (admite medias
            // horas tipo "12.5"). Dato personal, distinto de la duración
            // media externa de igdb_time_to_beat.
            $table->decimal('playtime_hours', 6, 1)->nullable()->after('rating');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('playtime_hours');
        });
    }
};
