<?php

namespace Tests\Feature\Indicadores;

use App\Filament\Pages\Indicadores;
use App\Filament\Widgets\Indicadores\PedidosPorEstadoGrafica;
use App\Models\EstadoPedido;
use App\Models\Pedido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\AltaLavanderias\Soporte\PanelDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Estados\Soporte\EstadosDePrueba;
use Tests\Feature\Indicadores\Soporte\IndicadoresDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * Los pedidos del rango por estado, en una dona (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5, categoría
 * Operaciones): activo es el pedido confirmado que todavía no se entrega ni se cancela; completado, el entregado
 * (openspec/changes/indicadores/specs/indicadores/spec.md, "Pedidos por estado").
 *
 * Los estados son los operativos de ADR-0021 y su adenda: activos son confirmado, aceptado, recolectado, en_proceso,
 * listo y en_entrega. Los cancelados y los rechazados esperan el estado de cancelación (indicadores-E05; DD-016;
 * BR-22): hoy no son activos ni completados, y la dona no los muestra.
 *
 * La lavandería mueve los pedidos con la operación real (EstadosDePrueba) y el repartidor entrega como lo hará su
 * botón (H8).
 *
 * Escenarios: indicadores-E04 · Spec: openspec/changes/indicadores/specs/indicadores/spec.md · Tarea 1.4
 */
class PedidosPorEstadoTest extends TestCase
{
    use AgendaDePrueba;
    use EstadosDePrueba;
    use IndicadoresDePrueba;
    use LavanderiasDePrueba;
    use PanelDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();
    }

    #[TestDox('indicadores-E04: con dos pedidos En proceso, uno Confirmado y tres Entregados en el rango, la dona da 3 activos y 3 completados')]
    public function test_indicadores_e04_la_dona_da_3_activos_y_3_completados(): void
    {
        $this->sembrarElEscenario();
        $this->comoAdministracion();

        $datos = $this->datosDeLaGrafica(
            $this->widget(PedidosPorEstadoGrafica::class, $this->filtros('2026-12-01', '2026-12-31')),
        );

        $this->assertSame(['Activos', 'Completados'], $datos['labels']);
        $this->assertSame([3, 3], $datos['datasets'][0]['data']);
    }

    #[TestDox('indicadores-E04: activos son todos los estados entre Confirmado y Entregado, también aceptado, listo y en entrega')]
    public function test_indicadores_e04_activos_son_todos_los_estados_entre_confirmado_y_entregado(): void
    {
        $this->pedidoConfirmadoEl(self::JUEVES_DE_PRUEBA.' 08:00:00');
        $this->aceptarComoLaLavanderia($this->pedidoConfirmadoEl(self::JUEVES_DE_PRUEBA.' 08:10:00'));
        $listo = $this->pedidoAceptadoEn(self::JUEVES_DE_PRUEBA.' 08:20:00');
        foreach ([EstadoPedido::RECOLECTADO, EstadoPedido::EN_PROCESO, EstadoPedido::LISTO] as $codigo) {
            $this->marcarComoLaLavanderia($listo, $codigo);
        }
        $enEntrega = $this->pedidoAceptadoEn(self::JUEVES_DE_PRUEBA.' 08:30:00');
        foreach ([EstadoPedido::RECOLECTADO, EstadoPedido::EN_PROCESO, EstadoPedido::LISTO, EstadoPedido::EN_ENTREGA] as $codigo) {
            $this->marcarComoLaLavanderia($enEntrega, $codigo);
        }

        $this->comoAdministracion();

        $datos = $this->datosDeLaGrafica(
            $this->widget(PedidosPorEstadoGrafica::class, $this->filtros('2026-12-01', '2026-12-31')),
        );

        $this->assertSame([4, 0], $datos['datasets'][0]['data']);
    }

    #[TestDox('indicadores-E04: no cuentan el pedido rechazado, el que nunca se pagó ni el que se confirmó fuera del rango')]
    public function test_indicadores_e04_no_cuentan_el_rechazado_el_sin_pagar_ni_el_de_fuera_del_rango(): void
    {
        $this->rechazarComoLaLavanderia($this->pedidoConfirmadoEl(self::JUEVES_DE_PRUEBA.' 08:00:00'));
        $this->pedidoSinPagarEl(self::JUEVES_DE_PRUEBA.' 08:30:00');
        $this->pedidoConfirmadoEl('2026-11-30 10:00:00');
        $this->pedidoConfirmadoEl(self::JUEVES_DE_PRUEBA.' 09:00:00');

        $this->comoAdministracion();

        $datos = $this->datosDeLaGrafica(
            $this->widget(PedidosPorEstadoGrafica::class, $this->filtros('2026-12-01', '2026-12-31')),
        );

        $this->assertSame([1, 0], $datos['datasets'][0]['data']);
    }

    #[TestDox('indicadores-E04: la dona dice sus cifras también en texto, y está en la página de indicadores')]
    public function test_indicadores_e04_la_dona_dice_sus_cifras_en_texto_y_esta_en_la_pagina(): void
    {
        $this->sembrarElEscenario();
        $this->comoAdministracion();

        $dona = $this->widget(PedidosPorEstadoGrafica::class, $this->filtros('2026-12-01', '2026-12-31'))
            ->assertSee('Pedidos por estado')
            ->assertSee('Activos: 3')
            ->assertSee('Completados: 3');

        // Las cifras se leen en la descripción, sin pasar el cursor, y el rango sin granularidad: la dona no la usa.
        $this->assertSame(
            'Del 01/12/2026 al 31/12/2026. Activos: 3. Completados: 3.',
            $dona->instance()->getDescription(),
        );

        $this->assertContains(PedidosPorEstadoGrafica::class, (new Indicadores)->getWidgets());
    }

    /**
     * El escenario: dos pedidos En proceso, uno Confirmado y tres Entregados, todos confirmados el jueves de prueba.
     */
    private function sembrarElEscenario(): void
    {
        $this->pedidoConfirmadoEl(self::JUEVES_DE_PRUEBA.' 08:00:00');

        foreach (['08:10:00', '08:20:00'] as $hora) {
            $pedido = $this->pedidoAceptadoEn(self::JUEVES_DE_PRUEBA.' '.$hora);
            $this->marcarComoLaLavanderia($pedido, EstadoPedido::RECOLECTADO);
            $this->marcarComoLaLavanderia($pedido, EstadoPedido::EN_PROCESO);
        }

        foreach (['08:30:00', '08:40:00', '08:50:00'] as $hora) {
            $this->llevarHastaEntregado($this->pedidoAceptadoEn(self::JUEVES_DE_PRUEBA.' '.$hora));
        }
    }

    /**
     * Un pedido confirmado a esa hora y aceptado enseguida por su lavandería: listo para sus botones.
     */
    private function pedidoAceptadoEn(string $fechaYHora): Pedido
    {
        $pedido = $this->pedidoConfirmadoEl($fechaYHora);
        $this->aceptarComoLaLavanderia($pedido);

        return $pedido->fresh();
    }
}
