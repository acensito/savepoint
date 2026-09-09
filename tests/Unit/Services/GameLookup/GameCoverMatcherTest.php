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

    /**
     * Encontrado en real (#128): CEX suele listar el mismo juego más de una
     * vez (distinta condición/SKU, mismo título y plataforma) — "Kameo" y
     * "Aliens: Colonial Marines" en Xbox 360, por ejemplo. Un empate entre
     * dos entradas con el mismo título normalizado no es una ambigüedad real
     * (es el mismo juego duplicado), a diferencia del empate entre dos
     * títulos distintos de test_match_by_title_returns_null_on_a_real_tie().
     */
    public function test_match_by_title_does_not_treat_a_duplicate_listing_of_the_same_game_as_ambiguous(): void
    {
        $platform = Platform::factory()->create(['name' => 'Xbox 360']);
        $game = Game::factory()->create(['title' => 'Kameo', 'platform_id' => $platform->id]);

        $lookup = Mockery::mock(GameLookupInterface::class);
        $lookup->shouldReceive('search')->once()->andReturn([
            $first = new GameLookupResult(title: 'Kameo Elements Of Power', ean: '882224053594', coverUrl: 'https://x/kameo1.jpg', platform: 'Xbox 360'),
            new GameLookupResult(title: 'Kameo Elements Of Power', ean: '882224053595', coverUrl: 'https://x/kameo2.jpg', platform: 'Xbox 360'),
        ]);

        $result = $this->matcher($lookup)->matchByTitle($game);

        $this->assertSame($first, $result);
    }

    /**
     * Encontrado en real (#128): el usuario guarda "Aliens Colonial Marines"
     * sin dos puntos, CEX lo lista como "Aliens: Colonial Marines" — mismo
     * juego, distinta puntuación si no se normalizan igual (ver
     * normalizeTitle(), mismo criterio que IgdbLookupService).
     */
    /**
     * Encontrado en real (#128): entre duplicados del mismo juego en CEX, no
     * todos traen carátula — el matcher no puede quedarse con el primero sin
     * más, porque podría ser justo el que no tiene foto.
     */
    public function test_match_by_title_prefers_the_duplicate_that_has_a_cover(): void
    {
        $platform = Platform::factory()->create(['name' => 'Xbox 360']);
        $game = Game::factory()->create(['title' => 'Kameo', 'platform_id' => $platform->id]);

        $lookup = Mockery::mock(GameLookupInterface::class);
        $lookup->shouldReceive('search')->once()->andReturn([
            new GameLookupResult(title: 'Kameo Elements Of Power', ean: '882224053594', coverUrl: null, platform: 'Xbox 360'),
            $withCover = new GameLookupResult(title: 'Kameo Elements Of Power', ean: '882224053595', coverUrl: 'https://x/kameo.jpg', platform: 'Xbox 360'),
        ]);

        $result = $this->matcher($lookup)->matchByTitle($game);

        $this->assertSame($withCover, $result);
    }

    public function test_match_by_title_ignores_colons_when_comparing_titles(): void
    {
        $platform = Platform::factory()->create(['name' => 'Xbox 360']);
        $game = Game::factory()->create(['title' => 'Aliens Colonial Marines', 'platform_id' => $platform->id]);

        $lookup = Mockery::mock(GameLookupInterface::class);
        $lookup->shouldReceive('search')->once()->andReturn([
            $expected = new GameLookupResult(title: 'Aliens: Colonial Marines', ean: '5055277018246', coverUrl: 'https://x/aliens.jpg', platform: 'Xbox 360'),
            new GameLookupResult(title: 'Aliens: Colonial Marines Coll Ed (P)', ean: '2', coverUrl: 'https://x/collector.jpg', platform: 'PS3'),
        ]);

        $result = $this->matcher($lookup)->matchByTitle($game);

        $this->assertSame($expected, $result);
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
