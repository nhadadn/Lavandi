<?php

use App\Http\Middleware\Autorizar;
use App\Http\Middleware\RepartidorQueOpera;
use App\Http\Middleware\ResponderEnJson;
use App\Http\Middleware\SoloHttps;
use App\Http\Middleware\UsarDireccionConfigurada;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(UsarDireccionConfigurada::class);
        // Con la configuración de producción, solo https (medicion-E13). Al final del grupo global, después de TrustProxies.
        $middleware->append(SoloHttps::class);
        $middleware->api(prepend: [ResponderEnJson::class]);
        // La puerta de las rutas protegidas hacia el componente único de autorización (registro-E14).
        // La puerta de las rutas de pedidos del Módulo C: solo un repartidor activo, con su plaza (repartidor-E03 y E06).
        $middleware->alias(['autorizar' => Autorizar::class, 'repartidor-opera' => RepartidorQueOpera::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
