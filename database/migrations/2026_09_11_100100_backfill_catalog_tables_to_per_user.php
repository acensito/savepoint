<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reparte el catálogo compartido (issue #175) en una copia privada por cada
 * cuenta ya existente, en vez de asignarlo todo a una sola (dejaría al resto
 * de cuentas con el desplegable de plataformas vacío de golpe — peor
 * regresión que la que esta migración quiere evitar). Toda cuenta acaba con
 * su propia copia del catálogo compartido de antes, incluida cualquier
 * plataforma/edición/fabricante "rara" que sus propios juegos ya
 * referenciaran (importados por CSV, renombrados a mano...).
 *
 * Orden: fabricantes → plataformas (resuelve manufacturer_id contra el mapa
 * de fabricantes) → ediciones → edition_platform (contra los dos mapas
 * anteriores) → repuntar games.platform_id/edition_id → borrar las filas
 * originales (user_id todavía null), ya sin nada que las referencie.
 */
return new class extends Migration
{
    public function up(): void
    {
        $userIds = DB::table('users')->pluck('id');

        if ($userIds->isEmpty()) {
            // Instalación nueva sin usuarios todavía: nada que repartir. El
            // catálogo semilla se copiará al primer usuario que se dé de
            // alta (ver SeedCatalogCopier), no aquí. Pero las filas
            // originales (p. ej. la "Normal" de seed_normal_edition.php) hay
            // que borrarlas igualmente: sin usuarios no hay juegos que
            // puedan referenciarlas, y dejarlas con user_id NULL rompería la
            // siguiente migración (NOT NULL en user_id) — este es
            // precisamente el estado con el que arranca cada test (
            // RefreshDatabase migra sobre una base sin usuarios todavía).
            DB::table('editions')->whereNull('user_id')->delete();
            DB::table('platforms')->whereNull('user_id')->delete();
            DB::table('manufacturers')->whereNull('user_id')->delete();

            return;
        }

        DB::transaction(function () use ($userIds) {
            $manufacturers = DB::table('manufacturers')->whereNull('user_id')->get();
            $platforms = DB::table('platforms')->whereNull('user_id')->get();
            $editions = DB::table('editions')->whereNull('user_id')->get();
            $editionPlatformPairs = DB::table('edition_platform')->get();

            $manufacturerMap = []; // [oldId][userId] => newId
            $platformMap = [];     // [oldId][userId] => newId
            $editionMap = [];      // [oldId][userId] => newId

            foreach ($userIds as $userId) {
                foreach ($manufacturers as $manufacturer) {
                    $manufacturerMap[$manufacturer->id][$userId] = DB::table('manufacturers')->insertGetId([
                        'user_id' => $userId,
                        'name' => $manufacturer->name,
                        'slug' => $manufacturer->slug,
                        'bg_color' => $manufacturer->bg_color,
                        'text_color' => $manufacturer->text_color,
                        'border_color' => $manufacturer->border_color,
                        'created_at' => $manufacturer->created_at,
                        'updated_at' => $manufacturer->updated_at,
                    ]);
                }
            }

            foreach ($userIds as $userId) {
                foreach ($platforms as $platform) {
                    $newManufacturerId = $platform->manufacturer_id !== null
                        ? ($manufacturerMap[$platform->manufacturer_id][$userId] ?? null)
                        : null;

                    $platformMap[$platform->id][$userId] = DB::table('platforms')->insertGetId([
                        'user_id' => $userId,
                        'name' => $platform->name,
                        'slug' => $platform->slug,
                        'label' => $platform->label,
                        'manufacturer_id' => $newManufacturerId,
                        'bg_color' => $platform->bg_color,
                        'text_color' => $platform->text_color,
                        'border_color' => $platform->border_color,
                        'created_at' => $platform->created_at,
                        'updated_at' => $platform->updated_at,
                    ]);
                }
            }

            foreach ($userIds as $userId) {
                foreach ($editions as $edition) {
                    $editionMap[$edition->id][$userId] = DB::table('editions')->insertGetId([
                        'user_id' => $userId,
                        'name' => $edition->name,
                        'format' => $edition->format,
                        'created_at' => $edition->created_at,
                        'updated_at' => $edition->updated_at,
                    ]);
                }
            }

            foreach ($userIds as $userId) {
                foreach ($editionPlatformPairs as $pair) {
                    $newEditionId = $editionMap[$pair->edition_id][$userId] ?? null;
                    $newPlatformId = $platformMap[$pair->platform_id][$userId] ?? null;

                    if ($newEditionId !== null && $newPlatformId !== null) {
                        DB::table('edition_platform')->insert([
                            'edition_id' => $newEditionId,
                            'platform_id' => $newPlatformId,
                        ]);
                    }
                }
            }

            // Repuntar cada juego existente a la copia de SU dueño, no a la
            // fila compartida original (que se borra al final de esta
            // migración) — sin esto, cualquier juego ya guardado se quedaría
            // con platform_id/edition_id apuntando a una fila inexistente.
            foreach ($platformMap as $oldPlatformId => $byUser) {
                foreach ($byUser as $userId => $newPlatformId) {
                    DB::table('games')
                        ->where('platform_id', $oldPlatformId)
                        ->where('user_id', $userId)
                        ->update(['platform_id' => $newPlatformId]);
                }
            }

            foreach ($editionMap as $oldEditionId => $byUser) {
                foreach ($byUser as $userId => $newEditionId) {
                    DB::table('games')
                        ->where('edition_id', $oldEditionId)
                        ->where('user_id', $userId)
                        ->update(['edition_id' => $newEditionId]);
                }
            }

            // users.default_edition_id (Ajustes → edición preseleccionada al
            // dar de alta un juego, ver 2026_08_14_210353_add_settings_
            // columns_to_users_table.php) tiene nullOnDelete(): sin este
            // repunte, cualquier usuario con una edición por defecto puesta
            // se quedaría con ella en blanco sin aviso al borrar las
            // ediciones originales más abajo, en vez de seguir apuntando a
            // su propia copia.
            foreach ($editionMap as $oldEditionId => $byUser) {
                foreach ($byUser as $userId => $newEditionId) {
                    DB::table('users')
                        ->where('id', $userId)
                        ->where('default_edition_id', $oldEditionId)
                        ->update(['default_edition_id' => $newEditionId]);
                }
            }

            // Las filas originales ya no las referencia ningún juego ni
            // ninguna cuenta (pasos de arriba) — platforms.manufacturer_id y
            // edition_platform de las originales se limpian solos vía
            // cascade/set null al borrarlas.
            DB::table('editions')->whereNull('user_id')->delete();
            DB::table('platforms')->whereNull('user_id')->delete();
            DB::table('manufacturers')->whereNull('user_id')->delete();
        });
    }

    public function down(): void
    {
        // No se revierte: no hay forma de deshacer un reparto por usuario
        // sin perder ediciones/plataformas que se hayan creado o modificado
        // después (mismo criterio que seed_normal_edition.php).
    }
};
