<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            // Marca que el match automático (IgdbGameMatcher) encontró varios
            // candidatos empatados a la mejor puntuación y no eligió ninguno
            // por su cuenta — ver issue #50. Se limpia en cuanto el usuario
            // resuelve el empate a mano (IgdbController::apply()).
            $table->boolean('igdb_match_ambiguous')->default(false)->after('igdb_matched_at');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('igdb_match_ambiguous');
        });
    }
};
