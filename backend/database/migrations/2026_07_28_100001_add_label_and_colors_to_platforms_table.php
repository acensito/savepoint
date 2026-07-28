<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platforms', function (Blueprint $table) {
            // Etiqueta corta mostrada en el chip (ej. "PS5"). Si es nula, se usa el nombre completo.
            $table->string('label', 20)->nullable()->after('name');

            // Colores propios de la plataforma. Nulos = hereda los del fabricante.
            $table->string('bg_color', 7)->nullable();
            $table->string('text_color', 7)->nullable();
            $table->string('border_color', 7)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('platforms', function (Blueprint $table) {
            $table->dropColumn(['label', 'bg_color', 'text_color', 'border_color']);
        });
    }
};
