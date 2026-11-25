<?php

namespace App\Pedidos;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * El pedido que se pidió no existe, o la cuenta no lo ve porque es de otra: las dos responden igual, 404 y sin ningún
 * dato (ADR-0019, decisión 3; confirmacion-E12 y perfil-usuario-E13).
 */
final class PedidoNoVisible extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('No existe el pedido.');
    }

    public function report(): void
    {
        //
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => 'No existe el pedido.'], 404);
    }
}
