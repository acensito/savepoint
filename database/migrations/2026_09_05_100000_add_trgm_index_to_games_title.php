<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * pg_trgm y los índices GIN son específicos de Postgres (SQLite no los
     * soporta), así que no tienen efecto en los tests, que corren contra
     * SQLite salvo en la tanda de CI de #100 — mismo guard que
     * add_money_and_rating_check_constraints_to_games_table.php.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Game::scopeSearch() hace whereLike('title', '%'.$term.'%', caseSensitive: false),
        // que compila a ILIKE '%...%': el comodín inicial impide usar el índice
        // B-tree (user_id, title) de create_games_table.php, así que cada
        // búsqueda escanea secuencialmente todos los juegos del usuario. pg_trgm
        // + un índice GIN sobre trigramas sí soporta LIKE/ILIKE con comodín en
        // ambos extremos.
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('CREATE INDEX games_title_trgm_index ON games USING gin (title gin_trgm_ops)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS games_title_trgm_index');
    }
};
