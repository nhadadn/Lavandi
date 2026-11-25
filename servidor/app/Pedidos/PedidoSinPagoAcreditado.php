<?php

namespace App\Pedidos;

use RuntimeException;

/**
 * Se intentó confirmar un pedido cuyo cobro no está pagado: pendiente, fallido, vencido, o sin cobro todavía (pago-E19;
 * C-188: "no se va a recolectar si no ha sido pagado"). No lo pide la app ni ninguna ruta: si pasa, es un error del
 * servidor y queda en el registro.
 */
final class PedidoSinPagoAcreditado extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('El pedido no tiene su cobro pagado.');
    }
}
