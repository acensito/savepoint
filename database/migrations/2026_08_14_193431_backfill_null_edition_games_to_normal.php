<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * "Normal" pasa a ser el valor por defecto para juegos nuevos (ver
     * games/_form.blade.php), así que los juegos que ya existían sin
     * edición asignada se igualan a mano aquí para que no se queden como
     * la única excepción "sin edición" del catálogo. Solo toca los que
     * tienen edition_id nulo: no pisa ninguna edición ya elegida a mano.
     */
    public function up(): void
    {
        $normalId = DB::table('editions')->where('name', 'Normal')->value('id');

        if ($normalId === null) {
            return;
        }

        DB::table('games')->whereNull('edition_id')->update(['edition_id' => $normalId]);
    }

    public function down(): void
    {
        // No se revierte: no hay forma de distinguir los juegos que ya
        // tenían "Normal" elegida a propósito antes de esta migración de
        // los que la recibieron aquí como relleno.
    }
};
