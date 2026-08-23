<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fila única de ajustes de instancia (no por cuenta, a diferencia de
 * panel/settings): hasta ahora esta app no tenía ningún concepto de
 * administrador global (ver README), esto abre esa puerta con el primer
 * caso real, si el registro público está abierto o no.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('registration_enabled')->default(true);
            $table->timestamps();
        });

        // Se crea ya la única fila que va a existir nunca: mismo
        // comportamiento que tenía la app hasta ahora (registro abierto),
        // así que aplicar esta migración no cambia nada por sí sola.
        DB::table('app_settings')->insert([
            'registration_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
