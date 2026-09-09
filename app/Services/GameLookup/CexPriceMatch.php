<?php

namespace App\Services\GameLookup;

/**
 * Precio actual de venta en CEX para un juego de la lista de deseos. En
 * euros tal cual lo devuelve el índice de prod_cex_es (catálogo de la tienda
 * española): a diferencia de PriceCharting (ver issue #85, aparcada), aquí
 * no hay ninguna pregunta de divisa que resolver, es la misma moneda que
 * games.wishlist_estimated_price.
 */
final class CexPriceMatch
{
    public function __construct(
        public readonly string $title,
        public readonly float $price,
    ) {}
}
