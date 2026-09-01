<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Cuándo se completó por primera vez un desafío de 2FA (o se
            // activó desde una sesión ya autenticada, ver
            // PanelController::updateToggle) — null significa "nunca", lo
            // que distingue una cuenta huérfana (registrada con 2FA activo
            // pero sin completar nunca el primer código) de una cuenta ya en
            // uso con un login a medias ahora mismo (ver issue #10).
            $table->timestamp('two_factor_verified_at')->nullable()->after('two_factor_code_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('two_factor_verified_at');
        });
    }
};
