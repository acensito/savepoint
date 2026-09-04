<?php

namespace App\Services\GameLookup;

use App\Models\Game;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Busca en IGDB (vía OAuth de Twitch) para enriquecer la ficha de un juego
 * con desarrollador, fecha de lanzamiento, géneros (en inglés, sin mezclar
 * con los que el usuario escribe a mano en español) y nota agregada — datos
 * que CEX no trae en su índice (ver CexGameLookupService).
 *
 * No implementa GameLookupInterface a propósito: esa interfaz está pensada
 * para el flujo de escaneo/búsqueda rápida por EAN (CEX), e IGDB no indexa
 * por código de barras, solo por título.
 *
 * Requiere darse de alta como desarrollador en Twitch (gratis, ver
 * https://dev.twitch.tv/console/apps) para conseguir un Client ID y un
 * Client Secret — por cuenta, no de instancia (users.igdb_client_id/
 * igdb_client_secret, ver Ajustes, AppServiceProvider y el README). Sin esas
 * credenciales, search() no llega a hacer ninguna petición.
 */
class IgdbLookupService
{
    private const TIMEOUT_SECONDS = 4;

    // Los access token de Twitch duran ~60 días (5.184.000s); se cachea algo
    // por debajo de eso para no arriesgarse a un margen demasiado justo.
    private const TOKEN_CACHE_TTL_SECONDS = 5_000_000;

    private const TOKEN_CACHE_KEY_PREFIX = 'igdb_access_token:';

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
    ) {}

    /**
     * Credenciales de una cuenta concreta (users.igdb_enabled/igdb_client_id/
     * igdb_client_secret), sin credenciales si no las tiene activadas. Único
     * sitio que sabe leer esos campos: usado tanto por el binding de
     * AppServiceProvider (cuenta autenticada de la petición actual) como por
     * Jobs\MatchGameWithIgdb (dueño del juego, sin petición HTTP ni sesión de
     * por medio en el worker de cola).
     */
    public static function forUser(?User $user): self
    {
        // PHPStan marca este "?->" como innecesario porque, en valor, acceder
        // a una propiedad de null sin "?->" también resuelve a null y "??" lo
        // captura igual — pero sin "?->", PHP emite un warning ("Attempt to
        // read property on null") cuando no hay usuario. Se mantiene a
        // propósito (ver Platform::effectiveBgColor() para el mismo caso).
        // @phpstan-ignore nullsafe.neverNull
        $enabled = $user?->igdb_enabled ?? false;

        return new self(
            clientId: $enabled ? (string) $user->igdb_client_id : '',
            clientSecret: $enabled ? (string) $user->igdb_client_secret : '',
        );
    }

    public function isConfigured(): bool
    {
        return $this->clientId !== '' && $this->clientSecret !== '';
    }

    /**
     * Busca por título en IGDB. Con $platformName, los resultados de esa
     * plataforma se devuelven primero (sin descartar el resto: varios
     * juegos pueden compartir título — remaster, otra plataforma...), así
     * que sirve tanto para quedarse con el primero como mejor candidato
     * automático (ver GameController::show()) como para listar varias
     * opciones en un buscador manual. Nunca lanza una excepción hacia
     * arriba: es una ayuda opcional, igual que CexGameLookupService::search().
     *
     * @return IgdbGameMatch[]
     */
    public function search(string $query, ?string $platformName = null, int $limit = 5): array
    {
        $query = trim($query);
        if ($query === '' || ! $this->isConfigured()) {
            return [];
        }

        $token = $this->accessToken();
        if ($token === null) {
            return [];
        }

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withHeaders([
                    'Client-ID' => $this->clientId,
                    'Authorization' => "Bearer {$token}",
                ])
                ->withBody(
                    'fields name,first_release_date,involved_companies.company.name,involved_companies.developer,'
                        .'genres.name,rating,aggregated_rating,platforms.name,'
                        .'age_ratings.organization.name,age_ratings.rating_category.rating; '
                        .'search "'.addslashes($query).'"; limit '.max(1, min($limit, 20)).';',
                    'text/plain',
                )
                ->post('https://api.igdb.com/v4/games');
        } catch (Throwable $e) {
            Log::warning('IGDB lookup failed', ['message' => $e->getMessage()]);

            return [];
        }

        if ($response->failed()) {
            Log::warning('IGDB lookup returned an error status', ['status' => $response->status()]);

            return [];
        }

        /** @var array<int, array<string, mixed>> $games */
        $games = $response->json() ?? [];

        $matches = collect($games)
            ->filter(fn (array $game) => filled($game['name'] ?? null))
            ->map(fn (array $game) => $this->toMatch($game))
            ->values();

        // IGDB devuelve por relevancia de texto libre, no por "cuál es el juego
        // que de verdad buscas": una edición/bundle con DLC en el nombre puede
        // salir antes que el juego base. Se reordena (nunca se descarta nada)
        // priorizando primero el título exacto y luego, si se ha dado, la
        // plataforma — el resto conserva el orden de relevancia de IGDB.
        return $matches
            ->sortByDesc(fn (IgdbGameMatch $match) => $this->matchScore($match, $query, $platformName))
            ->values()
            ->all();
    }

    /**
     * Artworks (arte promocional, pensado para usarse de fondo) de un juego
     * ya identificado en IGDB por su id — a diferencia de search(), no hace
     * falta volver a buscar por título: se pide directamente por el id que
     * ya se guardó en games.igdb_id tras el match automático o manual. Solo
     * devuelve los image_id (ver Game::backgroundUrl() para construir la
     * URL): elegir uno es una decisión del usuario, nunca automática.
     *
     * @return string[]
     */
    public function artworks(int $igdbId, int $limit = 8): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        $token = $this->accessToken();
        if ($token === null) {
            return [];
        }

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withHeaders([
                    'Client-ID' => $this->clientId,
                    'Authorization' => "Bearer {$token}",
                ])
                ->withBody(
                    "fields image_id; where game = {$igdbId}; limit ".max(1, min($limit, 20)).';',
                    'text/plain',
                )
                ->post('https://api.igdb.com/v4/artworks');
        } catch (Throwable $e) {
            Log::warning('IGDB artworks lookup failed', ['message' => $e->getMessage()]);

            return [];
        }

        if ($response->failed()) {
            Log::warning('IGDB artworks lookup returned an error status', ['status' => $response->status()]);

            return [];
        }

        /** @var array<int, array<string, mixed>> $artworks */
        $artworks = $response->json() ?? [];

        return collect($artworks)
            ->pluck('image_id')
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Duración media para completar un juego ya identificado en IGDB por su
     * id, sacada originalmente de HowLongToBeat (sin API oficial propia —
     * IGDB la agrega en un endpoint aparte, no viene incluida en search()).
     * Los tres tramos son opcionales de forma independiente (un juego puede
     * tener solo alguno con datos suficientes); count es el número de
     * partidas detrás de la media, para poder desconfiar si es muy bajo.
     *
     * @return array{hastily?: int, normally?: int, completely?: int, count?: int}|null
     */
    public function timeToBeat(int $igdbId): ?array
    {
        if (! $this->isConfigured()) {
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
                    "fields hastily,normally,completely,count; where game_id = {$igdbId}; limit 1;",
                    'text/plain',
                )
                ->post('https://api.igdb.com/v4/game_time_to_beats');
        } catch (Throwable $e) {
            Log::warning('IGDB time to beat lookup failed', ['message' => $e->getMessage()]);

            return null;
        }

        if ($response->failed()) {
            Log::warning('IGDB time to beat lookup returned an error status', ['status' => $response->status()]);

            return null;
        }

        /** @var array<int, array<string, mixed>> $entries */
        $entries = $response->json() ?? [];

        $entry = collect($entries)->first();
        if ($entry === null) {
            return null;
        }

        $times = collect($entry)
            ->only(['hastily', 'normally', 'completely', 'count'])
            ->filter(fn ($value) => $value !== null)
            ->all();

        return $times !== [] ? $times : null;
    }

    /**
     * Puntuación de un candidato para una búsqueda dada — misma lógica que
     * usa search() internamente para reordenar. Pública para que
     * IgdbGameMatcher pueda detectar un empate a la mejor puntuación entre
     * los resultados ya ordenados y, en ese caso, no elegir ninguno por su
     * cuenta (ver issue #50).
     */
    public function matchScore(IgdbGameMatch $match, string $query, ?string $platformName): int
    {
        $score = 0;

        if ($this->normalizeTitleForComparison($match->title) === $this->normalizeTitleForComparison($query)) {
            $score += 2;
        }

        if ($platformName !== null && $platformName !== '' && Str::contains(Str::lower($match->platforms ?? ''), Str::lower($platformName))) {
            $score += 1;
        }

        // Un bundle/pack sin apenas datos propios (sin nota agregada) es un
        // candidato peor que el juego base aunque empate a título exacto —
        // ver issue #50: para muchos títulos, todos los candidatos empatan a
        // título exacto salvo por diferencias triviales de puntuación, así
        // que sin este desempate el orden final vuelve a depender del orden
        // de relevancia (inestable) que devuelve IGDB.
        if ($match->rating === null) {
            $score -= 1;
        }

        return $score;
    }

    /**
     * Normaliza un título para comparar por igualdad exacta tolerando
     * diferencias triviales de puntuación (dos puntos, guiones, apóstrofo
     * tipográfico vs recto) que no cambian el juego que se busca — ver
     * issue #50. A propósito no toca palabras ("Deluxe Edition", "&" vs
     * "y", artículo inicial...): ensanchar la tolerancia ahí sí podría hacer
     * que un bundle con el mismo título base que el juego pasara a
     * considerarse "exacto".
     */
    private function normalizeTitleForComparison(string $title): string
    {
        $normalized = Str::lower(trim($title));
        $normalized = str_replace(['’', '‘', '´', '`', "'"], '', $normalized);
        $normalized = str_replace(['–', '—', '-', ':'], ' ', $normalized);

        return trim(preg_replace('/\s+/', ' ', $normalized) ?? $normalized);
    }

    /**
     * @param  array<string, mixed>  $game
     */
    private function toMatch(array $game): IgdbGameMatch
    {
        /** @var array<int, array<string, mixed>> $involvedCompanies */
        $involvedCompanies = $game['involved_companies'] ?? [];
        $developerEntry = collect($involvedCompanies)
            ->first(fn (array $company) => ($company['developer'] ?? false) === true);

        /** @var array<int, array<string, mixed>> $genresData */
        $genresData = $game['genres'] ?? [];
        /** @var array<int, array<string, mixed>> $platformsData */
        $platformsData = $game['platforms'] ?? [];

        $genres = collect($genresData)->pluck('name')->filter()->values()->all();
        $platforms = collect($platformsData)->pluck('name')->filter()->implode(', ');

        // aggregated_rating (media de críticas) es más fiable que rating (media
        // de usuarios, muy poblada de votos aislados); se cae al segundo solo
        // si IGDB no tiene el primero para este juego.
        $rating = $game['aggregated_rating'] ?? $game['rating'] ?? null;

        return new IgdbGameMatch(
            igdbId: (int) ($game['id'] ?? 0),
            title: (string) $game['name'],
            platforms: $platforms !== '' ? $platforms : null,
            developer: $developerEntry['company']['name'] ?? null,
            releaseDate: isset($game['first_release_date'])
                ? Carbon::createFromTimestamp($game['first_release_date'])->format('Y-m-d')
                : null,
            genres: $genres !== [] ? $genres : null,
            rating: $rating !== null ? round((float) $rating, 2) : null,
            ageRatings: $this->parseAgeRatings($game),
        );
    }

    /**
     * IGDB devuelve clasificaciones de bastantes más organismos de los que
     * esta app distingue (CLASS_IND de Brasil, ACB de Australia, GRAC de
     * Corea...) — se descartan, solo interesan los 4 que reconoce
     * Game::AGE_RATING_SYSTEMS (ver issue #46).
     *
     * @param  array<string, mixed>  $game
     * @return array<int, array{organization: string, value: string}>|null
     */
    private function parseAgeRatings(array $game): ?array
    {
        /** @var array<int, array<string, mixed>> $ageRatingsData */
        $ageRatingsData = $game['age_ratings'] ?? [];

        $ageRatings = collect($ageRatingsData)
            ->map(fn (array $entry) => [
                'organization' => $entry['organization']['name'] ?? null,
                'value' => $entry['rating_category']['rating'] ?? null,
            ])
            ->filter(fn (array $entry) => in_array($entry['organization'], array_keys(Game::AGE_RATING_SYSTEMS), true)
                && $entry['value'] !== null)
            ->values()
            ->all();

        return $ageRatings !== [] ? $ageRatings : null;
    }

    /**
     * Token de aplicación de Twitch (flujo Client Credentials), cacheado
     * entre peticiones: pedir uno nuevo en cada búsqueda gastaría la cuota
     * para nada. La clave incluye el client_id/secret (cada cuenta tiene los
     * suyos, ver AppServiceProvider) para no servirle a una cuenta el token
     * de otra. Si la petición falla, no se cachea nada (Cache::remember no
     * guarda null), así que el siguiente intento vuelve a pedirlo en vez de
     * quedar bloqueado hasta que expire la caché.
     */
    private function accessToken(): ?string
    {
        $cacheKey = self::TOKEN_CACHE_KEY_PREFIX.md5($this->clientId.':'.$this->clientSecret);

        return Cache::remember($cacheKey, self::TOKEN_CACHE_TTL_SECONDS, function () {
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
