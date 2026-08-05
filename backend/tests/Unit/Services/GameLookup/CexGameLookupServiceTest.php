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
                    ],
                    [
                        'boxName' => 'Hollow Knight',
                        'boxId' => '5060146467247',
                        'imageUrls' => ['large' => null, 'medium' => 'https://es.static.webuy.com/b_m.jpg'],
                    ],
                ],
            ], 200),
        ]);

        $results = $this->makeService()->search('hollow knight');

        $this->assertCount(2, $results);
        $this->assertSame('Hollow Knight', $results[0]->title);
        $this->assertSame('5060146467315', $results[0]->ean);
        $this->assertSame('https://es.static.webuy.com/a_l.jpg', $results[0]->coverUrl);
        // Sin "large", cae a "medium".
        $this->assertSame('https://es.static.webuy.com/b_m.jpg', $results[1]->coverUrl);
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
}
