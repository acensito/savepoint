<?php

namespace App\Services\GameLookup;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Descarga una carátula sugerida por un catálogo externo (CEX) hacia el
 * disco 'public', usada tanto al confirmar el alta/edición de un juego desde
 * su ficha de comprobación (GameController) como al confirmar candidatos de
 * la cola de revisión del identificado en bloque (GameAutoIdentifyController,
 * issue #128) — misma lógica de seguridad en un único sitio, en vez de
 * duplicarla.
 */
class ExternalCoverDownloader
{
    /**
     * El campo llega como texto (input oculto del formulario, o candidato en
     * caché), así que se valida contra una lista de hosts permitidos
     * (config('services.cex.image_hosts')) antes de pedirlo: sin eso,
     * cualquiera podría manipular ese campo para convertir el alta de un
     * juego en un proxy hacia una URL interna (SSRF). Si algo falla (host no
     * permitido, timeout, no es una imagen...) se devuelve null en vez de
     * lanzar: el juego se guarda igualmente, solo sin carátula.
     */
    public function download(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $allowedHosts = config('services.cex.image_hosts', []);

        if ($scheme !== 'https' || $host === null || ! in_array($host, $allowedHosts, true)) {
            return null;
        }

        try {
            $response = Http::timeout(5)->withOptions(['allow_redirects' => false])->get($url);
        } catch (Throwable $e) {
            return null;
        }

        if (! $response->ok() || strlen($response->body()) > 3 * 1024 * 1024) {
            return null;
        }

        $extension = match (true) {
            str_starts_with((string) $response->header('Content-Type'), 'image/jpeg') => 'jpg',
            str_starts_with((string) $response->header('Content-Type'), 'image/png') => 'png',
            str_starts_with((string) $response->header('Content-Type'), 'image/webp') => 'webp',
            default => null,
        };

        if ($extension === null) {
            return null;
        }

        $path = 'covers/'.Str::random(40).'.'.$extension;
        Storage::disk('public')->put($path, $response->body());

        return $path;
    }
}
