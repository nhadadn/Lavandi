<?php

namespace Tests\Feature\Administracion\Soporte;

use App\Cobro\CheckoutDelCobro;
use App\Cobro\Desarrollo\CobroSimulado;
use App\Cobro\PasarelaSimulada;
use App\Models\EventoPasarela;
use App\Models\Pago;
use App\Models\Pedido;

/**
 * Lo que comparten las pruebas de los reportes financieros: pedidos pagados, sin confirmar o rechazados a una fecha y
 * hora de la plaza, por el camino real del cobro, y la pasarela simulada con sus libros (openspec/changes/administracion/,
 * sección 4 de tasks.md). Se usa junto con IndicadoresDePrueba y lo que ella pide, después de prepararPedido().
 *
 * Cada pago lo acredita la pasarela simulada con su aviso, por el webhook (CobroSimulado): queda pagado en la plataforma
 * y en los libros de la pasarela, con el monto de su orden. Todo es ficticio: los montos y las fechas son de prueba.
 */
trait PagosDelPeriodoDePrueba
{
    /**
     * Un pedido pagado por ese monto a esa fecha y hora de la plaza: la pasarela simulada lo acredita con su aviso.
     */
    protected function pagadoEl(string $fechaYHora, string $monto): Pedido
    {
        return app(CobroSimulado::class)->pagar($this->pedidoSinPagarEl($fechaYHora), $monto)->fresh();
    }

    /**
     * Un pedido con su cobro y su orden creada en la pasarela, a esa fecha y hora, cuyo pago nunca se confirma.
     */
    protected function ordenSinConfirmarEl(string $fechaYHora, string $monto): Pago
    {
        $pedido = $this->pedidoSinPagarEl($fechaYHora);
        app(CobroSimulado::class)->prepararCobro($pedido, $monto);

        $cobro = app(CheckoutDelCobro::class)->abrir($pedido)->cobro->fresh();
        $this->assertSame(Pago::PENDIENTE, $cobro->estado);
        $this->assertNotNull($cobro->id_pasarela_orden);

        return $cobro;
    }

    /**
     * Un pedido cuyo intento de pago la pasarela rechazó con su aviso, a esa fecha y hora.
     */
    protected function rechazadoEl(string $fechaYHora, string $monto): Pago
    {
        $pedido = $this->pedidoSinPagarEl($fechaYHora);
        $cobro = app(CobroSimulado::class)->prepararCobro($pedido, $monto);
        app(CobroSimulado::class)->avisar($cobro, EventoPasarela::RECHAZADO);

        $this->assertSame(Pago::FALLIDO, $cobro->fresh()->estado);

        return $cobro->fresh();
    }

    /**
     * El cobro del pedido.
     */
    protected function cobroDe(Pedido $pedido): Pago
    {
        return $pedido->cobro()->sole();
    }

    /**
     * La pasarela simulada de las pruebas, la misma instancia que usa el servidor, con sus libros.
     */
    protected function simulada(): PasarelaSimulada
    {
        return app(PasarelaSimulada::class);
    }
}
