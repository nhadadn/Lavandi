<?php

namespace App\Pedidos;

use App\Models\Pedido;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * El pedido pasó a Confirmado, con su folio, porque la pasarela confirmó su pago (confirmacion-E13; C-188).
 *
 * Se publica solo cuando la transacción de la confirmación ya se guardó: lo que cuelga de él, como el correo de
 * confirmación (confirmacion-E10), nunca ve un pedido que después se deshizo, y su falla no deshace el pedido
 * (confirmacion-E11; design.md, decisión 7). El cambio avisos le agrega después el aviso al teléfono, sin tocar la
 * operación de confirmar.
 */
final class PedidoConfirmado implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Pedido $pedido) {}
}
