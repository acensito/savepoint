<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dimensiones reales de la carátula subida/descargada, para poder fijar
     * width/height en <img> y evitar el salto de layout sin recodificar ni
     * redimensionar la imagen (#116: getimagesize() se limita a leer la
     * cabecera del fichero). null en carátulas anteriores a esta migración —
     * no hay backfill, el componente cae al comportamiento actual si faltan.
     */
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->unsignedSmallInteger('cover_width')->nullable()->after('cover');
            $table->unsignedSmallInteger('cover_height')->nullable()->after('cover_width');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['cover_width', 'cover_height']);
        });
    }
};
