<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('two_factor_enabled')->default(false)->after('avatar_path');
            // Hasheado (igual que password), nunca en claro; limpiado tras
            // verificarse o al generar uno nuevo.
            $table->string('two_factor_code')->nullable()->after('two_factor_enabled');
            $table->timestamp('two_factor_code_expires_at')->nullable()->after('two_factor_code');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_enabled', 'two_factor_code', 'two_factor_code_expires_at']);
        });
    }
};
