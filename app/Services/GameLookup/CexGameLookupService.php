<?php

namespace App\Services\GameLookup;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Busca en el catálogo público de CEX (webuy.com) a través del índice de
 * Algolia que usa su propia web/app para el buscador. No es una API oficial
 * de CEX ni una integración con permiso: es la misma búsqueda que hace su
 * frontend, con una clave "search-only" que Algolia recomienda exponer en
 * cliente (no es un secreto real). Puede dejar de funcionar si CEX cambia
 * de proveedor de búsqueda o de nombre de índice sin aviso; si eso pasa, ver
 * config('services.cex') antes de tocar esta clase.
 *
 * search() rellena el alta de un juego (EAN, carátula...); currentPrice()
 * usa el mismo índice para el precio actual de venta (campo "sellPrice" de
 * cada hit, no expuesto por search()) que avisa cuando un juego de la lista
 * de deseos ha bajado al precio que se apuntó.
 */
class CexGameLookupService implements GameLookupInterface
{
    private const TIMEOUT_SECONDS = 4;

    private const MAX_RESULTS = 8;

    public function __construct(
        private readonly string $host,
        private readonly string $appId,
        private readonly string $apiKey,
        private readonly string $index,
    ) {}

    public function search(string $query): array
    {
        return collect($this->rawHits($query))
            ->map(fn (array $hit) => new GameLookupResult(
                title: trim((string) ($hit['boxName'] ?? '')),
                ean: isset($hit['boxId']) && $hit['boxId'] !== '' ? (string) $hit['boxId'] : null,
                coverUrl: $hit['imageUrls']['large'] ?? $hit['imageUrls']['medium'] ?? $hit['imageUrls']['small'] ?? null,
                platform: $this->platformFromCategory($hit['categoryFriendlyName'] ?? null),
            ))
            ->filter(fn (GameLookupResult $result) => $result->title !== '')
            ->values()
            ->all();
    }

    /**
     * Precio actual de venta en CEX de un juego de la lista de deseos, para
     * avisar cuando ha bajado al precio que el usuario apuntó
     * (wishlist_estimated_price, ver Game::hasReachedWishlistPrice() y
     * Jobs\FetchCexWishlistPrice). La lista de deseos no guarda EAN (alta
     * rápida, solo título), así que a diferencia de la búsqueda por código de
     * barras del alta normal, aquí solo se puede buscar por título — se
     * elige el mejor resultado con el mismo criterio que
     * IgdbLookupService::matchScore(): título exacto y, si se conoce, misma
     * plataforma.
     */
    public function currentPrice(string $title, ?string $platformName = null): ?CexPriceMatch
    {
        $best = collect($this->rawHits($title))
            ->filter(fn (array $hit) => is_numeric($hit['sellPrice'] ?? null) && trim($hit['boxName'] ?? '') !== '')
            ->sortByDesc(fn (array $hit) => $this->priceMatchScore($hit, $title, $platformName))
            ->first();

        if ($best === null) {
            return null;
        }

        return new CexPriceMatch(
            title: trim((string) $best['boxName']),
            price: (float) $best['sellPrice'],
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rawHits(string $query): array
    {
        $query = trim($query);
        if ($query === '' || $this->appId === '' || $this->apiKey === '') {
            return [];
        }

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withHeaders([
                    'X-Algolia-Application-Id' => $this->appId,
                    'X-Algolia-API-Key' => $this->apiKey,
                    // search.webuy.io está detrás de Cloudflare, que bloquea
                    // (403) el User-Agent por defecto de Guzzle/Laravel al
                    // identificarse como librería HTTP genérica. Un UA propio
                    // que identifica la app (no un navegador simulado) basta
                    // para pasar, igual que cualquier curl o cliente normal.
                    'User-Agent' => 'Savepoint/1.0 (+personal game-collection app)',
                ])
                ->post("https://{$this->host}/1/indexes/{$this->index}/query", [
                    'params' => http_build_query([
                        'query' => $query,
                        'hitsPerPage' => self::MAX_RESULTS,
                    ]),
                ]);
        } catch (Throwable $e) {
            // Servicio externo no oficial: si falla (timeout, DNS, lo que
            // sea) no debe tumbar la búsqueda rápida, solo quedarse sin
            // sugerencias de CEX.
            Log::warning('CEX game lookup failed', ['message' => $e->getMessage()]);

            return [];
        }

        if ($response->failed()) {
            Log::warning('CEX game lookup returned an error status', ['status' => $response->status()]);

            return [];
        }

        /** @var array<int, array<string, mixed>> */
        return $response->json('hits', []);
    }

    /**
     * Mismo espíritu que IgdbLookupService::matchScore(): prioriza título
     * exacto y, si se conoce la plataforma del juego de la wishlist, que la
     * categoría de CEX coincida — sin esto, el primer resultado de Algolia
     * podría ser un bundle o una plataforma distinta a la que se quiere.
     *
     * @param  array<string, mixed>  $hit
     */
    private function priceMatchScore(array $hit, string $title, ?string $platformName): int
    {
        $score = 0;

        if (Str::lower(trim((string) ($hit['boxName'] ?? ''))) === Str::lower(trim($title))) {
            $score += 2;
        }

        if ($platformName !== null && $platformName !== ''
            && Str::contains(Str::lower((string) ($hit['categoryFriendlyName'] ?? '')), Str::lower($platformName))) {
            $score += 1;
        }

        return $score;
    }

    /**
     * CEX categoriza cada producto como "<plataforma> Juegos" (p. ej. "PS4
     * Juegos", "Switch Juegos"): la plataforma es lo único útil de ahí para
     * mostrar en las sugerencias, así que se recorta el sufijo redundante.
     */
    private function platformFromCategory(?string $category): ?string
    {
        $category = trim((string) $category);
        if ($category === '') {
            return null;
        }

        return trim(preg_replace('/\s+Juegos$/', '', $category));
    }
}
