<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

        /*
         * Las fotos de las lavanderías (config/fotos.php; ADR-0006). Las dos son privadas: la app y el panel las ven
         * con una URL firmada que vence.
         *
         * fotos_local es el disco de local y de las pruebas: guarda en storage/app/private/fotos y sirve cada foto en
         * /archivos/fotos/... solo con firma válida. fotos_s3 es el de producción: el bucket privado del entorno, con
         * las credenciales del .env de cada entorno, nunca en el repositorio (ADR-0012).
         */
        'fotos_local' => [
            'driver' => 'local',
            'root' => storage_path('app/private/fotos'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/archivos/fotos',
            'visibility' => 'private',
            'serve' => true,
            'throw' => true,
            'report' => false,
        ],

        'fotos_s3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('FOTOS_BUCKET', env('AWS_BUCKET')),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'visibility' => 'private',
            'throw' => true,
            'report' => false,
        ],

        /*
         * El aviso de privacidad y los términos vigentes (config/textos_legales.php; ADR-0006). Los dos son privados: el
         * servidor lee el archivo y lo muestra en su página, nunca lo sirve directo.
         *
         * textos_legales_local es el de local y de las pruebas: guarda en storage/app/private/textos-legales.
         * textos_legales_s3 es el de producción: la carpeta textos-legales del bucket privado del entorno, con las
         * credenciales del .env de cada entorno, nunca en el repositorio (ADR-0012). Un archivo que falta se lee vacío y
         * la página dice que el documento no está disponible; un error al leerlo se registra.
         */
        'textos_legales_local' => [
            'driver' => 'local',
            'root' => storage_path('app/private/textos-legales'),
            'visibility' => 'private',
            'throw' => false,
            'report' => true,
        ],

        'textos_legales_s3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('TEXTOS_LEGALES_BUCKET', env('AWS_BUCKET')),
            'root' => 'textos-legales',
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'visibility' => 'private',
            'throw' => false,
            'report' => true,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
