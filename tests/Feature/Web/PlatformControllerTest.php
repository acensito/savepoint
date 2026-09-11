<?php

namespace Tests\Feature\Web;

use App\Models\Game;
use App\Models\Manufacturer;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Catálogo por cuenta (issue #175): cada usuario gestiona sus propias
 * plataformas, sin dato compartido con el resto — ver PlatformPolicy.
 */
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
        $manufacturer = Manufacturer::factory()->for($user)->create(['name' => 'Nintendo']);
        Platform::factory()->for($user)->for($manufacturer)->create(['name' => 'Switch']);

        $response = $this->actingAs($user)->get('/platforms');

        $response->assertOk();
        $response->assertSee('Switch');
        $response->assertSee('Nintendo');
    }

    public function test_index_does_not_list_another_users_platforms(): void
    {
        $user = User::factory()->create();
        Platform::factory()->create(['name' => 'Ajena']);

        $response = $this->actingAs($user)->get('/platforms');

        $response->assertOk();
        $response->assertDontSee('Ajena');
    }

    public function test_user_can_create_a_platform_without_overriding_colors(): void
    {
        $user = User::factory()->create();
        $manufacturer = Manufacturer::factory()->for($user)->create();

        $response = $this->actingAs($user)->post('/platforms', [
            'name' => 'PlayStation 5',
            'label' => 'PS5',
            'manufacturer_id' => $manufacturer->id,
        ]);

        $response->assertRedirect(route('web.platforms.index'));
        $this->assertDatabaseHas('platforms', [
            'user_id' => $user->id,
            'name' => 'PlayStation 5',
            'slug' => 'playstation-5',
            'label' => 'PS5',
            'bg_color' => null,
        ]);
    }

    /**
     * Issue #175: una cuenta no puede enganchar su plataforma al fabricante
     * de otra.
     */
    public function test_creating_a_platform_rejects_another_users_manufacturer(): void
    {
        $user = User::factory()->create();
        $othersManufacturer = Manufacturer::factory()->create();

        $this->actingAs($user)->post('/platforms', [
            'name' => 'PlayStation 5',
            'manufacturer_id' => $othersManufacturer->id,
        ])->assertSessionHasErrors('manufacturer_id');
    }

    /**
     * Regresión de GeneratesUniqueSlug (issue #188, ahora compartido con
     * ManufacturerController): dos plataformas con el mismo nombre no deben
     * chocar en la columna slug (única por usuario desde #175).
     */
    public function test_creating_a_platform_with_a_duplicate_name_gets_a_suffixed_slug(): void
    {
        $user = User::factory()->create();
        Platform::factory()->for($user)->create(['name' => 'PlayStation 5', 'slug' => 'playstation-5']);

        $response = $this->actingAs($user)->post('/platforms', ['name' => 'PlayStation 5']);

        $response->assertRedirect(route('web.platforms.index'));
        $this->assertDatabaseHas('platforms', ['user_id' => $user->id, 'name' => 'PlayStation 5', 'slug' => 'playstation-5-1']);
    }

    /**
     * Issue #175: la unicidad del slug es por cuenta, así que dos usuarios
     * pueden tener cada uno su propia "PlayStation 5" con el mismo slug sin
     * chocar entre sí.
     */
    public function test_creating_a_platform_with_the_same_name_as_another_users_platform_does_not_get_suffixed(): void
    {
        $user = User::factory()->create();
        Platform::factory()->create(['name' => 'PlayStation 5', 'slug' => 'playstation-5']);

        $response = $this->actingAs($user)->post('/platforms', ['name' => 'PlayStation 5']);

        $response->assertRedirect(route('web.platforms.index'));
        $this->assertDatabaseHas('platforms', ['user_id' => $user->id, 'name' => 'PlayStation 5', 'slug' => 'playstation-5']);
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
            'user_id' => $user->id,
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
        $platform = Platform::factory()->for($user)->create(['name' => 'Old']);

        $response = $this->actingAs($user)->put("/platforms/{$platform->id}", [
            'name' => 'New',
        ]);

        $response->assertRedirect(route('web.platforms.index'));
        $this->assertDatabaseHas('platforms', ['id' => $platform->id, 'name' => 'New', 'slug' => 'new']);
    }

    public function test_user_cannot_view_the_edit_form_of_another_users_platform(): void
    {
        $user = User::factory()->create();
        $platform = Platform::factory()->create();

        $this->actingAs($user)->get("/platforms/{$platform->id}/edit")->assertForbidden();
    }

    public function test_user_cannot_update_another_users_platform(): void
    {
        $user = User::factory()->create();
        $platform = Platform::factory()->create(['name' => 'Ajena']);

        $this->actingAs($user)->put("/platforms/{$platform->id}", ['name' => 'Hijacked'])
            ->assertForbidden();

        $this->assertDatabaseHas('platforms', ['id' => $platform->id, 'name' => 'Ajena']);
    }

    public function test_deleting_a_platform_nullifies_its_games(): void
    {
        $user = User::factory()->create();
        $platform = Platform::factory()->for($user)->create();
        $game = Game::factory()->for($user)->create(['platform_id' => $platform->id]);

        $this->actingAs($user)->delete("/platforms/{$platform->id}")
            ->assertRedirect(route('web.platforms.index'));

        $this->assertDatabaseMissing('platforms', ['id' => $platform->id]);
        $this->assertDatabaseHas('games', ['id' => $game->id, 'platform_id' => null]);
    }

    public function test_user_cannot_delete_another_users_platform(): void
    {
        $user = User::factory()->create();
        $platform = Platform::factory()->create();

        $this->actingAs($user)->delete("/platforms/{$platform->id}")->assertForbidden();

        $this->assertDatabaseHas('platforms', ['id' => $platform->id]);
    }
}
