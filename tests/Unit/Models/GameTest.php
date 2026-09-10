<?php

namespace Tests\Unit\Models;

use App\Models\Game;
use App\Models\Platform;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GameTest extends TestCase
{
    #[DataProvider('titles')]
    public function test_cover_initials(string $title, string $expected): void
    {
        $game = new Game(['title' => $title]);

        $this->assertSame($expected, $game->coverInitials());
    }

    public static function titles(): array
    {
        return [
            'two words' => ['The Legend of Zelda', 'TL'],
            'one word' => ['Tetris', 'T'],
            'extra whitespace' => ['  Super   Mario  ', 'SM'],
            'lowercase' => ['minecraft dungeons', 'MD'],
            'blank title' => ['', '?'],
        ];
    }

    public function test_cover_url_is_null_without_a_cover(): void
    {
        $game = new Game(['title' => 'No Cover']);

        $this->assertNull($game->coverUrl());
    }

    /**
     * Issue #187: 720p por defecto, no 1080p — indistinguible en la franja
     * de ~200px de alto donde se pinta (games/show.blade.php), pero bastante
     * más ligero.
     */
    public function test_background_url_defaults_to_the_720p_size(): void
    {
        $game = new Game(['igdb_background' => 'ar1abc']);

        $this->assertSame('https://images.igdb.com/igdb/image/upload/t_720p/ar1abc.jpg', $game->backgroundUrl());
    }

    public function test_background_url_accepts_a_custom_size(): void
    {
        $game = new Game(['igdb_background' => 'ar1abc']);

        $this->assertSame('https://images.igdb.com/igdb/image/upload/t_thumb/ar1abc.jpg', $game->backgroundUrl('thumb'));
    }

    public function test_background_url_is_null_without_a_background(): void
    {
        $game = new Game(['title' => 'Sin fondo']);

        $this->assertNull($game->backgroundUrl());
    }

    public function test_cover_placeholder_colors_uses_the_platform_colors_when_assigned(): void
    {
        $platform = new Platform(['bg_color' => '#111111', 'text_color' => '#222222', 'border_color' => '#333333']);
        $game = new Game(['title' => 'Celeste']);
        $game->setRelation('platform', $platform);

        $this->assertSame(
            ['bg' => '#111111', 'text' => '#222222', 'border' => '#333333'],
            $game->coverPlaceholderColors(),
        );
    }

    public function test_cover_placeholder_colors_are_deterministic_without_a_platform(): void
    {
        $first = new Game(['title' => 'Celeste']);
        $second = new Game(['title' => 'Celeste']);
        $other = new Game(['title' => 'Chrono Trigger']);

        $this->assertSame($first->coverPlaceholderColors(), $second->coverPlaceholderColors());
        $this->assertNotSame($first->coverPlaceholderColors(), $other->coverPlaceholderColors());
    }

    #[DataProvider('ratingsAndWhetherTheyNeedAttention')]
    public function test_needs_attention(?int $rating, bool $expected): void
    {
        $game = new Game(['rating' => $rating]);

        $this->assertSame($expected, $game->needsAttention());
    }

    public static function ratingsAndWhetherTheyNeedAttention(): array
    {
        return [
            'malo (1)' => [1, true],
            'regular (2)' => [2, true],
            'bueno (3)' => [3, false],
            'muy bueno (4)' => [4, false],
            'nuevo/precintado (5)' => [5, false],
            'sin valorar' => [null, false],
        ];
    }

    public function test_has_reached_wishlist_price_when_cex_is_at_or_below_target(): void
    {
        $atTarget = new Game(['wishlist_estimated_price' => '50.00', 'cex_current_price' => '50.00']);
        $belowTarget = new Game(['wishlist_estimated_price' => '50.00', 'cex_current_price' => '45.00']);
        $aboveTarget = new Game(['wishlist_estimated_price' => '50.00', 'cex_current_price' => '55.00']);

        $this->assertTrue($atTarget->hasReachedWishlistPrice());
        $this->assertTrue($belowTarget->hasReachedWishlistPrice());
        $this->assertFalse($aboveTarget->hasReachedWishlistPrice());
    }

    public function test_has_reached_wishlist_price_is_false_without_a_target_price(): void
    {
        $game = new Game(['wishlist_estimated_price' => null, 'cex_current_price' => '10.00']);

        $this->assertFalse($game->hasReachedWishlistPrice());
    }

    public function test_has_reached_wishlist_price_is_false_without_a_cex_price_yet(): void
    {
        $game = new Game(['wishlist_estimated_price' => '50.00', 'cex_current_price' => null]);

        $this->assertFalse($game->hasReachedWishlistPrice());
    }
}
