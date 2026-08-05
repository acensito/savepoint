<?php

namespace App\Services\GameLookup;

/**
 * Un resultado de búsqueda en un catálogo externo: solo lo mínimo que hace
 * falta para autorrellenar el alta de un juego (EAN, título, carátula). No
 * es un modelo de datos completo (sin plataforma, desarrollador, etc.) a
 * propósito: cada proveedor externo trae campos distintos y con nombres
 * distintos, así que solo se expone el subconjunto común que todos pueden
 * ofrecer con garantías.
 */
final class GameLookupResult
{
    public function __construct(
        public readonly string $title,
        public readonly ?string $ean = null,
        public readonly ?string $coverUrl = null,
    ) {
    }
}
