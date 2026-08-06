<?php

namespace Tests\Feature\Web;

use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_the_panel(): void
    {
        $this->get('/panel')->assertRedirect('/login');
    }

    public function test_panel_links_to_import_export_trash_and_profile(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/panel');

        $response->assertOk();
        $response->assertSee(route('web.games.import'), false);
        $response->assertSee(route('web.games.print'), false);
        $response->assertSee(route('web.games.trash'), false);
        $response->assertSee(route('web.profile.edit'), false);
    }

    public function test_panel_shows_the_trashed_games_count_for_the_authenticated_user_only(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Game::factory()->for($user)->create()->delete();
        Game::factory()->for($user)->create()->delete();
        Game::factory()->for($otherUser)->create()->delete();

        $response = $this->actingAs($user)->get('/panel');

        $response->assertSee('2 juegos en la papelera');
    }

    public function test_panel_shows_an_empty_trash_message_when_there_is_nothing_to_restore(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/panel');

        $response->assertSee('Vacía por ahora');
    }
}
