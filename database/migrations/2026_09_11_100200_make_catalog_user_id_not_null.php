<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Solo Postgres soporta ALTER COLUMN ... SET NOT NULL sin doctrine/dbal
 * instalado (mismo motivo que 2026_09_03_130000_add_enum_check_constraints_
 * to_games_editions_and_commissions.php) — no tiene efecto en los tests, que
 * corren contra SQLite salvo en la tanda de CI de #100. La capa de
 * aplicación (fillable + SeedCatalogCopier + validated() de cada
 * controlador) es la que de verdad garantiza que user_id siempre se rellena;
 * esto es solo una red de seguridad a nivel de esquema en producción.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE manufacturers ALTER COLUMN user_id SET NOT NULL');
        DB::statement('ALTER TABLE platforms ALTER COLUMN user_id SET NOT NULL');
        DB::statement('ALTER TABLE editions ALTER COLUMN user_id SET NOT NULL');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE manufacturers ALTER COLUMN user_id DROP NOT NULL');
        DB::statement('ALTER TABLE platforms ALTER COLUMN user_id DROP NOT NULL');
        DB::statement('ALTER TABLE editions ALTER COLUMN user_id DROP NOT NULL');
    }
};
