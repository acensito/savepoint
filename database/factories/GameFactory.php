<?php

namespace Database\Factories;

use App\Models\Platform;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Game>
 */
class GameFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'ean' => fake()->unique()->ean13(),
            'title' => fake()->unique()->sentence(3),
            'platform_id' => Platform::factory(),
            'cover' => null,
            'release_date' => fake()->date(),
            'developer' => fake()->company(),
            'genres' => [fake()->word(), fake()->word()],
            // 'owned' por defecto: la mayoría de tests no les importa la Propiedad
            // y esperan que el juego aparezca en la colección principal, que
            // excluye los "wishlist" (ver GameController::index). Los tests que sí
            // necesitan wishlist/sold lo indican explícitamente al crear el juego.
            'status' => 'owned',
            'play_status' => fake()->randomElement(['pending', 'playing', 'finished']),
            'condition' => fake()->randomElement(['mint', 'good', 'fair', 'poor']),
            'edition_id' => null,
            'notes' => fake()->sentence(),
            'rating' => fake()->numberBetween(1, 5),
            'price_paid' => fake()->randomFloat(2, 5, 90),
            'purchase_place' => fake()->company(),
            'purchase_date' => fake()->date(),
            'manual_status' => fake()->randomElement(['included', 'missing']),
            'region' => fake()->randomElement(['PAL-ES', 'NTSC-U', 'NTSC-J']),
            'age_rating' => fake()->randomElement(['PEGI 3', 'PEGI 12', 'PEGI 18']),
        ];
    }
}
