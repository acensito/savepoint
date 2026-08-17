<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            // Mismo patrón que el resto de índices compuestos con user_id
            // (ver create_games_table.php): la nueva sección "En venta"
            // (ForSaleController) filtra siempre por estas dos columnas.
            $table->index(['user_id', 'for_sale']);
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'for_sale']);
        });
    }
};
