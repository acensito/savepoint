<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Desactivado por defecto: no cambia el comportamiento actual
            // (los juegos en venta siguen mezclados en la colección) para
            // nadie que no lo active a propósito desde Ajustes. Activado,
            // GameController::filteredGamesQuery() los excluye del listado
            // sin filtrar — siguen viéndose con ?for_sale=1 o en su propia
            // sección (ForSaleController).
            $table->boolean('hide_for_sale_from_collection')->default(false)->after('quick_search_exclude_wishlist');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('hide_for_sale_from_collection');
        });
    }
};
