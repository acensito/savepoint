<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo por cuenta (issue #175): Platform/Edition/Manufacturer dejan de
 * ser compartidos entre todas las cuentas — cada usuario gestiona los suyos,
 * igual que ya pasa con Game. Solo el esquema aquí (columna nullable de
 * momento): el backfill real vive en la siguiente migración, y el NOT NULL
 * final en la de después — hace falta este orden porque las filas existentes
 * no tienen todavía ningún user_id que ponerles hasta que corra el backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manufacturers', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->dropUnique('manufacturers_name_unique');
            $table->dropUnique('manufacturers_slug_unique');
            $table->unique(['user_id', 'name']);
            $table->unique(['user_id', 'slug']);
        });

        Schema::table('platforms', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->dropUnique('platforms_slug_unique');
            $table->unique(['user_id', 'slug']);
        });

        // editions no tiene ninguna unicidad hoy (ver create_editions_table),
        // así que no hace falta tocar ningún índice aquí.
        Schema::table('editions', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('manufacturers', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'name']);
            $table->dropUnique(['user_id', 'slug']);
            $table->dropConstrainedForeignId('user_id');
            $table->unique('name');
            $table->unique('slug');
        });

        Schema::table('platforms', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'slug']);
            $table->dropConstrainedForeignId('user_id');
            $table->unique('slug');
        });

        Schema::table('editions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
