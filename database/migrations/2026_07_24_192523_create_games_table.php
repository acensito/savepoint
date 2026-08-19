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

            // Datos básicos
            $table->string('ean')->nullable();
            $table->string('title');
            $table->foreignId('platform_id')->nullable()->constrained('platforms')->onDelete('set null');
            $table->string('cover')->nullable();
            $table->longText('data')->nullable();
            $table->date('release_date')->nullable();
            $table->string('developer')->nullable();
            $table->json('genres')->nullable(); // En Postgres, Laravel lo traduce a JSONB (muy rápido)

            // Estados y condiciones (Cambiados a string para usar PHP Enums)
            $table->string('status')->nullable();
            $table->string('play_status')->nullable();
            $table->string('condition')->nullable();
            $table->foreignId('edition_id')->nullable()->constrained('editions')->onDelete('set null');

            // Notas y valoración
            $table->longText('notes')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();

            // Datos de compra
            $table->decimal('price_paid', 10, 2)->nullable();
            $table->string('purchase_place')->nullable();
            $table->date('purchase_date')->nullable();

            // Datos físicos
            $table->string('manual_status')->nullable();
            $table->string('region')->nullable();
            $table->string('age_rating')->nullable();

            $table->timestamps();
            $table->softDeletes(); // Papelera de reciclaje

            // Índices optimizados para las consultas más frecuentes
            $table->index(['user_id', 'title']);
            $table->index(['user_id', 'ean']);
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
