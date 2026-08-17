<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->foreignId('platform_id')->nullable()->constrained()->nullOnDelete();
            // Texto libre a propósito, sin tabla de contactos nueva: mismo
            // criterio que la hoja de cálculo que sustituye esta tabla.
            $table->string('counterparty_name');
            // 'owed_to_me' (alguien me debe este juego, lo tiene/compra para
            // mí) | 'owed_by_me' (yo lo tengo/debo y hay que enviarlo).
            $table->string('direction');
            $table->decimal('price', 10, 2)->nullable();
            $table->date('purchased_at')->nullable();
            // Una sola columna para "recibido" o "enviado": su significado
            // depende de 'direction' (ver Commission::resolvedLabel()),
            // igual que en la hoja de cálculo original.
            $table->date('resolved_at')->nullable();
            // Solo se rellena cuando direction=owed_to_me y se marca
            // recibido (CommissionController::resolve()): enlaza al Game
            // real creado en ese momento. El registro de este encargo NUNCA
            // se borra ni se sustituye al resolverse, se queda como
            // histórico consultable indefinidamente.
            $table->foreignId('game_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'direction']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commissions');
    }
};
