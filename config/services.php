<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | Búsqueda externa de CEX (webuy.com) para autorrellenar el alta de un
    | juego (EAN, título, carátula) cuando no está en la colección. No es una
    | API oficial de CEX: reutiliza el mismo índice de Algolia que su propia
    | web/app, con una clave "search-only" pensada por Algolia para exponerse
    | en cliente (no es un secreto real). Vive en config, no hardcodeada en
    | App\Services\GameLookup\CexGameLookupService, porque el endpoint/índice
    | podría cambiar sin aviso al no ser una integración oficial.
    */
    'cex' => [
        'host' => env('CEX_ALGOLIA_HOST', 'search.webuy.io'),
        'app_id' => env('CEX_ALGOLIA_APP_ID', 'LNNFEEWZVA'),
        'api_key' => env('CEX_ALGOLIA_API_KEY', 'bf79f2b6699e60a18ae330a1248b452c'),
        'index' => env('CEX_ALGOLIA_INDEX', 'prod_cex_es'),
        // Hosts permitidos para descargar la carátula sugerida al dar de alta
        // (GameController::downloadExternalCover): evita que ese campo del
        // formulario se pueda usar como proxy hacia una URL arbitraria (SSRF).
        'image_hosts' => array_values(array_filter(explode(',', env('CEX_IMAGE_HOSTS', 'es.static.webuy.com')))),
    ],

    /*
    | Búsqueda en IGDB (App\Services\GameLookup\IgdbLookupService) para
    | autocompletar desarrollador y fecha de lanzamiento, que CEX no trae en
    | su índice. A diferencia de CEX, IGDB sí exige darse de alta: hace falta
    | un Client ID y un Client Secret gratuitos de una app de Twitch
    | (https://dev.twitch.tv/console/apps -> "Register Your Application",
    | cualquier OAuth Redirect URL vale, p. ej. https://localhost). Sin estas
    | dos variables en .env, IgdbLookupService::findByTitle() no hace ninguna
    | petición y devuelve null.
    */
    'igdb' => [
        'client_id' => env('IGDB_CLIENT_ID', ''),
        'client_secret' => env('IGDB_CLIENT_SECRET', ''),
    ],

];
