<?php

namespace Database\Factories;

use App\Models\Manufacturer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Platform>
 */
class PlatformFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => $name,
            'slug' => Str::slug($name) . '-' . fake()->unique()->randomNumber(5),
            'label' => null,
            'manufacturer_id' => Manufacturer::factory(),
            'bg_color' => null,
            'text_color' => null,
            'border_color' => null,
        ];
    }
}
