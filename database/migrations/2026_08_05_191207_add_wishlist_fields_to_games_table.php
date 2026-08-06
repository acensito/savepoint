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
        Schema::table('games', function (Blueprint $table) {
            // 1 = alta, 2 = media, 3 = baja (se ordena ascendente para que "alta" salga primero).
            $table->unsignedTinyInteger('wishlist_priority')->nullable()->after('status');
            $table->decimal('wishlist_estimated_price', 10, 2)->nullable()->after('wishlist_priority');
            $table->string('wishlist_store')->nullable()->after('wishlist_estimated_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['wishlist_priority', 'wishlist_estimated_price', 'wishlist_store']);
        });
    }
};
