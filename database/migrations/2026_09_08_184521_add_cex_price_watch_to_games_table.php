<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            // Aviso de bajada de precio en la lista de deseos (aparcada
            // PriceCharting por requerir suscripción de pago, ver issue #85):
            // precio actual de venta en CEX, cacheado en el propio juego y
            // consultado en segundo plano al abrir la wishlist (mismo motivo
            // que pricecharting_checked_at: no bloquear la carga con una
            // llamada HTTP externa, ver Jobs\FetchCexWishlistPrice). En
            // euros, sin ninguna conversión de divisa (a diferencia de
            // PriceCharting, ver CexGameLookupService::currentPrice()).
            $table->decimal('cex_current_price', 10, 2)->nullable()->after('wishlist_store');
            $table->timestamp('cex_checked_at')->nullable()->after('cex_current_price');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['cex_current_price', 'cex_checked_at']);
        });
    }
};
