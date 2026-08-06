<?php

namespace Tests\Unit\Models;

use App\Models\Game;
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
}
