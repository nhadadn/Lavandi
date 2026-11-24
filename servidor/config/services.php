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

    /*
    | Inicio de sesión con Google (ADR-0005). Los clientes OAuth son de cada entorno y van en su .env (ADR-0012):
    | el idToken se acepta solo si su destinatario (aud) es uno de ellos. Son identificadores públicos, no secretos.
    */
    'google' => [
        'clientes' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('GOOGLE_CLIENTES_OAUTH', '')),
        ))),
        // Las llaves públicas con que Google firma sus idTokens, en formato JWK.
        'llaves' => env('GOOGLE_DIRECCION_DE_LLAVES', 'https://www.googleapis.com/oauth2/v3/certs'),
    ],

    /*
    | La geocodificación de las direcciones escritas con Google Geocoding (ADR-0009, decisión 4). La llave es de cada
    | entorno y va solo en su .env (ADR-0012); sin ella, una dirección escrita no se puede guardar y la app ofrece
    | marcarla en el mapa.
    */
    'geocodificacion' => [
        'llave' => env('GOOGLE_GEOCODIFICACION_LLAVE'),
        'direccion' => env('GOOGLE_GEOCODIFICACION_DIRECCION', 'https://maps.googleapis.com/maps/api/geocode/json'),
    ],

    /*
    | Google Maps JavaScript en el panel de administración, para el mapa de calor de los indicadores (ADR-0009,
    | decisión 3). La llave es de navegador, de cada entorno, restringida a la dirección del panel, y va solo en su .env
    | (ADR-0012). Sin ella, el panel no monta el mapa y dice por qué; las cifras se ven en la tabla.
    */
    'google_maps_panel' => [
        'llave' => env('GOOGLE_MAPS_PANEL_LLAVE'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
