<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('password');
        });

        // Backfill: hasta ahora no existía ningún concepto de rol, así que no
        // hay "primer admin" que promover a mano — se marcan como admin todos
        // los usuarios que ya existan en este momento (hoy, solo las cuentas
        // semilla y la cuenta real del usuario), igual que el backfill de
        // región/edición en add_settings_columns_to_users_table. Cualquier
        // usuario dado de alta después de esta migración (desde el nuevo
        // panel de gestión) empieza sin el rol, salvo que se marque a mano.
        DB::table('users')->update(['is_admin' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }
};
