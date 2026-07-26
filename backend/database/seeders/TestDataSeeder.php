<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Manufacturer;
use App\Models\Platform;
use App\Models\Game;

class TestDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Crear un usuario de prueba (usamos el Factory que trae Laravel por defecto)
        $user = User::factory()->create([
            'name' => 'Admin Test',
            'email' => 'admin@savepoint.test',
            'password' => bcrypt('password'),
        ]);

        // 2. Crear un fabricante
        $nintendo = Manufacturer::create([
            'name' => 'Nintendo',
            'slug' => 'nintendo',
        ]);

        // 3. Crear una plataforma dependiente del fabricante
        $switch = Platform::create([
            'name' => 'Nintendo Switch',
            'slug' => 'nintendo-switch',
            'manufacturer_id' => $nintendo->id,
        ]);

        // 4. Crear juegos asignados al usuario y a la plataforma
        Game::create([
            'user_id' => $user->id,
            'title' => 'The Legend of Zelda: Breath of the Wild',
            'platform_id' => $switch->id,
            'status' => 'owned',
            'play_status' => 'finished',
            'condition' => 'excellent',
            'release_date' => '2017-03-03',
            'genres' => ['Acción', 'Aventura', 'RPG'],
            'rating' => 10,
        ]);

        Game::create([
            'user_id' => $user->id,
            'title' => 'Super Mario Odyssey',
            'platform_id' => $switch->id,
            'status' => 'owned',
            'play_status' => 'playing',
            'condition' => 'good',
            'release_date' => '2017-10-27',
            'genres' => ['Plataformas', 'Aventura'],
            'rating' => 9,
        ]);
    }
}