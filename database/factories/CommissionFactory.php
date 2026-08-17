<?php

namespace Database\Factories;

use App\Models\Commission;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Commission>
 */
class CommissionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->unique()->sentence(3),
            'platform_id' => Platform::factory(),
            'counterparty_name' => fake()->firstName(),
            'direction' => fake()->randomElement(Commission::DIRECTIONS),
            'price' => fake()->randomFloat(2, 5, 90),
            'purchased_at' => fake()->date(),
            'resolved_at' => null,
            'game_id' => null,
            'notes' => fake()->sentence(),
        ];
    }
}
