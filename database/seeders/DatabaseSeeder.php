<?php

namespace Database\Seeders;

use App\Models\Game;
use App\Models\Platform;
use App\Models\User;
use App\Services\Catalog\SeedCatalogCopier;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $user = $this->seedUsers();

        // Catálogo por cuenta (issue #175): el admin de desarrollo pasa por
        // el mismo camino que un alta real (ver SeedCatalogCopier), en vez
        // de un catálogo global sembrado una sola vez y compartido por
        // todos.
        if ($user !== null) {
            app(SeedCatalogCopier::class)->copyTo($user);
        }

        $this->seedGames($user);
    }

    /**
     * Usuario de desarrollo por defecto.
     */
    private function seedUsers(): ?User
    {
        $configuredCredentials = config('app.dev_credentials', []);
        $email = is_string($configuredCredentials['email'] ?? null)
            ? trim($configuredCredentials['email'])
            : '';
        $password = is_string($configuredCredentials['password'] ?? null)
            ? $configuredCredentials['password']
            : '';

        if ($email === '' || $password === '') {
            return null;
        }

        // updateOrCreate para poder relanzar el seeder sin crear duplicados.
        return User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Admin',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'is_admin' => true,
            ]
        );
    }

    /**
     * Juegos de prueba en la colección de Admin.
     */
    private function seedGames(?User $user): void
    {
        if ($user === null) {
            return;
        }

        $switch = Platform::where('user_id', $user->id)->where('slug', 'nintendo-switch')->firstOrFail();

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
