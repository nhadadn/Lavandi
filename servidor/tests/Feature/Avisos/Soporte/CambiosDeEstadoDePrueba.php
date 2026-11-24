<?php

namespace Tests\Feature\Avisos\Soporte;

use App\Models\Pedido;
use App\Models\PedidoEvento;

/**
 * El cambio de estado del pedido por el botón de la lavandería, con su publicación, para las pruebas de avisos. Se usa
 * junto con EstadosDePrueba.
 *
 * Desde la tarea 2.1 de estados-del-pedido el botón existe: App\Pedidos\CambioDeEstado guarda el estado actual y su
 * evento en una transacción y publica EstadoDelPedidoCambiado, que se entrega ya guardado el cambio. Este trait ya no
 * publica a mano: llama a EstadosDePrueba::marcarComoLaLavanderia(), que usa la operación real. Por eso valida el orden
 * de ADR-0021: el pedido que se mueve tiene que estar aceptado (EstadosDePrueba::pedidoAceptadoDe()).
 */
trait CambiosDeEstadoDePrueba
{
    protected function cambiarComoLaLavanderia(Pedido $pedido, string $codigo): PedidoEvento
    {
        return $this->marcarComoLaLavanderia($pedido, $codigo);
    }

    /**
     * El pedido pasa por cada estado, en orden, como la lavandería con sus botones.
     */
    protected function llevarComoLaLavanderia(Pedido $pedido, string ...$codigos): void
    {
        foreach ($codigos as $codigo) {
            $this->cambiarComoLaLavanderia($pedido, $codigo);
        }
    }
}
