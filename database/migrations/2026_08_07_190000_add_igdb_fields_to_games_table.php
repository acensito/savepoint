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
            // Id del juego coincidente en IGDB, para poder distinguir "nunca se
            // ha buscado" (igdb_matched_at null) de "se buscó y no hubo match".
            $table->unsignedInteger('igdb_id')->nullable()->after('notes');
            // Aparte de 'genres' (en español, escritos a mano por el usuario):
            // los de IGDB vienen en inglés y no se mezclan con esos.
            $table->json('igdb_genres')->nullable()->after('igdb_id');
            // Nota agregada de IGDB (0-100), tal cual la da su API.
            $table->decimal('igdb_rating', 5, 2)->nullable()->after('igdb_genres');
            // Marca que ya se ha intentado enlazar con IGDB (con o sin éxito),
            // para no repetir la búsqueda automática en cada visita a la ficha.
            $table->timestamp('igdb_matched_at')->nullable()->after('igdb_rating');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['igdb_id', 'igdb_genres', 'igdb_rating', 'igdb_matched_at']);
        });
    }
};
