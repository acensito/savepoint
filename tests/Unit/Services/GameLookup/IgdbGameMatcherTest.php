<?php

namespace Tests\Unit\Services\GameLookup;

use App\Models\Game;
use App\Models\Platform;
use App\Services\GameLookup\AgeRatingResolver;
use App\Services\GameLookup\IgdbGameMatch;
use App\Services\GameLookup\IgdbGameMatcher;
use App\Services\GameLookup\IgdbLookupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class IgdbGameMatcherTest extends TestCase
{
    use RefreshDatabase;

    private function matcher(IgdbLookupService $igdbLookup): IgdbGameMatcher
    {
        return new IgdbGameMatcher($igdbLookup, new AgeRatingResolver);
    }

    public function test_match_if_needed_fills_developer_release_date_genres_rating_time_to_beat_and_igdb_id(): void
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
                ageRatings: null,
            )]);
        $igdbLookup->shouldReceive('timeToBeat')
            ->once()
            ->with(305)
            ->andReturn(['hastily' => 36000, 'normally' => 64800, 'completely' => 115200, 'count' => 150]);

        $this->matcher($igdbLookup)->matchIfNeeded($game);

        $this->assertSame('Maddy Makes Games', $game->developer);
        $this->assertSame('2018-01-25', $game->release_date->format('Y-m-d'));
        $this->assertSame(305, $game->igdb_id);
        $this->assertSame(['Platform', 'Indie'], $game->igdb_genres);
        $this->assertSame('87.65', $game->igdb_rating);
        $this->assertSame(['hastily' => 36000, 'normally' => 64800, 'completely' => 115200, 'count' => 150], $game->igdb_time_to_beat);
        $this->assertNotNull($game->igdb_matched_at);
    }

    public function test_match_if_needed_does_nothing_once_already_matched(): void
    {
        $game = Game::factory()->create(['igdb_matched_at' => now()]);

        $igdbLookup = Mockery::mock(IgdbLookupService::class);
        $igdbLookup->shouldNotReceive('search');
        $igdbLookup->shouldNotReceive('timeToBeat');

        $this->matcher($igdbLookup)->matchIfNeeded($game);
    }

    public function test_match_if_needed_marks_matched_even_when_there_is_no_result(): void
    {
        $game = Game::factory()->create();

        $igdbLookup = Mockery::mock(IgdbLookupService::class);
        $igdbLookup->shouldReceive('search')->once()->andReturn([]);
        $igdbLookup->shouldNotReceive('timeToBeat');

        $this->matcher($igdbLookup)->matchIfNeeded($game);

        $this->assertNotNull($game->igdb_matched_at);
        $this->assertNull($game->igdb_id);
        $this->assertNull($game->igdb_time_to_beat);
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
            ageRatings: null,
        )]);
        $igdbLookup->shouldReceive('timeToBeat')->once()->with(1)->andReturn(null);

        $this->matcher($igdbLookup)->matchIfNeeded($game);

        $this->assertSame('Mi desarrollador', $game->developer);
        $this->assertSame('2010-01-01', $game->release_date->format('Y-m-d'));
        // igdb_id sí se guarda siempre, aunque developer/release_date no se toquen.
        $this->assertSame(1, $game->igdb_id);
    }

    /**
     * (#46): age_rating se elige según la región del juego (ver
     * AgeRatingResolver) entre las clasificaciones que IGDB devuelve.
     */
    public function test_match_if_needed_fills_age_rating_according_to_region_and_stores_the_raw_list(): void
    {
        $game = Game::factory()->create(['region' => 'NTSC-J', 'age_rating' => null]);

        $ageRatings = [
            ['organization' => 'PEGI', 'value' => '12'],
            ['organization' => 'CERO', 'value' => 'C'],
        ];

        $igdbLookup = Mockery::mock(IgdbLookupService::class);
        $igdbLookup->shouldReceive('search')->once()->andReturn([new IgdbGameMatch(
            igdbId: 42,
            title: 'X',
            platforms: null,
            developer: null,
            releaseDate: null,
            genres: null,
            rating: null,
            ageRatings: $ageRatings,
        )]);
        $igdbLookup->shouldReceive('timeToBeat')->once()->with(42)->andReturn(null);

        $this->matcher($igdbLookup)->matchIfNeeded($game);

        $this->assertSame('CERO C', $game->age_rating);
        $this->assertSame($ageRatings, $game->igdb_age_ratings);
    }

    /**
     * (#46): igdb_age_ratings se guarda siempre (informativo, acota el
     * desplegable del formulario), aunque age_rating ya viniera puesto a
     * mano y no se toque.
     */
    public function test_match_if_needed_never_overwrites_an_existing_age_rating_but_still_stores_the_raw_igdb_list(): void
    {
        $game = Game::factory()->create(['region' => 'PAL-ES', 'age_rating' => 'PEGI 18']);

        $ageRatings = [['organization' => 'PEGI', 'value' => '12']];

        $igdbLookup = Mockery::mock(IgdbLookupService::class);
        $igdbLookup->shouldReceive('search')->once()->andReturn([new IgdbGameMatch(
            igdbId: 7,
            title: 'X',
            platforms: null,
            developer: null,
            releaseDate: null,
            genres: null,
            rating: null,
            ageRatings: $ageRatings,
        )]);
        $igdbLookup->shouldReceive('timeToBeat')->once()->with(7)->andReturn(null);

        $this->matcher($igdbLookup)->matchIfNeeded($game);

        $this->assertSame('PEGI 18', $game->age_rating);
        $this->assertSame($ageRatings, $game->igdb_age_ratings);
    }
}
