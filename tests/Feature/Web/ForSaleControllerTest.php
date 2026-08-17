<?php

namespace Tests\Feature\Web;

use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForSaleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_the_for_sale_page(): void
    {
        $this->get('/for-sale')->assertRedirect('/login');
    }

    public function test_index_only_lists_the_authenticated_users_for_sale_games(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Game::factory()->for($user)->create(['title' => 'Mío en venta', 'for_sale' => true]);
        Game::factory()->for($user)->create(['title' => 'Mío no en venta', 'for_sale' => false]);
        Game::factory()->for($otherUser)->create(['title' => 'Ajeno en venta', 'for_sale' => true]);

        $response = $this->actingAs($user)->get('/for-sale');

        $response->assertOk();
        $response->assertSee('Mío en venta');
        $response->assertDontSee('Mío no en venta');
        $response->assertDontSee('Ajeno en venta');
    }

    public function test_index_shows_an_empty_state_without_any_for_sale_games(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/for-sale');

        $response->assertOk();
        $response->assertSee('No tienes ningún juego marcado como en venta.');
    }

    public function test_for_sale_games_still_list_here_even_when_hidden_from_the_collection(): void
    {
        $user = User::factory()->create(['hide_for_sale_from_collection' => true]);
        Game::factory()->for($user)->create(['title' => 'En venta', 'for_sale' => true]);

        $response = $this->actingAs($user)->get('/for-sale');

        $response->assertOk();
        $response->assertSee('En venta');
    }
}
