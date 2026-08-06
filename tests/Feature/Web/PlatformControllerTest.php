<?php

namespace Tests\Feature\Web;

use App\Models\Game;
use App\Models\Manufacturer;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_platforms(): void
    {
        $this->get('/platforms')->assertRedirect('/login');
    }

    public function test_index_lists_platforms_with_their_manufacturer(): void
    {
        $user = User::factory()->create();
        $manufacturer = Manufacturer::factory()->create(['name' => 'Nintendo']);
        Platform::factory()->for($manufacturer)->create(['name' => 'Switch']);

        $response = $this->actingAs($user)->get('/platforms');

        $response->assertOk();
        $response->assertSee('Switch');
        $response->assertSee('Nintendo');
    }

    public function test_user_can_create_a_platform_without_overriding_colors(): void
    {
        $user = User::factory()->create();
        $manufacturer = Manufacturer::factory()->create();

        $response = $this->actingAs($user)->post('/platforms', [
            'name' => 'PlayStation 5',
            'label' => 'PS5',
            'manufacturer_id' => $manufacturer->id,
        ]);

        $response->assertRedirect(route('web.platforms.index'));
        $this->assertDatabaseHas('platforms', [
            'name' => 'PlayStation 5',
            'slug' => 'playstation-5',
            'label' => 'PS5',
            'bg_color' => null,
        ]);
    }

    public function test_user_can_create_a_platform_with_overridden_colors(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/platforms', [
            'name' => 'Steam Deck',
            'override_colors' => '1',
            'bg_color' => '#111111',
            'text_color' => '#222222',
            'border_color' => '#333333',
        ]);

        $response->assertRedirect(route('web.platforms.index'));
        $this->assertDatabaseHas('platforms', [
            'name' => 'Steam Deck',
            'bg_color' => '#111111',
            'text_color' => '#222222',
            'border_color' => '#333333',
        ]);
    }

    public function test_creating_a_platform_requires_a_name(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/platforms', [])
            ->assertSessionHasErrors('name');
    }

    public function test_overridden_colors_are_required_when_override_is_checked(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/platforms', [
            'name' => 'Steam Deck',
            'override_colors' => '1',
        ])->assertSessionHasErrors(['bg_color', 'text_color', 'border_color']);
    }

    public function test_user_can_update_a_platform(): void
    {
        $user = User::factory()->create();
        $platform = Platform::factory()->create(['name' => 'Old']);

        $response = $this->actingAs($user)->put("/platforms/{$platform->id}", [
            'name' => 'New',
        ]);

        $response->assertRedirect(route('web.platforms.index'));
        $this->assertDatabaseHas('platforms', ['id' => $platform->id, 'name' => 'New', 'slug' => 'new']);
    }

    public function test_deleting_a_platform_nullifies_its_games(): void
    {
        $user = User::factory()->create();
        $platform = Platform::factory()->create();
        $game = Game::factory()->for($user)->create(['platform_id' => $platform->id]);

        $this->actingAs($user)->delete("/platforms/{$platform->id}")
            ->assertRedirect(route('web.platforms.index'));

        $this->assertDatabaseMissing('platforms', ['id' => $platform->id]);
        $this->assertDatabaseHas('games', ['id' => $game->id, 'platform_id' => null]);
    }
}
