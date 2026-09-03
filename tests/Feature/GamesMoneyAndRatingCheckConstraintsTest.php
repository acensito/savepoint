<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GamesMoneyAndRatingCheckConstraintsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Los CHECK constraints solo existen en Postgres (ver #99).');
        }
    }

    private function baseAttributes(): array
    {
        $user = User::factory()->create();
        $platform = Platform::factory()->create();

        return [
            'user_id' => $user->id,
            'title' => 'Test',
            'platform_id' => $platform->id,
            'status' => 'owned',
        ];
    }

    public function test_rating_outside_one_to_five_is_rejected(): void
    {
        $this->expectException(QueryException::class);

        Game::query()->create([...$this->baseAttributes(), 'rating' => 99]);
    }

    public function test_negative_price_paid_is_rejected(): void
    {
        $this->expectException(QueryException::class);

        Game::query()->create([...$this->baseAttributes(), 'price_paid' => -10]);
    }

    public function test_negative_sale_price_is_rejected(): void
    {
        $this->expectException(QueryException::class);

        Game::query()->create([...$this->baseAttributes(), 'sale_price' => -10]);
    }

    public function test_negative_wishlist_estimated_price_is_rejected(): void
    {
        $this->expectException(QueryException::class);

        Game::query()->create([...$this->baseAttributes(), 'wishlist_estimated_price' => -10]);
    }

    public function test_valid_values_are_accepted(): void
    {
        $game = Game::query()->create([
            ...$this->baseAttributes(),
            'rating' => 5,
            'price_paid' => 0,
            'sale_price' => 0,
            'wishlist_estimated_price' => 0,
        ]);

        $this->assertNotNull($game->id);
    }
}
