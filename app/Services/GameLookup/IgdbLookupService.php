<?php

namespace App\Services\GameLookup;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Busca desarrollador y fecha de lanzamiento en IGDB (vía OAuth de Twitch)
 * para autocompletar esos dos campos, que CEX no trae en su índice (ver
 * CexGameLookupService) y que, a diferencia del género o la plataforma, son
 * datos objetivos que no dependen de traducción.
 *
 * No implementa GameLookupInterface a propósito: esa interfaz está pensada
 * para el flujo de escaneo/búsqueda rápida por EAN (CEX), e IGDB no indexa
 * por código de barras, solo por título — esto es un enriquecimiento puntual
 * de dos campos, no un proveedor de sugerencias intercambiable con CEX.
 *
 * Requiere darse de alta como desarrollador en Twitch (gratis, ver
 * https://dev.twitch.tv/console/apps) para conseguir un Client ID y un
 * Client Secret — ver config('services.igdb') y el README. Sin esas
 * credenciales, findByTitle() no llega a hacer ninguna petición.
 */
class IgdbLookupService
{
    private const TIMEOUT_SECONDS = 4;

    // Los access token de Twitch duran ~60 días (5.184.000s); se cachea algo
    // por debajo de eso para no arriesgarse a un margen demasiado justo.
    private const TOKEN_CACHE_TTL_SECONDS = 5_000_000;
    private const TOKEN_CACHE_KEY = 'igdb_access_token';

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->clientId !== '' && $this->clientSecret !== '';
    }

    /**
     * Busca por título (opcionalmente acotado por plataforma, cuando IGDB
     * devuelve varias ediciones/remasters con el mismo nombre) y devuelve el
     * desarrollador y la fecha de lanzamiento del resultado que mejor
     * coincide. Nunca lanza una excepción hacia arriba: es una ayuda
     * opcional para el formulario, igual que CexGameLookupService::search().
     *
     * @return array{developer: ?string, release_date: ?string}|null null si
     *   IGDB no está configurado, la búsqueda falla o no hay nada que ofrecer.
     */
    public function findByTitle(string $title, ?string $platformName = null): ?array
    {
        $title = trim($title);
        if ($title === '' || !$this->isConfigured()) {
            return null;
        }

        $token = $this->accessToken();
        if ($token === null) {
            return null;
        }

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withHeaders([
                    'Client-ID' => $this->clientId,
                    'Authorization' => "Bearer {$token}",
                ])
                ->withBody(
                    'fields name,first_release_date,involved_companies.company.name,involved_companies.developer,platforms.name; '
                        . 'search "' . addslashes($title) . '"; limit 5;',
                    'text/plain',
                )
                ->post('https://api.igdb.com/v4/games');
        } catch (Throwable $e) {
            Log::warning('IGDB lookup failed', ['message' => $e->getMessage()]);

            return null;
        }

        if ($response->failed()) {
            Log::warning('IGDB lookup returned an error status', ['status' => $response->status()]);

            return null;
        }

        $games = collect($response->json() ?? []);

        // Varios resultados pueden compartir título (remaster, otra
        // plataforma...); si sabemos la plataforma del alta, se prioriza el
        // que coincida en vez de quedarnos con el primero sin más.
        if ($platformName !== null && $platformName !== '') {
            $matches = $games->filter(
                fn (array $game) => collect($game['platforms'] ?? [])
                    ->contains(fn (array $p) => Str::lower($p['name'] ?? '') === Str::lower($platformName))
            );

            if ($matches->isNotEmpty()) {
                $games = $matches;
            }
        }

        $game = $games->first();
        if ($game === null) {
            return null;
        }

        $developerEntry = collect($game['involved_companies'] ?? [])
            ->first(fn (array $company) => ($company['developer'] ?? false) === true);
        $developer = $developerEntry['company']['name'] ?? null;

        $releaseDate = isset($game['first_release_date'])
            ? Carbon::createFromTimestamp($game['first_release_date'])->format('Y-m-d')
            : null;

        if ($developer === null && $releaseDate === null) {
            return null;
        }

        return ['developer' => $developer, 'release_date' => $releaseDate];
    }

    /**
     * Token de aplicación de Twitch (flujo Client Credentials), cacheado
     * entre peticiones: pedir uno nuevo en cada búsqueda gastaría la cuota
     * para nada, ya que es el mismo token para toda la app (no por usuario).
     * Si la petición falla, no se cachea nada (Cache::remember no guarda
     * null), así que el siguiente intento vuelve a pedirlo en vez de quedar
     * bloqueado hasta que expire la caché.
     */
    private function accessToken(): ?string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY, self::TOKEN_CACHE_TTL_SECONDS, function () {
            try {
                $response = Http::timeout(self::TIMEOUT_SECONDS)
                    ->asForm()
                    ->post('https://id.twitch.tv/oauth2/token', [
                        'client_id' => $this->clientId,
                        'client_secret' => $this->clientSecret,
                        'grant_type' => 'client_credentials',
                    ]);
            } catch (Throwable $e) {
                Log::warning('IGDB token request failed', ['message' => $e->getMessage()]);

                return null;
            }

            if ($response->failed()) {
                Log::warning('IGDB token request returned an error status', ['status' => $response->status()]);

                return null;
            }

            return $response->json('access_token');
        });
    }
}
