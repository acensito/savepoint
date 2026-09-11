<?php

namespace App\Services\Catalog;

use App\Models\Edition;
use App\Models\Manufacturer;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Catálogo por cuenta (issue #175): cada usuario gestiona sus propias
 * plataformas/fabricantes/ediciones, sin dato compartido con el resto. Toda
 * cuenta nueva empieza con una copia del mismo catálogo base de siempre
 * (antes sembrado una sola vez y compartido por todos, ver
 * DatabaseSeeder::seedCatalog()) en vez de arrancar en blanco — así se puede
 * dar de alta el primer juego sin tener que crear antes la plataforma a
 * mano, con sus colores y fabricante.
 *
 * Única fuente de este catálogo base: la usan tanto el seeder de desarrollo
 * (DatabaseSeeder) como el registro público (RegisterController) y el alta
 * de usuario del admin (UserController), para que los tres caminos acaben
 * en el mismo sitio.
 */
class SeedCatalogCopier
{
    /**
     * @var array<string, array<int, string>>
     */
    private const CATALOG = [
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

    public function copyTo(User $user): void
    {
        foreach (self::CATALOG as $manufacturerName => $platforms) {
            $manufacturer = Manufacturer::updateOrCreate(
                ['user_id' => $user->id, 'slug' => Str::slug($manufacturerName)],
                ['name' => $manufacturerName]
            );

            foreach ($platforms as $platformName) {
                Platform::updateOrCreate(
                    ['user_id' => $user->id, 'slug' => Str::slug($platformName)],
                    [
                        'name' => $platformName,
                        'manufacturer_id' => $manufacturer->id,
                    ]
                );
            }
        }

        // PC no tiene fabricante: la columna manufacturer_id es nullable.
        Platform::updateOrCreate(
            ['user_id' => $user->id, 'slug' => 'pc'],
            ['name' => 'PC', 'manufacturer_id' => null]
        );

        // Solo una "Normal" (formato disco, mismo desempate que ya usa
        // GameCsvImporter::resolveEdition() entre formatos ambiguos) — si
        // una cuenta necesita cartucho/diskette aparte más adelante, la crea
        // ella misma, igual que ya hubo que hacer a mano una vez (#142).
        // Sin filas en edition_platform a propósito: una edición sin
        // plataformas asociadas ya se considera "disponible para cualquier
        // plataforma" (mismo criterio que seed_normal_edition.php).
        Edition::updateOrCreate(
            ['user_id' => $user->id, 'name' => 'Normal'],
            ['format' => Edition::FORMAT_PHYSICAL_DISC]
        );
    }
}
