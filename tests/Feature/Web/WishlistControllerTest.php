<?php

namespace Tests\Feature\Web;

use App\Models\Game;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WishlistControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_only_lists_the_authenticated_users_wishlist_games(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Game::factory()->for($user)->create(['title' => 'Mi deseo', 'status' => 'wishlist']);
        Game::factory()->for($user)->create(['title' => 'Ya lo tengo', 'status' => 'owned']);
        Game::factory()->for($otherUser)->create(['title' => 'Deseo ajeno', 'status' => 'wishlist']);

        $response = $this->actingAs($user)->get(route('web.wishlist.index'));

        $response->assertOk();
        $response->assertSee('Mi deseo');
        $response->assertDontSee('Ya lo tengo');
        $response->assertDontSee('Deseo ajeno');
    }

    public function test_index_sorts_by_priority_ascending_by_default(): void
    {
        $user = User::factory()->create();
        Game::factory()->for($user)->create(['title' => 'Baja', 'status' => 'wishlist', 'wishlist_priority' => 3]);
        Game::factory()->for($user)->create(['title' => 'Alta', 'status' => 'wishlist', 'wishlist_priority' => 1]);
        Game::factory()->for($user)->create(['title' => 'Media', 'status' => 'wishlist', 'wishlist_priority' => 2]);

        $response = $this->actingAs($user)->get(route('web.wishlist.index'));

        $titles = $response->viewData('games')->pluck('title')->all();
        $this->assertSame(['Alta', 'Media', 'Baja'], $titles);
    }

    public function test_index_searches_by_title(): void
    {
        $user = User::factory()->create();
        Game::factory()->for($user)->create(['title' => 'Hollow Knight', 'status' => 'wishlist']);
        Game::factory()->for($user)->create(['title' => 'Celeste', 'status' => 'wishlist']);

        $response = $this->actingAs($user)->get(route('web.wishlist.index', ['q' => 'Hollow']));

        $response->assertSee('Hollow Knight');
        $response->assertDontSee('Celeste');
    }

    public function test_create_form_can_be_rendered(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('web.wishlist.create'));

        $response->assertOk();
        $response->assertSee('Añadir a la lista de deseos');
        $response->assertSee('name="wishlist_priority"', false);
        $response->assertSee('name="wishlist_estimated_price"', false);
        $response->assertSee('name="wishlist_store"', false);
    }

    public function test_store_creates_a_wishlist_game_without_optional_fields(): void
    {
        $user = User::factory()->create();
        $platform = Platform::factory()->create();

        $response = $this->actingAs($user)->post(route('web.wishlist.store'), [
            'title' => 'Silksong',
            'platform_id' => $platform->id,
        ]);

        $response->assertRedirect(route('web.wishlist.index'));

        $game = Game::where('title', 'Silksong')->firstOrFail();
        $this->assertSame($user->id, $game->user_id);
        $this->assertSame('wishlist', $game->status);
        $this->assertSame('pending', $game->play_status);
        $this->assertSame($platform->id, $game->platform_id);
        $this->assertNull($game->wishlist_priority);
    }

    public function test_store_saves_priority_estimated_price_and_store(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('web.wishlist.store'), [
            'title' => 'Silksong',
            'wishlist_priority' => '1',
            'wishlist_estimated_price' => '39.99',
            'wishlist_store' => 'CEX',
        ]);

        $game = Game::where('title', 'Silksong')->firstOrFail();
        $this->assertSame(1, $game->wishlist_priority);
        $this->assertEquals(39.99, $game->wishlist_estimated_price);
        $this->assertSame('CEX', $game->wishlist_store);
    }

    public function test_store_rejects_a_priority_outside_the_1_to_3_range(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('web.wishlist.store'), [
            'title' => 'Silksong',
            'wishlist_priority' => '4',
        ]);

        $response->assertSessionHasErrors('wishlist_priority');
        $this->assertDatabaseCount('games', 0);
    }

    public function test_store_requires_a_title(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('web.wishlist.store'), []);

        $response->assertSessionHasErrors('title');
        $this->assertDatabaseCount('games', 0);
    }

    public function test_a_wishlist_game_never_appears_in_the_main_collection(): void
    {
        $user = User::factory()->create();
        Game::factory()->for($user)->create(['title' => 'Deseado', 'status' => 'wishlist']);
        Game::factory()->for($user)->create(['title' => 'En colección', 'status' => 'owned']);

        $response = $this->actingAs($user)->get('/');

        $response->assertSee('En colección');
        $response->assertDontSee('Deseado');
    }
}
