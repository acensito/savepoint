<?php

namespace Tests\Unit\Services\GameLookup;

use App\Services\GameLookup\CexGameLookupService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CexGameLookupServiceTest extends TestCase
{
    private function makeService(): CexGameLookupService
    {
        return new CexGameLookupService(
            host: 'search.webuy.io',
            appId: 'TESTAPPID',
            apiKey: 'test-api-key',
            index: 'prod_cex_es',
        );
    }

    public function test_search_maps_hits_to_lookup_results(): void
    {
        Http::fake([
            'search.webuy.io/*' => Http::response([
                'hits' => [
                    [
                        'boxName' => 'Hollow Knight',
                        'boxId' => '5060146467315',
                        'imageUrls' => ['large' => 'https://es.static.webuy.com/a_l.jpg', 'medium' => 'https://es.static.webuy.com/a_m.jpg'],
                        'categoryFriendlyName' => 'Switch Juegos',
                    ],
                    [
                        'boxName' => 'Hollow Knight',
                        'boxId' => '5060146467247',
                        'imageUrls' => ['large' => null, 'medium' => 'https://es.static.webuy.com/b_m.jpg'],
                        'categoryFriendlyName' => 'PS4 Juegos',
                    ],
                ],
            ], 200),
        ]);

        $results = $this->makeService()->search('hollow knight');

        $this->assertCount(2, $results);
        $this->assertSame('Hollow Knight', $results[0]->title);
        $this->assertSame('5060146467315', $results[0]->ean);
        $this->assertSame('https://es.static.webuy.com/a_l.jpg', $results[0]->coverUrl);
        // El sufijo " Juegos" de la categoría se recorta para quedarse solo con la plataforma.
        $this->assertSame('Switch', $results[0]->platform);
        // Sin "large", cae a "medium".
        $this->assertSame('https://es.static.webuy.com/b_m.jpg', $results[1]->coverUrl);
        $this->assertSame('PS4', $results[1]->platform);
    }

    public function test_search_leaves_platform_null_when_the_category_is_missing(): void
    {
        Http::fake([
            'search.webuy.io/*' => Http::response([
                'hits' => [['boxName' => 'Hollow Knight', 'boxId' => '123']],
            ], 200),
        ]);

        $results = $this->makeService()->search('hollow knight');

        $this->assertNull($results[0]->platform);
    }

    public function test_search_sends_the_credentials_as_headers_and_the_query_in_the_body(): void
    {
        Http::fake(['search.webuy.io/*' => Http::response(['hits' => []], 200)]);

        $this->makeService()->search('zelda');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://search.webuy.io/1/indexes/prod_cex_es/query'
                && $request->hasHeader('X-Algolia-Application-Id', 'TESTAPPID')
                && $request->hasHeader('X-Algolia-API-Key', 'test-api-key')
                && str_contains($request['params'], 'query=zelda');
        });
    }

    /**
     * Auditoría de rendimiento del 2026-09-10: el catálogo de CEX es
     * compartido entre toda la instancia y no cambia de un minuto para
     * otro — repetir la misma consulta (buscador rápido, "buscar carátula",
     * identificador en bloque reintentando el mismo título) no debería
     * volver a golpear la red.
     */
    public function test_search_caches_identical_queries_without_a_second_request(): void
    {
        Http::fake(['search.webuy.io/*' => Http::response(['hits' => [['boxName' => 'Zelda', 'boxId' => '1']]], 200)]);

        $service = $this->makeService();
        $first = $service->search('Zelda');
        $second = $service->search('Zelda');

        $this->assertEquals($first, $second);
        Http::assertSentCount(1);
    }

    public function test_search_cache_is_case_insensitive(): void
    {
        Http::fake(['search.webuy.io/*' => Http::response(['hits' => [['boxName' => 'Zelda', 'boxId' => '1']]], 200)]);

        $service = $this->makeService();
        $service->search('Zelda');
        $service->search('zelda');
        $service->search('  ZELDA  ');

        Http::assertSentCount(1);
    }

    public function test_search_does_not_cache_across_different_queries(): void
    {
        Http::fake(['search.webuy.io/*' => Http::response(['hits' => []], 200)]);

        $service = $this->makeService();
        $service->search('Zelda');
        $service->search('Mario');

        Http::assertSentCount(2);
    }

    public function test_search_does_not_cache_a_connection_failure(): void
    {
        $attempt = 0;
        Http::fake([
            'search.webuy.io/*' => function () use (&$attempt) {
                $attempt++;

                return $attempt === 1
                    ? throw new ConnectionException('timed out')
                    : Http::response(['hits' => [['boxName' => 'Zelda', 'boxId' => '1']]], 200);
            },
        ]);

        $service = $this->makeService();
        $this->assertSame([], $service->search('Zelda'));
        $this->assertNotEmpty($service->search('Zelda'));

        // Http::assertSentCount() no sirve aquí: un fake que lanza no llega
        // a registrarse como "enviado" — el contador propio del closure sí
        // prueba que la segunda llamada intentó la red de verdad en vez de
        // servirse de una caché que nunca debió escribirse tras el fallo.
        $this->assertSame(2, $attempt);
    }

    public function test_search_returns_empty_array_for_a_blank_query_without_making_a_request(): void
    {
        // Http::preventStrayRequests() (Tests\TestCase) haría fallar el test
        // si esto disparase una petición real: confirma el corte temprano.
        $this->assertSame([], $this->makeService()->search('   '));
    }

    public function test_search_returns_empty_array_when_the_response_is_an_error_status(): void
    {
        Http::fake(['search.webuy.io/*' => Http::response('', 403)]);

        $this->assertSame([], $this->makeService()->search('zelda'));
    }

    public function test_search_returns_empty_array_on_connection_failure(): void
    {
        Http::fake(['search.webuy.io/*' => fn () => throw new ConnectionException('timed out')]);

        $this->assertSame([], $this->makeService()->search('zelda'));
    }

    public function test_search_skips_hits_without_a_title(): void
    {
        Http::fake([
            'search.webuy.io/*' => Http::response([
                'hits' => [
                    ['boxName' => '', 'boxId' => '123'],
                    ['boxId' => '456'],
                ],
            ], 200),
        ]);

        $this->assertSame([], $this->makeService()->search('algo'));
    }

    public function test_current_price_prefers_the_exact_title_and_platform_match(): void
    {
        Http::fake([
            'search.webuy.io/*' => Http::response([
                'hits' => [
                    ['boxName' => 'Hollow Knight Bundle', 'sellPrice' => 60, 'categoryFriendlyName' => 'Switch Juegos'],
                    ['boxName' => 'Hollow Knight', 'sellPrice' => 20, 'categoryFriendlyName' => 'PS4 Juegos'],
                    // Título exacto Y plataforma exacta: debe ganar a los dos de arriba.
                    ['boxName' => 'Hollow Knight', 'sellPrice' => 25, 'categoryFriendlyName' => 'Switch Juegos'],
                ],
            ], 200),
        ]);

        $match = $this->makeService()->currentPrice('Hollow Knight', 'Switch');

        $this->assertSame('Hollow Knight', $match->title);
        $this->assertSame(25.0, $match->price);
    }

    public function test_current_price_ignores_hits_without_a_sell_price(): void
    {
        Http::fake([
            'search.webuy.io/*' => Http::response([
                'hits' => [
                    ['boxName' => 'Hollow Knight', 'categoryFriendlyName' => 'Switch Juegos'],
                    ['boxName' => 'Hollow Knight Silksong', 'sellPrice' => 40, 'categoryFriendlyName' => 'Switch Juegos'],
                ],
            ], 200),
        ]);

        $match = $this->makeService()->currentPrice('Hollow Knight', 'Switch');

        $this->assertSame('Hollow Knight Silksong', $match->title);
        $this->assertSame(40.0, $match->price);
    }

    public function test_current_price_returns_null_without_any_match(): void
    {
        Http::fake(['search.webuy.io/*' => Http::response(['hits' => []], 200)]);

        $this->assertNull($this->makeService()->currentPrice('Un juego que no existe'));
    }

    public function test_current_price_returns_null_for_a_blank_title_without_making_a_request(): void
    {
        $this->assertNull($this->makeService()->currentPrice('   '));
    }
}
