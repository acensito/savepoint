<?php

namespace Tests\Unit\Services\GameLookup;

use App\Services\GameLookup\ExternalCoverDownloader;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Test dedicado de la protección SSRF de ExternalCoverDownloader::download()
 * (hasta ahora solo cubierta de forma incidental a través de
 * GameControllerTest) — ver auditoría de seguridad del 2026-09-10.
 */
class ExternalCoverDownloaderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_downloads_and_stores_an_image_from_an_allowlisted_https_host(): void
    {
        Http::fake([
            'es.static.webuy.com/*' => Http::response('fake-jpeg-bytes', 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $path = (new ExternalCoverDownloader)->download('https://es.static.webuy.com/product_images/x_l.jpg');

        $this->assertNotNull($path);
        $this->assertStringEndsWith('.jpg', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_rejects_a_host_that_is_not_allowlisted(): void
    {
        Http::fake();

        $path = (new ExternalCoverDownloader)->download('https://evil.example.com/x.jpg');

        $this->assertNull($path);
        Http::assertNothingSent();
    }

    /**
     * Un esquema no-https se rechaza aunque el HOST sí esté en la lista
     * permitida -- el allowlist de host por sí solo no basta, el
     * downgrade a http también tiene que bloquearse explícitamente.
     */
    public function test_rejects_a_non_https_scheme_even_on_an_allowlisted_host(): void
    {
        Http::fake();

        $path = (new ExternalCoverDownloader)->download('http://es.static.webuy.com/product_images/x_l.jpg');

        $this->assertNull($path);
        Http::assertNothingSent();
    }

    /**
     * SSRF clásico: un atacante que consiga manipular este campo no debe
     * poder usarlo para llegar a un servicio interno (localhost) o al
     * endpoint de metadatos de la nube (169.254.169.254) -- ninguno de los
     * dos está en la lista permitida, así que ni siquiera se llega a pedir.
     */
    public function test_rejects_internal_and_cloud_metadata_hosts(): void
    {
        Http::fake();

        $this->assertNull((new ExternalCoverDownloader)->download('https://127.0.0.1/x.jpg'));
        $this->assertNull((new ExternalCoverDownloader)->download('https://169.254.169.254/latest/meta-data/'));
        Http::assertNothingSent();
    }

    public function test_rejects_a_response_over_the_three_megabyte_limit(): void
    {
        Http::fake([
            'es.static.webuy.com/*' => Http::response(str_repeat('a', 3 * 1024 * 1024 + 1), 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $path = (new ExternalCoverDownloader)->download('https://es.static.webuy.com/x.jpg');

        $this->assertNull($path);
        Storage::disk('public')->assertDirectoryEmpty('covers');
    }

    public function test_rejects_a_non_image_content_type(): void
    {
        Http::fake([
            'es.static.webuy.com/*' => Http::response('<html>not an image</html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $path = (new ExternalCoverDownloader)->download('https://es.static.webuy.com/x.jpg');

        $this->assertNull($path);
    }

    public function test_rejects_a_non_ok_response_status(): void
    {
        Http::fake([
            'es.static.webuy.com/*' => Http::response('', 404),
        ]);

        $path = (new ExternalCoverDownloader)->download('https://es.static.webuy.com/x.jpg');

        $this->assertNull($path);
    }

    public function test_returns_null_when_the_request_throws(): void
    {
        Http::fake(function () {
            throw new ConnectionException('timed out');
        });

        $path = (new ExternalCoverDownloader)->download('https://es.static.webuy.com/x.jpg');

        $this->assertNull($path);
    }
}
