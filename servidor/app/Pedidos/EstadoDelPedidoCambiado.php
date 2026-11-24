<?php

namespace App\Pedidos;

use App\Models\Pedido;
use App\Models\PedidoEvento;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * El pedido cambió de estado: la lavandería o el repartidor movieron su estado actual y su evento quedó en el historial
 * (ADR-0021; openspec/changes/estados-del-pedido/design.md).
 *
 * Lo publica quien guarda el cambio, en la misma transacción que el estado y su evento: los botones de la lavandería
 * (estados-del-pedido, tarea 2.1) y, después, los del repartidor. Mientras los botones no existan, lo publican las
 * pruebas de avisos igual que lo harán ellos (tests/Feature/Avisos/Soporte/CambiosDeEstadoDePrueba.php).
 *
 * Se entrega solo cuando la transacción ya se guardó: lo que cuelga de él, como el aviso al teléfono del usuario
 * (avisos-E10 a E12, E14), nunca ve un cambio que después se deshizo, y su falla no deshace el cambio (avisos-E02).
 * La confirmación por el pago no lo publica: tiene su propio evento, PedidoConfirmado.
 */
final class EstadoDelPedidoCambiado implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  PedidoEvento  $evento  El evento del historial que se acaba de agregar, con el estado nuevo.
     */
    public function __construct(
        public readonly Pedido $pedido,
        public readonly PedidoEvento $evento,
    ) {}
}
