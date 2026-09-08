<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Activado por defecto: mismo comportamiento que #152 tenía
            // antes de poder desactivarse (ver Game::needsAttention() y
            // games/_results.blade.php).
            $table->boolean('highlight_low_rating')->default(true)->after('games_view');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('highlight_low_rating');
        });
    }
};
