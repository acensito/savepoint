<?php

namespace Tests\Feature\Web;

use App\Models\Edition;
use App\Models\Game;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_editions(): void
    {
        $this->get('/editions')->assertRedirect('/login');
    }

    public function test_the_normal_edition_exists_and_is_available_for_any_platform(): void
    {
        // Poblada por la migración 2026_08_14_190156_seed_normal_edition, no
        // por un seeder (ver su docblock: los seeders no corren siempre en
        // producción). Sin filas en edition_platform = disponible para
        // cualquier plataforma, incluidas las que se den de alta después.
        $edition = Edition::where('name', 'Normal')->firstOrFail();

        $this->assertCount(0, $edition->platforms);
    }

    public function test_index_lists_editions_with_their_platforms_and_game_count(): void
    {
        $user = User::factory()->create();
        $platform = Platform::factory()->create(['name' => 'Switch']);
        $edition = Edition::factory()->create(['name' => 'Coleccionista']);
        $edition->platforms()->attach($platform);
        Game::factory()->for($user)->create(['edition_id' => $edition->id]);

        $response = $this->actingAs($user)->get('/editions');

        $response->assertOk();
        $response->assertSee('Coleccionista');
        $response->assertSee('Switch');
    }

    public function test_user_can_create_an_edition_with_platforms(): void
    {
        $user = User::factory()->create();
        $platform = Platform::factory()->create();

        $response = $this->actingAs($user)->post('/editions', [
            'name' => 'Edición especial',
            'platform_ids' => [$platform->id],
        ]);

        $response->assertRedirect(route('web.editions.index'));

        $edition = Edition::where('name', 'Edición especial')->firstOrFail();
        $this->assertTrue($edition->platforms->contains($platform));
    }

    public function test_creating_an_edition_without_a_format_defaults_to_physical(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/editions', ['name' => 'Edición al vuelo']);

        $edition = Edition::where('name', 'Edición al vuelo')->firstOrFail();
        $this->assertSame(Edition::FORMAT_PHYSICAL, $edition->format);
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
        $edition = Edition::factory()->create(['format' => Edition::FORMAT_PHYSICAL]);

        $response = $this->actingAs($user)->put("/editions/{$edition->id}", [
            'name' => $edition->name,
            'format' => Edition::FORMAT_CIAB,
        ]);

        $response->assertRedirect(route('web.editions.index'));
        $this->assertSame(Edition::FORMAT_CIAB, $edition->fresh()->format);
    }

    public function test_creating_an_edition_via_ajax_returns_json(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/editions', [
            'name' => 'Edición al vuelo',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('name', 'Edición al vuelo');
        $this->assertDatabaseHas('editions', ['name' => 'Edición al vuelo']);
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
        $edition = Edition::factory()->create();
        $oldPlatform = Platform::factory()->create();
        $edition->platforms()->attach($oldPlatform);
        $newPlatform = Platform::factory()->create();

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
        $edition = Edition::factory()->create();
        $game = Game::factory()->for($user)->create(['edition_id' => $edition->id]);

        $this->actingAs($user)->delete("/editions/{$edition->id}")
            ->assertRedirect(route('web.editions.index'));

        $this->assertDatabaseMissing('editions', ['id' => $edition->id]);
        $this->assertDatabaseHas('games', ['id' => $game->id, 'edition_id' => null]);
    }
}
