<?php

namespace Database\Seeders;

use App\Models\Game;
use App\Models\Manufacturer;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->seedUsers();
        $this->seedCatalog();
        $this->seedGames();
    }

    /**
     * Usuario de desarrollo por defecto.
     */
    private function seedUsers(): void
    {
        // updateOrCreate para poder relanzar el seeder sin petar por el unique del email.
        User::updateOrCreate(
            ['email' => 'admin@savepoint.test'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
    }

    /**
     * Catálogo base: fabricantes y sus plataformas.
     */
    private function seedCatalog(): void
    {
        $catalog = [
            'Nintendo' => [
                'NES', 'SNES', 'Nintendo 64', 'GameCube', 'Wii', 'Wii U',
                'Nintendo Switch', 'Game Boy', 'Game Boy Advance', 'Nintendo DS', 'Nintendo 3DS',
            ],
            'Sony' => [
                'PlayStation', 'PlayStation 2', 'PlayStation 3', 'PlayStation 4',
                'PlayStation 5', 'PSP', 'PS Vita',
            ],
            'Microsoft' => [
                'Xbox', 'Xbox 360', 'Xbox One', 'Xbox Series X|S',
            ],
            'Sega' => [
                'Master System', 'Mega Drive', 'Saturn', 'Dreamcast', 'Game Gear',
            ],
        ];

        foreach ($catalog as $manufacturerName => $platforms) {
            $manufacturer = Manufacturer::updateOrCreate(
                ['slug' => Str::slug($manufacturerName)],
                ['name' => $manufacturerName]
            );

            foreach ($platforms as $platformName) {
                Platform::updateOrCreate(
                    ['slug' => Str::slug($platformName)],
                    [
                        'name' => $platformName,
                        'manufacturer_id' => $manufacturer->id,
                    ]
                );
            }
        }

        // PC no tiene fabricante: la columna manufacturer_id es nullable.
        Platform::updateOrCreate(
            ['slug' => 'pc'],
            ['name' => 'PC', 'manufacturer_id' => null]
        );
    }

    /**
     * Juegos de prueba en la colección de Admin.
     */
    private function seedGames(): void
    {
        $user = User::where('email', 'admin@savepoint.test')->firstOrFail();
        $switch = Platform::where('slug', 'nintendo-switch')->firstOrFail();

        $games = [
            [
                'title' => 'The Legend of Zelda: Breath of the Wild',
                'status' => 'owned',
                'play_status' => 'finished',
                'condition' => 'mint',
                'release_date' => '2017-03-03',
                'genres' => ['Acción', 'Aventura', 'RPG'],
                'rating' => 5,
            ],
            [
                'title' => 'Super Mario Odyssey',
                'status' => 'owned',
                'play_status' => 'playing',
                'condition' => 'good',
                'release_date' => '2017-10-27',
                'genres' => ['Plataformas', 'Aventura'],
                'rating' => 5,
            ],
        ];

        foreach ($games as $game) {
            Game::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'title' => $game['title'],
                    'platform_id' => $switch->id,
                ],
                $game
            );
        }
    }
}
