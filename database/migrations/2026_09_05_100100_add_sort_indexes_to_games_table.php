<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            // GameController::SORTABLE_COLUMNS permite ordenar por estas tres
            // columnas, pero a diferencia de title/for_sale/las de wishlist no
            // tenían ningún índice compuesto con user_id: cada página ordenada
            // por precio/valoración/fecha de compra provocaba un filesort.
            // Mismo patrón que add_for_sale_index_to_games_table.php.
            $table->index(['user_id', 'price_paid']);
            $table->index(['user_id', 'rating']);
            $table->index(['user_id', 'purchase_date']);
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'price_paid']);
            $table->dropIndex(['user_id', 'rating']);
            $table->dropIndex(['user_id', 'purchase_date']);
        });
    }
};
