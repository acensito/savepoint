<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            // WishlistController::index() siempre filtra por (user_id, status
            // = 'wishlist') antes de ordenar por una de estas dos columnas
            // (?sort=wishlist_priority/wishlist_estimated_price, ver
            // SORTABLE_COLUMNS) — compuestos, no sueltas, para que el mismo
            // índice cubra el filtro y el orden en una sola pasada, mismo
            // patrón que (user_id, for_sale) en ForSaleController.
            $table->index(['user_id', 'status', 'wishlist_priority']);
            $table->index(['user_id', 'status', 'wishlist_estimated_price']);
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status', 'wishlist_priority']);
            $table->dropIndex(['user_id', 'status', 'wishlist_estimated_price']);
        });
    }
};
