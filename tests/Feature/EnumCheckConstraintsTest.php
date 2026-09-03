<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Commission;
use App\Models\Edition;
use App\Models\Game;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EnumCheckConstraintsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Los CHECK constraints solo existen en Postgres (ver #101).');
        }
    }

    public function test_invalid_game_status_is_rejected(): void
    {
        $this->expectException(QueryException::class);

        Game::query()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Test',
            'platform_id' => Platform::factory()->create()->id,
            'status' => 'not-a-real-status',
        ]);
    }

    public function test_sold_is_a_valid_game_status(): void
    {
        $game = Game::query()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Test',
            'platform_id' => Platform::factory()->create()->id,
            'status' => 'sold',
        ]);

        $this->assertNotNull($game->id);
    }

    public function test_invalid_play_status_is_rejected(): void
    {
        $this->expectException(QueryException::class);

        Game::query()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Test',
            'platform_id' => Platform::factory()->create()->id,
            'status' => 'owned',
            'play_status' => 'not-a-real-status',
        ]);
    }

    public function test_invalid_edition_format_is_rejected(): void
    {
        $this->expectException(QueryException::class);

        Edition::query()->create(['name' => 'Test', 'format' => 'not-a-real-format']);
    }

    public function test_invalid_commission_direction_is_rejected(): void
    {
        $this->expectException(QueryException::class);

        Commission::factory()->create(['direction' => 'not-a-real-direction']);
    }
}
