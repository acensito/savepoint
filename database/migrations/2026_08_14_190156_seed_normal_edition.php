<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Garantiza que exista una edición "Normal" en cualquier entorno (los
     * seeders no son un paso garantizado en producción, ver CHANGELOG del
     * 2026-08-06). Se deja sin filas en edition_platform a propósito: una
     * edición sin plataformas asociadas ya se considera "disponible para
     * cualquier plataforma" (mismo criterio que games/_form.blade.php y el
     * alta al vuelo de ediciones), así que cubre también las plataformas que
     * se den de alta más adelante sin tener que engancharla una a una.
     *
     * Si "Normal" ya existía (encontrada en producción con las 28 plataformas
     * de entonces enganchadas a mano una a una), se sueltan esas filas del
     * pivote a propósito: quedarse con la lista fija se habría quedado corta
     * en cuanto se diera de alta una plataforma nueva.
     */
    public function up(): void
    {
        $editionId = DB::table('editions')->where('name', 'Normal')->value('id');

        if ($editionId === null) {
            $editionId = DB::table('editions')->insertGetId([
                'name' => 'Normal',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('edition_platform')->where('edition_id', $editionId)->delete();
    }

    public function down(): void
    {
        // No se revierte: para cuando esta migración ya corrió, "Normal"
        // puede tener juegos reales apuntando a ella (edition_id), y no hay
        // forma de distinguir esos de los que ya existían antes. Borrarla
        // aquí les pondría edition_id a null sin que nadie lo haya pedido.
    }
};
