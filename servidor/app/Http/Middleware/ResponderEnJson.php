<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * La API responde siempre en JSON, aunque la petición no pida application/json: los errores de validación salen como
 * 422 con sus campos y la falta de sesión como 401, nunca como una redirección a una pantalla web.
 */
class ResponderEnJson
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
