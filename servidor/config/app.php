<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | the application so that it's available within Artisan commands.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Solo https
    |--------------------------------------------------------------------------
    |
    | En prueba y en producción, el servidor atiende solo por https: toda petición por http se redirige a https, en el
    | dominio de APP_URL (NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3; medicion-E13; App\Http\Middleware\SoloHttps).
    | Se enciende en todo entorno que no sea el local ni las pruebas, sin variable que lo apague.
    |
    */

    'solo_https' => ! in_array(env('APP_ENV', 'production'), ['local', 'testing'], true),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. The timezone
    | is set to "UTC" by default as it is suitable for most use cases.
    |
    */

    'timezone' => 'UTC',

    /*
    |--------------------------------------------------------------------------
    | Zona horaria de lo que no tiene plaza
    |--------------------------------------------------------------------------
    |
    | El día, la semana y el mes de lo que no tiene plaza: el "hoy" del panel y su rango por omisión, el mes de registro
    | de una cuenta, el día de actividad_usuarios cuando DD-111 diga qué la escribe y el día de un movimiento de la
    | pasarela sin pedido (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5; openspec/changes/indicadores/design.md,
    | pregunta 8; indicadores-E24). Lo que tiene plaza se lee en la zona horaria de su plaza.
    |
    | Por omisión es America/Mexico_City, la de las plazas sembradas Plaza de prueba 1 y Plaza de prueba Durango
    | (database/datos/plazas-de-prueba/): UTC-6, grado E. Nunca America/Matamoros, que es Matamoros, Tamaulipas. Es una
    | sola clave para los indicadores y las finanzas. No adelanta DD-093: si las plazas quedan en zonas distintas, sigue
    | bastando un valor. La zona de la aplicación (timezone) se queda en UTC: los instantes se guardan absolutos.
    |
    */

    'zona_horaria_sin_plaza' => env('ZONA_HORARIA_SIN_PLAZA', 'America/Mexico_City'),

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by Laravel's translation / localization methods. This option can be
    | set to any locale for which you plan to have translation strings.
    |
    */

    'locale' => env('APP_LOCALE', 'es'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is utilized by Laravel's encryption services and should be set
    | to a random, 32 character string to ensure that all encrypted values
    | are secure. You should do this prior to deploying the application.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

];
