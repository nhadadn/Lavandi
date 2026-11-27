<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Con la configuración de producción, el servidor atiende solo por https (NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3,
 * comunicaciones cifradas; NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.2, A42-3; SEG-1).
 *
 * Una petición por http a una página, de la landing o del panel, recibe una redirección permanente a la misma dirección
 * por https, en el dominio de la configuración (APP_URL), no en el de la consulta (medicion-E13 y publicacion-E04). Una
 * petición por http a la API recibe 403 con solo su mensaje (publicacion-E05). En los dos casos la petición no se
 * procesa: no abre sesión, no revisa el formulario ni devuelve ningún dato. Se enciende con config('app.solo_https'), que
 * es verdadero en todo entorno que no sea el local ni las pruebas (config/app.php).
 *
 * Va al final del grupo global: corre después de TrustProxies, para que una petición que llegó por https a un proxy de
 * confianza cuente como segura, y antes de la sesión y de las rutas.
 *
 * Escenarios: medicion-E13 (openspec/changes/medicion-de-la-landing/, tarea 3.2) y publicacion-E04 y publicacion-E05
 * (openspec/changes/publicacion/, tarea 2.1)
 */
class SoloHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('app.solo_https') || $request->isSecure()) {
            return $next($request);
        }

        // La API no redirige: una app que siguiera la redirección volvería a mandar por http lo que ya mandó, con su token
        // o su contraseña. Se rechaza con solo el mensaje, sin nada a dónde ir (publicacion-E05).
        if ($request->is('api', 'api/*')) {
            return response()->json(['message' => 'La API solo atiende por https.'], 403);
        }

        return redirect()->away($this->direccionSegura($request), 301);
    }

    /**
     * La misma dirección, con su consulta, por https y en el dominio de la configuración.
     */
    private function direccionSegura(Request $request): string
    {
        $configurada = (string) config('app.url');
        $servidor = parse_url($configurada, PHP_URL_HOST) ?: $request->getHost();
        $puerto = parse_url($configurada, PHP_URL_PORT);

        return 'https://'.$servidor.($puerto ? ':'.$puerto : '').$request->getRequestUri();
    }
}
