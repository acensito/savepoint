<?php

namespace Database\Factories;

use App\Models\Manufacturer;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Platform>
 */
class PlatformFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'user_id' => User::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->randomNumber(5),
            'label' => null,
            // Comparte el mismo user_id que la propia plataforma salvo que
            // el test diga lo contrario (issue #175): sin esto, una
            // plataforma y su fabricante de fábrica acabarían con dueños
            // distintos por pura casualidad, incoherente ahora que ambos son
            // catálogo por cuenta.
            'manufacturer_id' => fn (array $attributes) => Manufacturer::factory()->create([
                'user_id' => $attributes['user_id'],
            ])->id,
            'bg_color' => null,
            'text_color' => null,
            'border_color' => null,
        ];
    }
}
