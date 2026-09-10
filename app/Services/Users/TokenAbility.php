<?php

namespace App\Services\Users;

/**
 * Abilities de Sanctum del token de la API ("MobileApp", ver
 * AuthController::issueTokenResponse()) — antes se creaba con el ability por
 * defecto '*' (comodín, todo permitido), así que un token filtrado tenía
 * acceso total a la cuenta vía API sin ningún límite. Con esto, el propio
 * token declara explícitamente para qué sirve (games:read, games:write,
 * profile:read) y cada ruta exige la que le corresponde (ver routes/api.php)
 * — no hay hoy ningún cliente que necesite menos que todas estas (solo existe
 * el token "MobileApp"), pero deja sentada la base para un futuro token más
 * restringido sin tener que rediseñar nada.
 */
enum TokenAbility: string
{
    case GAMES_READ = 'games:read';
    case GAMES_WRITE = 'games:write';
    case PROFILE_READ = 'profile:read';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return array_column(self::cases(), 'value');
    }
}
