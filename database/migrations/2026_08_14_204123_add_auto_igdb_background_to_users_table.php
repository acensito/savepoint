<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Desactivado por defecto: no cambia el comportamiento actual
            // (elegir el fondo a mano) para nadie que no lo active a
            // propósito desde el panel de control.
            $table->boolean('auto_igdb_background')->default(false)->after('password');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('auto_igdb_background');
        });
    }
};
