<?php

namespace App\Avisos;

use App\Pedidos\PedidoConfirmado;
use Illuminate\Support\Facades\Mail;

/**
 * Cuelga del evento de pedido confirmado y pone en cola el correo de confirmación a la dirección de la cuenta
 * (confirmacion-E10; design.md, decisión 7). El evento se publica ya guardada la confirmación, así que el correo nunca
 * sale de un pedido que se deshizo, y su falla no lo deshace (confirmacion-E11).
 *
 * El aviso al teléfono de F-A11 lo agrega el cambio avisos como otro oyente del mismo evento (ADR-0008).
 */
final class MandarCorreoDeConfirmacion
{
    public function handle(PedidoConfirmado $evento): void
    {
        $pedido = $evento->pedido->loadMissing('usuario');

        Mail::to($pedido->usuario->correo)->queue(new CorreoDeConfirmacion($pedido));
    }
}
