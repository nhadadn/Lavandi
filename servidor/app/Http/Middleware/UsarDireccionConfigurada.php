<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Los enlaces absolutos que arma el servidor usan la dirección de la configuración (APP_URL), no la dirección por
 * la que llegó la consulta (base-E10). Así un túnel, un proxy o un encabezado Host ajeno no cambian los enlaces, y
 * el esquema sale de APP_URL: con una dirección https, todos los enlaces son https.
 */
class UsarDireccionConfigurada
{
    public function handle(Request $request, Closure $next): Response
    {
        $direccion = (string) config('app.url');

        URL::forceRootUrl($direccion);
        URL::forceScheme(parse_url($direccion, PHP_URL_SCHEME) ?: null);

        return $next($request);
    }
}
