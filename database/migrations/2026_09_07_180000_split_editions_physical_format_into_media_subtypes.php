<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * #142: "físico" deja de ser un único valor de editions.format y se
     * desglosa en el soporte concreto (cartucho/disco/diskette/cassette/
     * otros) — digital y ciab se quedan igual que antes. Las filas
     * existentes con format='physical' no tienen forma de saber a qué
     * subtipo pertenecían, así que se migran a 'physical_disc' (el soporte
     * más común, y el único que tenía asociado la BD local/monsterserver en
     * el momento de escribir esto).
     */
    public function up(): void
    {
        // El constraint viejo (format IN ('physical','digital','ciab')) sigue
        // activo hasta que se suelta aquí: si el backfill de abajo corriera
        // antes de este DROP, "physical_disc" lo violaría igual que
        // "physical" iba a violar el nuevo en cuanto se añadiera.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE editions DROP CONSTRAINT IF EXISTS chk_editions_format');
        }

        DB::table('editions')->where('format', 'physical')->update(['format' => 'physical_disc']);

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE editions ADD CONSTRAINT chk_editions_format CHECK (format IN ('physical_cartridge', 'physical_disc', 'physical_floppy', 'physical_tape', 'physical_other', 'digital', 'ciab'))");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE editions DROP CONSTRAINT IF EXISTS chk_editions_format');
        }

        DB::table('editions')
            ->whereIn('format', ['physical_cartridge', 'physical_disc', 'physical_floppy', 'physical_tape', 'physical_other'])
            ->update(['format' => 'physical']);

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE editions ADD CONSTRAINT chk_editions_format CHECK (format IN ('physical', 'digital', 'ciab'))");
    }
};
