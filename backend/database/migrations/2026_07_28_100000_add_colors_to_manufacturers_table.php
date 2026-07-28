<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manufacturers', function (Blueprint $table) {
            // Colores de marca del chip. Las plataformas los heredan salvo que definan los suyos propios.
            $table->string('bg_color', 7)->default('#EEF2FF');
            $table->string('text_color', 7)->default('#4338CA');
            $table->string('border_color', 7)->default('#C7D2FE');
        });
    }

    public function down(): void
    {
        Schema::table('manufacturers', function (Blueprint $table) {
            $table->dropColumn(['bg_color', 'text_color', 'border_color']);
        });
    }
};
