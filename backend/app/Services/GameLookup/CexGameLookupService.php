<?php

namespace App\Services\GameLookup;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Busca en el catálogo público de CEX (webuy.com) a través del índice de
 * Algolia que usa su propia web/app para el buscador. No es una API oficial
 * de CEX ni una integración con permiso: es la misma búsqueda que hace su
 * frontend, con una clave "search-only" que Algolia recomienda exponer en
 * cliente (no es un secreto real). Puede dejar de funcionar si CEX cambia
 * de proveedor de búsqueda o de nombre de índice sin aviso; si eso pasa, ver
 * config('services.cex') antes de tocar esta clase.
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
    ) {
    }

    public function search(string $query): array
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

        return collect($response->json('hits', []))
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
