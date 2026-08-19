<?php

namespace Tests\Unit\Services\GameLookup;

use App\Models\Game;
use App\Models\Platform;
use App\Services\GameLookup\IgdbGameMatch;
use App\Services\GameLookup\IgdbGameMatcher;
use App\Services\GameLookup\IgdbLookupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class IgdbGameMatcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_match_if_needed_fills_developer_release_date_genres_rating_and_igdb_id(): void
    {
        $platform = Platform::factory()->create(['name' => 'Nintendo Switch']);
        $game = Game::factory()->create([
            'title' => 'Celeste',
            'platform_id' => $platform->id,
            'developer' => null,
            'release_date' => null,
        ]);

        $igdbLookup = Mockery::mock(IgdbLookupService::class);
        $igdbLookup->shouldReceive('search')
            ->once()
            ->with('Celeste', 'Nintendo Switch', 10)
            ->andReturn([new IgdbGameMatch(
                igdbId: 305,
                title: 'Celeste',
                platforms: 'Nintendo Switch',
                developer: 'Maddy Makes Games',
                releaseDate: '2018-01-25',
                genres: ['Platform', 'Indie'],
                rating: 87.65,
            )]);

        (new IgdbGameMatcher($igdbLookup))->matchIfNeeded($game);

        $this->assertSame('Maddy Makes Games', $game->developer);
        $this->assertSame('2018-01-25', $game->release_date->format('Y-m-d'));
        $this->assertSame(305, $game->igdb_id);
        $this->assertSame(['Platform', 'Indie'], $game->igdb_genres);
        $this->assertSame('87.65', $game->igdb_rating);
        $this->assertNotNull($game->igdb_matched_at);
    }

    public function test_match_if_needed_does_nothing_once_already_matched(): void
    {
        $game = Game::factory()->create(['igdb_matched_at' => now()]);

        $igdbLookup = Mockery::mock(IgdbLookupService::class);
        $igdbLookup->shouldNotReceive('search');

        (new IgdbGameMatcher($igdbLookup))->matchIfNeeded($game);
    }

    public function test_match_if_needed_marks_matched_even_when_there_is_no_result(): void
    {
        $game = Game::factory()->create();

        $igdbLookup = Mockery::mock(IgdbLookupService::class);
        $igdbLookup->shouldReceive('search')->once()->andReturn([]);

        (new IgdbGameMatcher($igdbLookup))->matchIfNeeded($game);

        $this->assertNotNull($game->igdb_matched_at);
        $this->assertNull($game->igdb_id);
    }

    public function test_match_if_needed_never_overwrites_an_existing_developer_or_release_date(): void
    {
        $game = Game::factory()->create(['developer' => 'Mi desarrollador', 'release_date' => '2010-01-01']);

        $igdbLookup = Mockery::mock(IgdbLookupService::class);
        $igdbLookup->shouldReceive('search')->once()->andReturn([new IgdbGameMatch(
            igdbId: 1,
            title: 'X',
            platforms: null,
            developer: 'Otro desarrollador',
            releaseDate: '2020-05-05',
            genres: null,
            rating: null,
        )]);

        (new IgdbGameMatcher($igdbLookup))->matchIfNeeded($game);

        $this->assertSame('Mi desarrollador', $game->developer);
        $this->assertSame('2010-01-01', $game->release_date->format('Y-m-d'));
        // igdb_id sí se guarda siempre, aunque developer/release_date no se toquen.
        $this->assertSame(1, $game->igdb_id);
    }
}
