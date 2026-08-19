<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edition_platform', function (Blueprint $table) {
            $table->foreignId('edition_id')->constrained('editions')->onDelete('cascade');
            $table->foreignId('platform_id')->constrained('platforms')->onDelete('cascade');
            $table->primary(['edition_id', 'platform_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edition_platform');
    }
};
