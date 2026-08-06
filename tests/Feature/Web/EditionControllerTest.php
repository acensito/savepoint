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
