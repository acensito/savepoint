<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Antes las credenciales de IGDB vivían solo en .env
            // (config('services.igdb')), compartidas por toda la instancia:
            // inviable para quien se despliega su propia copia de la app sin
            // usar la app de Twitch de otra persona. Ahora cada cuenta se da
            // de alta la suya (gratis, ver README) desde Ajustes; sin esto
            // desactivado, IgdbLookupService no llega a hacer ninguna
            // petición (ver AppServiceProvider). client_secret va cifrado en
            // reposo (cast 'encrypted' en el modelo) al ser, a diferencia
            // del client_id, un secreto real.
            $table->boolean('igdb_enabled')->default(false)->after('auto_igdb_background');
            $table->string('igdb_client_id')->nullable()->after('igdb_enabled');
            $table->text('igdb_client_secret')->nullable()->after('igdb_client_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['igdb_enabled', 'igdb_client_id', 'igdb_client_secret']);
        });
    }
};
