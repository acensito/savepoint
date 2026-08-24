<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Color de la barra de navegación superior: uno de los presets
            // de PanelController::NAVBAR_COLORS (indigo, el actual, por
            // defecto). Mismo patrón que theme/games_view: ajuste de cuenta,
            // no de instancia.
            $table->string('navbar_color')->default('indigo')->after('games_view');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('navbar_color');
        });
    }
};
