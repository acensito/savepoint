<?php

namespace App\Services\GameLookup;

/**
 * Búsqueda de juegos en un catálogo externo (hoy CEX, ver
 * CexGameLookupService), usada para autorrellenar el alta cuando un juego
 * escaneado/buscado no está todavía en la colección. Vive detrás de esta
 * interfaz para poder cambiar de proveedor o de forma de consulta (API
 * oficial, otro buscador...) sin tocar SearchController ni la vista: solo
 * habría que cambiar el binding en AppServiceProvider.
 */
interface GameLookupInterface
{
    /**
     * Nunca lanza una excepción hacia arriba ni bloquea la petición: es una
     * ayuda opcional para el formulario, no algo de lo que dependa el alta
     * de un juego. Si el proveedor falla o no hay resultados, devuelve [].
     *
     * @return GameLookupResult[]
     */
    public function search(string $query): array;
}
