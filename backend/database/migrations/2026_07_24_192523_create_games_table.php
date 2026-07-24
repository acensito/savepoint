<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            
            // Datos básicos del juego
            $table->string('ean')->nullable();
            $table->string('title');
            $table->foreignId('platform_id')->nullable()->constrained('platforms')->onDelete('set null');
            $table->string('cover')->nullable();
            $table->longText('data')->nullable();
            $table->date('release_date')->nullable();
            $table->string('developer')->nullable();
            $table->json('genres')->nullable();
            
            // Estados
            $table->enum('status', ['owned', 'wishlist', 'preordered', 'borrowed'])->nullable();
            $table->enum('play_status', ['finished', 'playing', 'abandoned', 'backlog'])->nullable();
            
            // Condición y edición
            $table->foreignId('condition_id')->nullable()->constrained('conditions')->onDelete('set null');
            $table->foreignId('edition_id')->nullable()->constrained('editions')->onDelete('set null');
            
            // Notas y valoración
            $table->longText('notes')->nullable();
            $table->integer('rating')->nullable()->min(0)->max(10);
            
            // Datos de compra
            $table->decimal('price_paid', 10, 2)->nullable();
            $table->string('purchase_place')->nullable();
            $table->date('purchase_date')->nullable();
            
            // Datos físicos
            $table->enum('manual_status', ['included', 'missing', 'none', 'leaflet'])->nullable();
            $table->enum('region', ['PAL-ES', 'PAL-EU', 'PAL-UK', 'PAL-FR', 'PAL-DE', 'NTSC-U', 'NTSC-J', 'Other'])->nullable();
            $table->enum('age_rating', ['USK 0', 'USK 6', 'USK 12', 'USK 16', 'USK 18', 'ESRB E', 'ESRB T', 'ESRB M', 'CERO A (All)', 'CERO B (12+)', 'CERO C (15+)', 'CERO D (17+)', 'CERO Z (18+)', 'NOT RATED', 'PEGI 3', 'PEGI 7', 'PEGI 12', 'PEGI 16', 'PEGI 18'])->nullable();
            
            $table->timestamps();
            
            // Índices
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'play_status']);
            $table->index('platform_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};