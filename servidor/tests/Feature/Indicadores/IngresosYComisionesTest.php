<?php

namespace Tests\Feature\Indicadores;

use App\Cobro\CheckoutDelCobro;
use App\Cobro\Desarrollo\CobroSimulado;
use App\Filament\Pages\Indicadores;
use App\Filament\Widgets\Finanzas\IngresosPorPeriodoTabla;
use App\Filament\Widgets\Indicadores\EntregasPorRepartidorTabla;
use App\Filament\Widgets\Indicadores\IngresosBrutosGrafica;
use App\Filament\Widgets\Indicadores\MapaDeCalor;
use App\Models\Pedido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\AltaLavanderias\Soporte\PanelDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Indicadores\Soporte\IndicadoresDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * Los ingresos brutos por periodo, en barras, en el panel de indicadores (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5,
 * categoría Financiero), con las mismas cifras que los reportes financieros de la administración
 * (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #6; administracion-E17): las dos vistas leen la misma consulta,
 * App\Finanzas\Ingresos.
 *
 * - Ingresos brutos son lo cobrado con pago confirmado por la pasarela, sin descontar reembolsos ni comisiones
 *   (administracion-E18 y E19, bloqueados por DD-012, DD-016 y DD-020).
 * - Por qué se agrupan las barras espera a EL CLIENTE (indicadores-E17, BR-31, DD-115): mientras, cada periodo es una
 *   barra.
 *
 * Todo es ficticio: los montos y las fechas son de prueba.
 *
 * Escenario: indicadores-E16 · Spec: openspec/changes/indicadores/specs/indicadores/spec.md · Tarea 4.1
 */
class IngresosYComisionesTest extends TestCase
{
    use AgendaDePrueba;
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

    #[TestDox('indicadores-E16: con pagos confirmados en enero y en febrero, el indicador mensual da la suma de lo cobrado en cada mes, y cada cifra coincide con el reporte financiero de ese mes')]
    public function test_indicadores_e16_cada_mes_da_lo_cobrado_y_coincide_con_el_reporte_financiero(): void
    {
        $this->sembrarEneroYFebrero();
        $this->comoAdministracion();

        $grafica = $this->datosDeLaGrafica($this->widget(IngresosBrutosGrafica::class, $this->filtros('2027-01-01', '2027-02-28', 'mes')));

        $this->assertSame(['enero 2027', 'febrero 2027'], $grafica['labels']);
        $this->assertSame([350.0, 125.5], $grafica['datasets'][0]['data']);

        // Cada barra es la cifra del reporte financiero de su mes.
        foreach ([['2027-01-01', '2027-01-31', 0], ['2027-02-01', '2027-02-28', 1]] as [$desde, $hasta, $barra]) {
            $reporte = $this->widget(IngresosPorPeriodoTabla::class, $this->filtros($desde, $hasta, 'mes'))
                ->instance()->getTableRecords()->sole();

            $this->assertEquals((float) $reporte['ingresos'], $grafica['datasets'][0]['data'][$barra], "{$desde} a {$hasta}");
        }
    }

    #[TestDox('indicadores-E16: un pago confirmado cuenta en el mes del día de su plaza, y una orden sin confirmar no suma')]
    public function test_indicadores_e16_el_mes_es_el_de_la_plaza_y_la_orden_sin_confirmar_no_suma(): void
    {
        $this->sembrarEneroYFebrero();
        $this->comoAdministracion();

        // El pago de las 23:45 del 31 de enero en la hora de la plaza ya es 1 de febrero en UTC, y cuenta en enero.
        $porDia = $this->datosDeLaGrafica($this->widget(IngresosBrutosGrafica::class, $this->filtros('2027-01-31', '2027-02-01')));
        $this->assertSame([200.0, 0.0], $porDia['datasets'][0]['data']);
    }

    #[TestDox('indicadores-E16: el indicador está en la página de indicadores, en el orden del A.5: después de los de socios y antes del mapa; sus barras son de una sola serie mientras DD-115 no diga cómo se agrupan')]
    public function test_indicadores_e16_el_indicador_esta_en_la_pagina(): void
    {
        $this->comoAdministracion();

        $widgets = (new Indicadores)->getWidgets();
        $posicion = array_search(IngresosBrutosGrafica::class, $widgets, true);
        $this->assertSame(array_search(EntregasPorRepartidorTabla::class, $widgets, true) + 1, $posicion);
        $this->assertSame(array_search(MapaDeCalor::class, $widgets, true) - 1, $posicion);
        $this->get('/admin/indicadores')->assertOk()->assertSeeLivewire(IngresosBrutosGrafica::class);

        $grafica = $this->datosDeLaGrafica($this->widget(IngresosBrutosGrafica::class, $this->filtros('2027-01-01', '2027-02-28', 'mes')));
        $this->assertCount(1, $grafica['datasets']);
        $this->assertSame([0.0, 0.0], $grafica['datasets'][0]['data']);
    }

    /**
     * Enero: un pago de 150 el día 10 y otro de 200 a las 23:45 del día 31, en la hora de la plaza; una orden de 900 el
     * día 20 que la pasarela nunca confirmó. Febrero: un pago de 125.50 el día 14.
     */
    private function sembrarEneroYFebrero(): void
    {
        $this->pagadoEl('2027-01-10 10:00:00', '150.00');
        $this->pagadoEl('2027-01-31 23:45:00', '200.00');
        $this->pagadoEl('2027-02-14 10:00:00', '125.50');

        $sinConfirmar = $this->pedidoSinPagarEl('2027-01-20 10:00:00');
        app(CobroSimulado::class)->prepararCobro($sinConfirmar, '900.00');
        app(CheckoutDelCobro::class)->abrir($sinConfirmar);
    }

    private function pagadoEl(string $fechaYHora, string $monto): Pedido
    {
        return app(CobroSimulado::class)->pagar($this->pedidoSinPagarEl($fechaYHora), $monto);
    }
}
