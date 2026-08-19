<?php

namespace Tests\Feature\Web;

use App\Models\Game;
use App\Models\Platform;
use App\Models\User;
use App\Services\GameLookup\IgdbGameMatch;
use App\Services\GameLookup\IgdbLookupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IgdbControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_igdb_search_lists_candidates_from_the_query(): void
    {
        $user = User::factory()->create();
        $platform = Platform::factory()->create(['name' => 'PC']);
        $game = Game::factory()->for($user)->create(['platform_id' => $platform->id, 'igdb_matched_at' => now()]);

        $this->mock(IgdbLookupService::class, function ($mock) {
            $mock->shouldReceive('search')
                ->once()
                ->with('otro título', 'PC', 8)
                ->andReturn([new IgdbGameMatch(
                    igdbId: 9,
                    title: 'Otro título',
                    platforms: 'PC',
                    developer: 'Dev Studio',
                    releaseDate: '2000-01-01',
                    genres: ['RPG'],
                    rating: 70.0,
                )]);
        });

        $response = $this->actingAs($user)->getJson("/games/{$game->id}/igdb-search?q=".urlencode('otro título'));

        $response->assertOk();
        $response->assertJson(['results' => [[
            'igdb_id' => 9,
            'title' => 'Otro título',
            'platforms' => 'PC',
            'developer' => 'Dev Studio',
            'release_date' => '2000-01-01',
            'genres' => ['RPG'],
            'rating' => 70.0,
        ]]]);
    }

    public function test_igdb_search_defaults_to_the_games_title_without_a_query(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create(['title' => 'Celeste', 'platform_id' => null, 'igdb_matched_at' => now()]);

        $this->mock(IgdbLookupService::class, function ($mock) {
            $mock->shouldReceive('search')->once()->with('Celeste', null, 8)->andReturn([]);
        });

        $this->actingAs($user)->getJson("/games/{$game->id}/igdb-search")
            ->assertOk()
            ->assertJson(['results' => []]);
    }

    public function test_igdb_search_is_forbidden_for_another_users_game(): void
    {
        $owner = User::factory()->create();
        $game = Game::factory()->for($owner)->create();

        $this->actingAs(User::factory()->create())->getJson("/games/{$game->id}/igdb-search")->assertForbidden();
    }

    public function test_igdb_apply_updates_the_game_and_redirects_to_its_detail_page(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create(['developer' => null, 'release_date' => null]);

        $response = $this->actingAs($user)->post("/games/{$game->id}/igdb-apply", [
            'igdb_id' => 42,
            'developer' => 'Dev Studio',
            'release_date' => '2015-06-01',
            'genres' => ['Action', 'RPG'],
            'rating' => 88.5,
        ]);

        $response->assertRedirect(route('web.games.show', $game));
        $fresh = $game->fresh();
        $this->assertSame('Dev Studio', $fresh->developer);
        $this->assertSame('2015-06-01', $fresh->release_date->format('Y-m-d'));
        $this->assertSame(42, $fresh->igdb_id);
        $this->assertSame(['Action', 'RPG'], $fresh->igdb_genres);
        $this->assertSame('88.50', $fresh->igdb_rating);
    }

    public function test_igdb_apply_overwrites_an_existing_developer_with_an_explicit_choice(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create(['developer' => 'Desarrollador anterior']);

        $this->actingAs($user)->post("/games/{$game->id}/igdb-apply", [
            'igdb_id' => 1,
            'developer' => 'Desarrollador correcto',
        ]);

        $this->assertSame('Desarrollador correcto', $game->fresh()->developer);
    }

    public function test_igdb_apply_keeps_the_existing_developer_when_the_chosen_result_has_none(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create(['developer' => 'Ya tenía uno']);

        $this->actingAs($user)->post("/games/{$game->id}/igdb-apply", ['igdb_id' => 1]);

        $this->assertSame('Ya tenía uno', $game->fresh()->developer);
    }

    public function test_igdb_apply_requires_an_igdb_id(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create();

        $this->actingAs($user)->post("/games/{$game->id}/igdb-apply", [])->assertSessionHasErrors('igdb_id');
    }

    public function test_igdb_apply_is_forbidden_for_another_users_game(): void
    {
        $owner = User::factory()->create();
        $game = Game::factory()->for($owner)->create();

        $this->actingAs(User::factory()->create())
            ->post("/games/{$game->id}/igdb-apply", ['igdb_id' => 1])
            ->assertForbidden();
    }

    public function test_igdb_artworks_lists_the_matched_games_artwork(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create(['igdb_id' => 305]);

        $this->mock(IgdbLookupService::class, function ($mock) {
            $mock->shouldReceive('artworks')->once()->with(305)->andReturn(['ar1abc', 'ar2def']);
        });

        $response = $this->actingAs($user)->getJson("/games/{$game->id}/igdb-artworks");

        $response->assertOk();
        $response->assertJson(['results' => [
            ['image_id' => 'ar1abc', 'thumb_url' => 'https://images.igdb.com/igdb/image/upload/t_screenshot_med/ar1abc.jpg'],
            ['image_id' => 'ar2def', 'thumb_url' => 'https://images.igdb.com/igdb/image/upload/t_screenshot_med/ar2def.jpg'],
        ]]);
    }

    public function test_igdb_artworks_returns_no_results_without_a_matched_igdb_id(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create(['igdb_id' => null]);

        $this->mock(IgdbLookupService::class, function ($mock) {
            $mock->shouldNotReceive('artworks');
        });

        $this->actingAs($user)->getJson("/games/{$game->id}/igdb-artworks")
            ->assertOk()
            ->assertJson(['results' => []]);
    }

    public function test_igdb_artworks_is_forbidden_for_another_users_game(): void
    {
        $owner = User::factory()->create();
        $game = Game::factory()->for($owner)->create(['igdb_id' => 305]);

        $this->actingAs(User::factory()->create())->getJson("/games/{$game->id}/igdb-artworks")->assertForbidden();
    }

    public function test_igdb_set_background_saves_the_chosen_image_id(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create(['igdb_background' => null]);

        $response = $this->actingAs($user)->post("/games/{$game->id}/igdb-background", ['image_id' => 'ar1abc']);

        $response->assertRedirect(route('web.games.show', $game));
        $this->assertSame('ar1abc', $game->fresh()->igdb_background);
    }

    public function test_igdb_set_background_clears_it_with_a_blank_image_id(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create(['igdb_background' => 'ar1abc']);

        $this->actingAs($user)->post("/games/{$game->id}/igdb-background", ['image_id' => '']);

        $this->assertNull($game->fresh()->igdb_background);
    }

    public function test_igdb_set_background_is_forbidden_for_another_users_game(): void
    {
        $owner = User::factory()->create();
        $game = Game::factory()->for($owner)->create();

        $this->actingAs(User::factory()->create())
            ->post("/games/{$game->id}/igdb-background", ['image_id' => 'ar1abc'])
            ->assertForbidden();
    }
}
