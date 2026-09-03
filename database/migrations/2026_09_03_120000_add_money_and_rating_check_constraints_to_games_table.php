<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Solo Postgres soporta CHECK constraints con esta sintaxis (SQLite las
     * ignora en ALTER TABLE), así que no tienen efecto en los tests, que
     * corren contra SQLite salvo en la tanda de CI de #100.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE games ADD CONSTRAINT chk_games_rating_range CHECK (rating IS NULL OR rating BETWEEN 1 AND 5)');
        DB::statement('ALTER TABLE games ADD CONSTRAINT chk_games_price_paid CHECK (price_paid IS NULL OR price_paid >= 0)');
        DB::statement('ALTER TABLE games ADD CONSTRAINT chk_games_sale_price CHECK (sale_price IS NULL OR sale_price >= 0)');
        DB::statement('ALTER TABLE games ADD CONSTRAINT chk_games_wishlist_estimated_price CHECK (wishlist_estimated_price IS NULL OR wishlist_estimated_price >= 0)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE games DROP CONSTRAINT IF EXISTS chk_games_rating_range');
        DB::statement('ALTER TABLE games DROP CONSTRAINT IF EXISTS chk_games_price_paid');
        DB::statement('ALTER TABLE games DROP CONSTRAINT IF EXISTS chk_games_sale_price');
        DB::statement('ALTER TABLE games DROP CONSTRAINT IF EXISTS chk_games_wishlist_estimated_price');
    }
};
