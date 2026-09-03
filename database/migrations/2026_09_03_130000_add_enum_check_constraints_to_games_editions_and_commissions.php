<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Solo Postgres soporta CHECK constraints con esta sintaxis (SQLite las
     * ignora en ALTER TABLE), así que no tienen efecto en los tests, que
     * corren contra SQLite salvo en la tanda de CI de #100.
     *
     * 'sold' no está en Game::STATUSES (es un estado derivado que solo
     * escribe SalesController::markAsSold() vía soft-delete) pero sí tiene
     * que estar aquí explícitamente, o cualquier venta futura rompería
     * contra este constraint.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE games ADD CONSTRAINT chk_games_status CHECK (status IS NULL OR status IN ('owned', 'wishlist', 'sold'))");
        DB::statement("ALTER TABLE games ADD CONSTRAINT chk_games_play_status CHECK (play_status IS NULL OR play_status IN ('pending', 'playing', 'finished'))");
        DB::statement("ALTER TABLE editions ADD CONSTRAINT chk_editions_format CHECK (format IN ('physical', 'digital', 'ciab'))");
        DB::statement("ALTER TABLE commissions ADD CONSTRAINT chk_commissions_direction CHECK (direction IN ('owed_to_me', 'owed_by_me'))");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE games DROP CONSTRAINT IF EXISTS chk_games_status');
        DB::statement('ALTER TABLE games DROP CONSTRAINT IF EXISTS chk_games_play_status');
        DB::statement('ALTER TABLE editions DROP CONSTRAINT IF EXISTS chk_editions_format');
        DB::statement('ALTER TABLE commissions DROP CONSTRAINT IF EXISTS chk_commissions_direction');
    }
};
