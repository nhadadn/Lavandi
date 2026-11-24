<?php

namespace App\Http\Middleware;

use App\Autorizacion\Autorizacion;
use App\Models\Usuario;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * La puerta de las rutas protegidas hacia el componente de autorización: autorizar:<rol>[,<rol>...]. Deja pasar a la
 * cuenta que tenga cualquiera de los roles. No decide nada; pregunta a App\Autorizacion\Autorizacion (registro-E14). Va
 * siempre después de auth:sanctum.
 *
 *     Route::middleware(['auth:sanctum', 'autorizar:usuario'])->group(...);
 *     Route::middleware(['auth:sanctum', 'autorizar:usuario,lavanderia'])->group(...);
 */
class Autorizar
{
    public function __construct(private readonly Autorizacion $autorizacion) {}

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $usuario = $request->user();

        if (! $usuario instanceof Usuario) {
            throw new AuthenticationException;
        }

        $permitido = array_any($roles, fn (string $rol): bool => $this->autorizacion->permite($usuario, $rol));

        abort_unless($permitido, 403, 'Esta cuenta no tiene acceso.');

        return $next($request);
    }
}
