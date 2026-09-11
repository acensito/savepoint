<?php

namespace Tests\Feature\Web;

use App\Models\Edition;
use App\Models\Game;
use App\Models\Platform;
use App\Models\User;
use App\Services\Catalog\SeedCatalogCopier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Catálogo por cuenta (issue #175): cada usuario gestiona sus propias
 * ediciones, sin dato compartido con el resto — ver EditionPolicy.
 */
class EditionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_editions(): void
    {
        $this->get('/editions')->assertRedirect('/login');
    }

    public function test_the_normal_edition_exists_and_is_available_for_any_platform(): void
    {
        // SeedCatalogCopier crea una "Normal" por cuenta al darse de alta
        // (issue #175) — ya no es una fila global sembrada por migración.
        // Sin filas en edition_platform = disponible para cualquier
        // plataforma, incluidas las que se den de alta después.
        $user = User::factory()->create();
        app(SeedCatalogCopier::class)->copyTo($user);

        $edition = Edition::where('user_id', $user->id)->where('name', 'Normal')->firstOrFail();

        $this->assertCount(0, $edition->platforms);
    }

    public function test_index_lists_editions_with_their_platforms_and_game_count(): void
    {
        $user = User::factory()->create();
        $platform = Platform::factory()->for($user)->create(['name' => 'Switch']);
        $edition = Edition::factory()->for($user)->create(['name' => 'Coleccionista']);
        $edition->platforms()->attach($platform);
        Game::factory()->for($user)->create(['edition_id' => $edition->id]);

        $response = $this->actingAs($user)->get('/editions');

        $response->assertOk();
        $response->assertSee('Coleccionista');
        $response->assertSee('Switch');
    }

    public function test_index_does_not_list_another_users_editions(): void
    {
        $user = User::factory()->create();
        Edition::factory()->create(['name' => 'Ajena']);

        $response = $this->actingAs($user)->get('/editions');

        $response->assertOk();
        $response->assertDontSee('Ajena');
    }

    /**
     * Regresión: _form.blade.php referenciaba `Edition::FORMAT_PHYSICAL` sin
     * el namespace completo (`\App\Models\Edition`, ya usado dos líneas más
     * arriba para `Edition::FORMATS`) — los ficheros Blade compilados se
     * ejecutan en el namespace raíz, así que resolvía a la clase inexistente
     * `\Edition` y tumbaba /editions/create con un 500. Sin este test, ningún
     * caso existente llegaba a renderizar el formulario de alta con GET.
     */
    public function test_create_form_can_be_rendered(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/editions/create')->assertOk();
    }

    public function test_edit_form_can_be_rendered(): void
    {
        $user = User::factory()->create();
        $edition = Edition::factory()->for($user)->create();

        $this->actingAs($user)->get("/editions/{$edition->id}/edit")->assertOk();
    }

    public function test_user_cannot_view_the_edit_form_of_another_users_edition(): void
    {
        $user = User::factory()->create();
        $edition = Edition::factory()->create();

        $this->actingAs($user)->get("/editions/{$edition->id}/edit")->assertForbidden();
    }

    public function test_user_can_create_an_edition_with_platforms(): void
    {
        $user = User::factory()->create();
        $platform = Platform::factory()->for($user)->create();

        $response = $this->actingAs($user)->post('/editions', [
            'name' => 'Edición especial',
            'platform_ids' => [$platform->id],
        ]);

        $response->assertRedirect(route('web.editions.index'));

        $edition = Edition::where('name', 'Edición especial')->firstOrFail();
        $this->assertSame($user->id, $edition->user_id);
        $this->assertTrue($edition->platforms->contains($platform));
    }

    /**
     * Issue #175: una cuenta no puede enganchar su edición a la plataforma
     * de otra.
     */
    public function test_creating_an_edition_rejects_another_users_platform(): void
    {
        $user = User::factory()->create();
        $othersPlatform = Platform::factory()->create();

        $this->actingAs($user)->post('/editions', [
            'name' => 'Edición especial',
            'platform_ids' => [$othersPlatform->id],
        ])->assertSessionHasErrors('platform_ids.0');
    }

    public function test_creating_an_edition_without_a_format_defaults_to_physical_disc(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/editions', ['name' => 'Edición al vuelo']);

        $edition = Edition::where('name', 'Edición al vuelo')->firstOrFail();
        $this->assertSame(Edition::FORMAT_PHYSICAL_DISC, $edition->format);
    }

    /**
     * #142: físico desglosado en subtipos de soporte — cubre los cinco a la
     * vez para no repetir cinco tests casi idénticos.
     */
    public function test_user_can_create_an_edition_with_any_physical_media_subtype(): void
    {
        $user = User::factory()->create();

        foreach ([
            Edition::FORMAT_PHYSICAL_CARTRIDGE,
            Edition::FORMAT_PHYSICAL_DISC,
            Edition::FORMAT_PHYSICAL_FLOPPY,
            Edition::FORMAT_PHYSICAL_TAPE,
            Edition::FORMAT_PHYSICAL_OTHER,
        ] as $format) {
            $this->actingAs($user)->post('/editions', [
                'name' => "Edición {$format}",
                'format' => $format,
            ]);

            $edition = Edition::where('name', "Edición {$format}")->firstOrFail();
            $this->assertSame($format, $edition->format);
        }
    }

    public function test_user_can_create_an_edition_with_a_specific_format(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/editions', [
            'name' => 'Edición digital',
            'format' => Edition::FORMAT_DIGITAL,
        ]);

        $edition = Edition::where('name', 'Edición digital')->firstOrFail();
        $this->assertSame(Edition::FORMAT_DIGITAL, $edition->format);
    }

    public function test_creating_an_edition_rejects_an_invalid_format(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/editions', [
            'name' => 'Edición rara',
            'format' => 'cartridge',
        ])->assertSessionHasErrors('format');
    }

    public function test_user_can_update_an_editions_format(): void
    {
        $user = User::factory()->create();
        $edition = Edition::factory()->for($user)->create(['format' => Edition::FORMAT_PHYSICAL_DISC]);

        $response = $this->actingAs($user)->put("/editions/{$edition->id}", [
            'name' => $edition->name,
            'format' => Edition::FORMAT_CIAB,
        ]);

        $response->assertRedirect(route('web.editions.index'));
        $this->assertSame(Edition::FORMAT_CIAB, $edition->fresh()->format);
    }

    public function test_user_cannot_update_another_users_edition(): void
    {
        $user = User::factory()->create();
        $edition = Edition::factory()->create(['name' => 'Ajena']);

        $this->actingAs($user)->put("/editions/{$edition->id}", ['name' => 'Hijacked'])
            ->assertForbidden();

        $this->assertDatabaseHas('editions', ['id' => $edition->id, 'name' => 'Ajena']);
    }

    public function test_creating_an_edition_via_ajax_returns_json(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/editions', [
            'name' => 'Edición al vuelo',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('name', 'Edición al vuelo');
        // El JS del alta rápida (games/_form.blade.php) lo usa para que la
        // opción añadida al desplegable distinga ediciones con el mismo
        // nombre pero distinto formato, igual que las que ya vienen del
        // servidor (#142).
        $response->assertJsonPath('formatLabel', Edition::FORMATS[Edition::FORMAT_PHYSICAL_DISC]['label']);
        $this->assertDatabaseHas('editions', ['user_id' => $user->id, 'name' => 'Edición al vuelo']);
    }

    public function test_creating_an_edition_requires_a_name(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/editions', [])
            ->assertSessionHasErrors('name');
    }

    public function test_user_can_update_an_editions_platforms(): void
    {
        $user = User::factory()->create();
        $edition = Edition::factory()->for($user)->create();
        $oldPlatform = Platform::factory()->for($user)->create();
        $edition->platforms()->attach($oldPlatform);
        $newPlatform = Platform::factory()->for($user)->create();

        $response = $this->actingAs($user)->put("/editions/{$edition->id}", [
            'name' => $edition->name,
            'platform_ids' => [$newPlatform->id],
        ]);

        $response->assertRedirect(route('web.editions.index'));

        $edition->refresh();
        $this->assertFalse($edition->platforms->contains($oldPlatform));
        $this->assertTrue($edition->platforms->contains($newPlatform));
    }

    public function test_deleting_an_edition_nullifies_its_games(): void
    {
        $user = User::factory()->create();
        $edition = Edition::factory()->for($user)->create();
        $game = Game::factory()->for($user)->create(['edition_id' => $edition->id]);

        $this->actingAs($user)->delete("/editions/{$edition->id}")
            ->assertRedirect(route('web.editions.index'));

        $this->assertDatabaseMissing('editions', ['id' => $edition->id]);
        $this->assertDatabaseHas('games', ['id' => $game->id, 'edition_id' => null]);
    }

    public function test_user_cannot_delete_another_users_edition(): void
    {
        $user = User::factory()->create();
        $edition = Edition::factory()->create();

        $this->actingAs($user)->delete("/editions/{$edition->id}")->assertForbidden();

        $this->assertDatabaseHas('editions', ['id' => $edition->id]);
    }
}
