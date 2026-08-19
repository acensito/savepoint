<?php

namespace App\Services\GameLookup;

/**
 * Un resultado de búsqueda en IGDB. Bastante más rico que GameLookupResult
 * (pensado solo para CEX): aquí el objetivo es justo el contrario, traer
 * todo lo que IGDB pueda aportar sin depender de traducción (desarrollador,
 * fecha de lanzamiento, géneros en inglés, nota agregada).
 */
final class IgdbGameMatch
{
    public function __construct(
        public readonly int $igdbId,
        public readonly string $title,
        public readonly ?string $platforms,
        public readonly ?string $developer,
        public readonly ?string $releaseDate,
        public readonly ?array $genres,
        public readonly ?float $rating,
    ) {}
}
