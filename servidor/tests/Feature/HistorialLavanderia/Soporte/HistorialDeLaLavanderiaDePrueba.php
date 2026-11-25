<?php

namespace Tests\Feature\HistorialLavanderia\Soporte;

use App\Models\Pedido;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Testing\TestResponse;

/**
 * Lo que comparten las pruebas de historial-de-la-lavanderia: un pedido confirmado que se entrega a la hora que se pasa,
 * en la hora de su plaza, y las dos consultas de la app del Módulo B con la sesión de la cuenta de la lavandería, el
 * historial y las estadísticas (openspec/changes/historial-de-la-lavanderia/design.md, "Contrato de la API").
 *
 * Se usa junto con AgendaDePrueba, EstadosDePrueba, HistorialDePrueba, LavanderiasDePrueba,
 * PedidosDeLaLavanderiaDePrueba, PedidosDePrueba y PlazasDePrueba, después de prepararPedido().
 *
 * La entrega sigue el camino real: la lavandería acepta el pedido y toca sus cuatro botones con App\Pedidos\CambioDeEstado,
 * y el repartidor lo entrega como lo hará su botón (EstadosDePrueba::entregarComoElRepartidor()), todo en el instante que
 * se pasa. Las fechas son de prueba.
 *
 * Cada petición olvida la cuenta de la anterior (forgetGuards), como dos peticiones reales al servidor.
 */
trait HistorialDeLaLavanderiaDePrueba
{
    /**
     * El pedido confirmado llega a Entregado en ese instante, escrito en la hora de su plaza ("2026-12-03 19:30").
     */
    protected function entregarEl(Pedido $pedido, string $instante): Pedido
    {
        $this->travelTo(CarbonImmutable::parse($instante, $pedido->plaza->zona_horaria));
        $this->aceptarComoLaLavanderia($pedido);

        return $this->llevarHastaEntregado($pedido);
    }

    /**
     * El reloj en ese instante, escrito en la hora de la plaza de prueba de prepararPedido().
     */
    protected function elRelojEn(string $instante): void
    {
        $this->travelTo(CarbonImmutable::parse($instante, $this->plaza->zona_horaria));
    }

    protected function historialDeLaLavanderia(Usuario $cuenta): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($cuenta->createToken('app')->plainTextToken)->getJson('/api/lavanderia/historial');
    }

    protected function estadisticasDeLaLavanderia(Usuario $cuenta): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($cuenta->createToken('app')->plainTextToken)->getJson('/api/lavanderia/estadisticas');
    }
}
