<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Desde que la búsqueda externa (CEX) y la descarga de su carátula
        // hacen peticiones HTTP reales, cualquier test que las ejercite debe
        // usar Http::fake() explícitamente. Sin esto, un test mal escrito
        // podría golpear la red real en vez de fallar de forma clara.
        Http::preventStrayRequests();
    }
}
