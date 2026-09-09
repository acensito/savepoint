<?php

namespace Tests\Unit\Services\GameLookup;

use App\Models\Game;
use App\Models\Platform;
use App\Services\GameLookup\GameCoverMatcher;
use App\Services\GameLookup\GameLookupInterface;
use App\Services\GameLookup\GameLookupResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class GameCoverMatcherTest extends TestCase
{
    use RefreshDatabase;

    private function matcher(GameLookupInterface $lookup): GameCoverMatcher
    {
        return new GameCoverMatcher($lookup);
    }

    public function test_match_by_ean_returns_the_result_with_the_exact_matching_ean(): void
    {
        $game = Game::factory()->create(['ean' => '5060146467315']);

        $lookup = Mockery::mock(GameLookupInterface::class);
        $lookup->shouldReceive('search')->once()->with('5060146467315')->andReturn([
            new GameLookupResult(title: 'Otro juego', ean: '1111111111111', coverUrl: 'https://x/other.jpg'),
            $expected = new GameLookupResult(title: 'Hollow Knight', ean: '5060146467315', coverUrl: 'https://x/hk.jpg'),
        ]);

        $result = $this->matcher($lookup)->matchByEan($game);

        $this->assertSame($expected, $result);
    }

    public function test_match_by_ean_returns_null_without_an_exact_ean_match(): void
    {
        $game = Game::factory()->create(['ean' => '5060146467315']);

        $lookup = Mockery::mock(GameLookupInterface::class);
        $lookup->shouldReceive('search')->once()->andReturn([
            new GameLookupResult(title: 'Otro juego', ean: '1111111111111', coverUrl: 'https://x/other.jpg'),
        ]);

        $this->assertNull($this->matcher($lookup)->matchByEan($game));
    }

    public function test_match_by_ean_returns_null_when_the_game_has_no_ean(): void
    {
        $game = Game::factory()->create(['ean' => null]);

        $lookup = Mockery::mock(GameLookupInterface::class);
        $lookup->shouldNotReceive('search');

        $this->assertNull($this->matcher($lookup)->matchByEan($game));
    }

    public function test_match_by_title_picks_the_result_with_exact_title_and_matching_platform(): void
    {
        $platform = Platform::factory()->create(['name' => 'Nintendo Switch']);
        $game = Game::factory()->create(['title' => 'Celeste', 'platform_id' => $platform->id]);

        $lookup = Mockery::mock(GameLookupInterface::class);
        $lookup->shouldReceive('search')->once()->with('Celeste')->andReturn([
            new GameLookupResult(title: 'Celeste Collector\'s Bundle', ean: '1', coverUrl: 'https://x/bundle.jpg', platform: 'PS4'),
            $expected = new GameLookupResult(title: 'Celeste', ean: '2', coverUrl: 'https://x/celeste.jpg', platform: 'Nintendo Switch'),
        ]);

        $result = $this->matcher($lookup)->matchByTitle($game);

        $this->assertSame($expected, $result);
    }

    /**
     * Mismo espíritu que IgdbGameMatcherTest (#50): con dos candidatos
     * empatados a la mejor puntuación, no hay forma fiable de elegir uno
     * solo — mejor no proponer nada que arriesgar una carátula/EAN
     * equivocados en un identificado en bloque.
     */
    public function test_match_by_title_returns_null_on_a_real_tie(): void
    {
        $game = Game::factory()->create(['title' => 'Ambiguous Game', 'platform_id' => null]);

        $lookup = Mockery::mock(GameLookupInterface::class);
        $lookup->shouldReceive('search')->once()->andReturn([
            new GameLookupResult(title: 'Ambiguous Game Collection', ean: '1', coverUrl: 'https://x/a.jpg'),
            new GameLookupResult(title: 'Ambiguous Game Anthology', ean: '2', coverUrl: 'https://x/b.jpg'),
        ]);

        $this->assertNull($this->matcher($lookup)->matchByTitle($game));
    }

    public function test_match_by_title_returns_null_without_any_reasonable_candidate(): void
    {
        $game = Game::factory()->create(['title' => 'Un juego cualquiera', 'platform_id' => null]);

        $lookup = Mockery::mock(GameLookupInterface::class);
        $lookup->shouldReceive('search')->once()->andReturn([
            new GameLookupResult(title: 'Algo completamente distinto', ean: '1', coverUrl: 'https://x/a.jpg', platform: 'PS4'),
        ]);

        $this->assertNull($this->matcher($lookup)->matchByTitle($game));
    }

    public function test_match_by_title_returns_null_without_any_results(): void
    {
        $game = Game::factory()->create(['title' => 'Celeste']);

        $lookup = Mockery::mock(GameLookupInterface::class);
        $lookup->shouldReceive('search')->once()->andReturn([]);

        $this->assertNull($this->matcher($lookup)->matchByTitle($game));
    }
}
