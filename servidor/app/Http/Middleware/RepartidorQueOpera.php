<?php

namespace App\Http\Middleware;

use App\Autorizacion\Autorizacion;
use App\Models\Repartidor;
use App\Models\Usuario;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * La puerta de las rutas de pedidos del Módulo C: solo pasa la cuenta cuyo repartidor opera, activo y con su plaza. Un
 * repartidor pendiente o suspendido recibe 403 con su estado (repartidor-E03 y E06). No decide nada: pregunta a
 * App\Autorizacion\Autorizacion::repartidorQueOpera(). Va después de auth:sanctum y autorizar:repartidor.
 *
 * Deja el repartidor en la petición, para que el controlador no lo vuelva a buscar:
 *
 *     RepartidorQueOpera::de($peticion)
 */
class RepartidorQueOpera
{
    private const ATRIBUTO = 'repartidor_que_opera';

    public function __construct(private readonly Autorizacion $autorizacion) {}

    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if (! $usuario instanceof Usuario) {
            throw new AuthenticationException;
        }

        $request->attributes->set(self::ATRIBUTO, $this->autorizacion->repartidorQueOpera($usuario));

        return $next($request);
    }

    /**
     * El repartidor que opera de la petición, que dejó este middleware.
     */
    public static function de(Request $peticion): Repartidor
    {
        /** @var Repartidor */
        return $peticion->attributes->get(self::ATRIBUTO);
    }
}
