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
            // Duraciones medias (en segundos) que da IGDB, sacadas originalmente
            // de HowLongToBeat: hastily/normally/completely + count. Ver
            // IgdbLookupService::timeToBeat().
            $table->json('igdb_time_to_beat')->nullable()->after('igdb_rating');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('igdb_time_to_beat');
        });
    }
};
