<?php

namespace App\Pedidos;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Falta el estado en que nace el pedido antes de su pago (config/pedidos.php, estado_previo_al_pago): no está
 * configurado, no existe en estados_pedido, o es uno de los que ve el usuario. Su código y su nombre los fija DD-123.
 *
 * Sin él no se crea ningún pedido: nacer en un estado que ve el usuario sería confirmarlo sin pago, lo que C-188
 * impide. Es un dato que falta, no un error de quien pide: responde 503 y queda en el registro.
 */
final class EstadoPrevioAlPagoSinDefinir extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Falta el estado previo al pago del pedido (config/pedidos.php; DD-123).');
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => 'Todavía no se pueden crear pedidos.'], 503);
    }
}
