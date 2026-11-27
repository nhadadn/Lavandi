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
 * cuenta que tenga cualquiera de los roles. No decide nada; pregunta a App\Autorizacion\Autorizacion::permiteEnLaApi()
 * con el token de la petición, que para el rol de administración exige su habilidad (registro-E14; ADR-0022, decisión
 * 8). Va siempre después de auth:sanctum.
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

        $token = $usuario->currentAccessToken();

        $permitido = array_any(
            $roles,
            fn (string $rol): bool => $this->autorizacion->permiteEnLaApi($usuario, $rol, $token),
        );

        abort_unless($permitido, 403, 'Esta cuenta no tiene acceso.');

        return $next($request);
    }
}
