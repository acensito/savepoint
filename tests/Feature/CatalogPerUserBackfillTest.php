<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Issue #175: la migración de backfill (2026_09_11_100100_backfill_catalog_
 * tables_to_per_user.php) es la pieza de más riesgo de todo el cambio — es
 * la que reparte el catálogo compartido en una copia por cuenta sin dejar
 * ningún juego existente con una FK rota. RefreshDatabase por sí solo no la
 * ejercita de verdad: cuando las migraciones corren por primera vez en un
 * test no hay ningún usuario todavía, así que sale por el "return" temprano
 * (nada que repartir). Aquí se simulan a mano filas "antiguas" (user_id
 * null, imposible de crear ya por la vía normal una vez migrado del todo)
 * para volver a lanzar esa misma migración contra un escenario realista:
 * catálogo compartido + más de un usuario + juegos ya apuntando a él.
 */
class CatalogPerUserBackfillTest extends TestCase
{
    use RefreshDatabase;

    private function runBackfillMigration(): void
    {
        $migration = include database_path('migrations/2026_09_11_100100_backfill_catalog_tables_to_per_user.php');
        $migration->up();
    }

    public function test_backfill_gives_every_user_their_own_copy_of_the_shared_catalog(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        // Catálogo "antiguo" compartido, tal como estaba antes de esta
        // migración (user_id null) — ya no se puede crear así por la vía
        // normal (Eloquent) una vez el resto del catálogo por usuario está
        // en marcha, pero es justo el estado real que esta migración tiene
        // que encontrarse en una base de datos con datos ya cargados.
        $manufacturerId = DB::table('manufacturers')->insertGetId([
            'user_id' => null,
            'name' => 'Nintendo',
            'slug' => 'nintendo',
            'bg_color' => '#111111',
            'text_color' => '#222222',
            'border_color' => '#333333',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $platformId = DB::table('platforms')->insertGetId([
            'user_id' => null,
            'name' => 'Nintendo Switch',
            'slug' => 'nintendo-switch',
            'label' => 'Switch',
            'manufacturer_id' => $manufacturerId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $editionId = DB::table('editions')->insertGetId([
            'user_id' => null,
            'name' => 'Coleccionista',
            'format' => 'physical_cartridge',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('edition_platform')->insert([
            'edition_id' => $editionId,
            'platform_id' => $platformId,
        ]);

        $gameA = Game::factory()->for($userA)->create(['platform_id' => $platformId, 'edition_id' => $editionId]);
        $gameB = Game::factory()->for($userB)->create(['platform_id' => $platformId, 'edition_id' => $editionId]);

        // userA tiene esta edición como preseleccionada en Ajustes; userB no
        // tiene ninguna puesta (comportamiento por defecto).
        $userA->forceFill(['default_edition_id' => $editionId])->save();

        $this->runBackfillMigration();

        // Cada usuario tiene su propia copia, no la fila compartida original.
        $manufacturerA = DB::table('manufacturers')->where('user_id', $userA->id)->where('slug', 'nintendo')->first();
        $manufacturerB = DB::table('manufacturers')->where('user_id', $userB->id)->where('slug', 'nintendo')->first();
        $this->assertNotNull($manufacturerA);
        $this->assertNotNull($manufacturerB);
        $this->assertNotSame($manufacturerA->id, $manufacturerB->id);

        $platformA = DB::table('platforms')->where('user_id', $userA->id)->where('slug', 'nintendo-switch')->first();
        $platformB = DB::table('platforms')->where('user_id', $userB->id)->where('slug', 'nintendo-switch')->first();
        $this->assertNotNull($platformA);
        $this->assertNotNull($platformB);
        $this->assertNotSame($platformA->id, $platformB->id);
        // La plataforma de cada usuario apunta a SU PROPIA copia del fabricante.
        $this->assertSame($manufacturerA->id, $platformA->manufacturer_id);
        $this->assertSame($manufacturerB->id, $platformB->manufacturer_id);

        $editionA = DB::table('editions')->where('user_id', $userA->id)->where('name', 'Coleccionista')->first();
        $editionB = DB::table('editions')->where('user_id', $userB->id)->where('name', 'Coleccionista')->first();
        $this->assertNotNull($editionA);
        $this->assertNotNull($editionB);
        $this->assertNotSame($editionA->id, $editionB->id);

        // El pivote edition_platform se ha duplicado por usuario, no compartido.
        $this->assertTrue(
            DB::table('edition_platform')->where('edition_id', $editionA->id)->where('platform_id', $platformA->id)->exists()
        );
        $this->assertTrue(
            DB::table('edition_platform')->where('edition_id', $editionB->id)->where('platform_id', $platformB->id)->exists()
        );

        // Cada juego se repunta a la copia de SU dueño, no a la fila
        // compartida original (que ya no existe tras el paso siguiente).
        $this->assertSame($platformA->id, $gameA->fresh()->platform_id);
        $this->assertSame($editionA->id, $gameA->fresh()->edition_id);
        $this->assertSame($platformB->id, $gameB->fresh()->platform_id);
        $this->assertSame($editionB->id, $gameB->fresh()->edition_id);

        // Las filas compartidas originales han desaparecido.
        $this->assertDatabaseMissing('manufacturers', ['id' => $manufacturerId]);
        $this->assertDatabaseMissing('platforms', ['id' => $platformId]);
        $this->assertDatabaseMissing('editions', ['id' => $editionId]);

        // users.default_edition_id (nullOnDelete) se repunta a la copia del
        // propio usuario en vez de quedarse en null al borrar la original.
        $this->assertSame($editionA->id, $userA->fresh()->default_edition_id);
        $this->assertNull($userB->fresh()->default_edition_id);
    }

    public function test_backfill_does_nothing_when_there_are_no_users(): void
    {
        DB::table('users')->delete();

        // El único dato de catálogo que existe en una instalación nueva sin
        // usuarios es la edición "Normal" fija de seed_normal_edition.php
        // (2026_08_14_190156) — el backfill no debe tocarla ni lanzar
        // ninguna excepción con la tabla de usuarios vacía.
        $editionsBefore = DB::table('editions')->count();

        $this->runBackfillMigration();

        $this->assertSame(0, DB::table('manufacturers')->count());
        $this->assertSame(0, DB::table('platforms')->count());
        $this->assertSame($editionsBefore, DB::table('editions')->count());
    }
}
