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
            $table->boolean('for_sale')->default(false)->after('status');
            $table->decimal('sale_price', 10, 2)->nullable()->after('price_paid');
            $table->date('sold_at')->nullable()->after('sale_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['for_sale', 'sale_price', 'sold_at']);
        });
    }
};
