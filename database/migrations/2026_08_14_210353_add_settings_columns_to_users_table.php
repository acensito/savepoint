<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Tema y vista de la colección: antes solo en localStorage del
            // navegador (sp:theme/sp:gamesView), ahora en la cuenta para que
            // sigan al usuario entre dispositivos. Mismos valores por
            // defecto que ya tenía cualquier visitante sin localStorage.
            $table->string('theme')->default('dark')->after('password');
            $table->string('games_view')->default('compact')->after('theme');

            // Orden/paginación por defecto del listado de la colección
            // (GameController::index). Sin default_sort explícito (null =
            // "más recientes", igual que hoy sin ?sort= en la URL).
            $table->string('default_sort')->nullable()->after('games_view');
            $table->string('default_dir')->default('desc')->after('default_sort');
            $table->unsignedSmallInteger('default_per_page')->default(20)->after('default_dir');

            // Región/edición preseleccionadas al dar de alta un juego
            // (games/_form.blade.php): sustituyen el fallback hardcodeado a
            // 'PAL-ES' y a la edición "Normal" buscada por nombre.
            $table->string('default_region')->nullable()->after('default_per_page');
            $table->foreignId('default_edition_id')->nullable()->after('default_region')
                ->constrained('editions')->nullOnDelete();

            // Búsqueda rápida (Ctrl+K): por defecto false para no cambiar el
            // comportamiento actual (hoy SÍ aparecen juegos de la wishlist).
            $table->boolean('quick_search_exclude_wishlist')->default(false)->after('default_edition_id');
        });

        // Backfill para usuarios ya existentes: sin esto, perderían de golpe
        // la región/edición que hoy se les preseleccionaba a mano (mismo
        // patrón que la migración backfill_null_edition_games_to_normal).
        // Los usuarios nuevos dados de alta después de esta migración
        // empiezan sin región/edición por defecto hasta que las configuren
        // en Ajustes: aceptable, esta app no tiene alta pública de usuarios.
        $normalId = DB::table('editions')->where('name', 'Normal')->value('id');

        DB::table('users')->update([
            'default_region' => 'PAL-ES',
            'default_edition_id' => $normalId,
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_edition_id');
            $table->dropColumn([
                'theme',
                'games_view',
                'default_sort',
                'default_dir',
                'default_per_page',
                'default_region',
                'quick_search_exclude_wishlist',
            ]);
        });
    }
};
