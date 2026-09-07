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
}
