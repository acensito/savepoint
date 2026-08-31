<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Todas activas por defecto (issue #32): apagar una solo oculta su
            // acceso (sidebar, rutas) sin tocar los datos que ya tuviera.
            $table->boolean('section_wishlist_enabled')->default(true)->after('two_factor_code_expires_at');
            $table->boolean('section_commissions_enabled')->default(true)->after('section_wishlist_enabled');
            $table->boolean('section_for_sale_enabled')->default(true)->after('section_commissions_enabled');
            $table->boolean('section_sales_enabled')->default(true)->after('section_for_sale_enabled');
            $table->boolean('section_stats_enabled')->default(true)->after('section_sales_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'section_wishlist_enabled',
                'section_commissions_enabled',
                'section_for_sale_enabled',
                'section_sales_enabled',
                'section_stats_enabled',
            ]);
        });
    }
};
