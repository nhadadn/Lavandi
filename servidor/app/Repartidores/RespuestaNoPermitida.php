<?php

namespace App\Repartidores;

use App\Http\Resources\PedidoDelRepartidorResource;
use App\Models\Pedido;
use App\Models\Repartidor;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * El repartidor quiso responder una oferta que ya respondió, o aceptar un pedido que ya no está en curso o que tomó otro
 * repartidor (App\Repartidores\RespuestaDelRepartidor). Nada cambió.
 *
 * Responde 409 con el pedido como está, para que la app lo ponga al día. Un pedido que no es suyo nunca llega aquí:
 * responde 404 antes (ADR-0019, decisión 3).
 */
final class RespuestaNoPermitida extends RuntimeException
{
    public function __construct(
        public readonly Pedido $pedido,
        public readonly Repartidor $repartidor,
        string $mensaje,
    ) {
        parent::__construct($mensaje);
    }

    public function report(): void
    {
        //
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'pedido' => PedidoDelRepartidorResource::delPedido($this->pedido, $this->repartidor),
        ], 409);
    }
}
