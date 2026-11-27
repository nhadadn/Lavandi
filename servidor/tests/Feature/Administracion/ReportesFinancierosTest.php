<?php

namespace Tests\Feature\Administracion;

use App\Autorizacion\Autorizacion;
use App\Filament\Pages\ReporteDeIngresos;
use App\Filament\Widgets\Finanzas\IngresosDelPeriodo;
use App\Filament\Widgets\Finanzas\IngresosPorPeriodoTabla;
use App\Models\Pago;
use App\Models\Rol;
use App\Models\Usuario;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Administracion\Soporte\PagosDelPeriodoDePrueba;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\AltaLavanderias\Soporte\PanelDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Indicadores\Soporte\IndicadoresDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * Los ingresos del periodo en los reportes financieros del panel (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D
 * #6, "Ingresos por periodo"; openspec/changes/administracion/, tarea 4.1).
 *
 * - Ingresos son lo cobrado a los usuarios con pago confirmado por la pasarela: los pagos que el aviso (webhook) de la
 *   pasarela dejó en pagado (ADR-0010, decisión 1; design.md, lectura 4). No salen de los montos del pedido: qué monto
 *   se cobra lo fija DD-012, y aquí cuenta lo que la pasarela cobró.
 * - La fecha de un ingreso es la de la confirmación del pago, en la hora de la plaza del pedido (modelo-multi-plaza.md).
 * - Una orden que la pasarela creó pero no confirmó, o cuyo intento se rechazó, no es ingreso.
 *
 * Cada pago de prueba pasa por el camino real: la pasarela simulada lo acredita con su aviso, por el webhook
 * (Soporte\PagosDelPeriodoDePrueba). Todo es ficticio: los montos y las fechas son de prueba.
 *
 * Escenario: administracion-E17 · Spec: openspec/changes/administracion/specs/administracion/spec.md · Tarea 4.1
 */
class ReportesFinancierosTest extends TestCase
{
    use AgendaDePrueba;
    use IndicadoresDePrueba;
    use LavanderiasDePrueba;
    use PagosDelPeriodoDePrueba;
    use PanelDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    /** Marzo de 2027, por mes. */
    private const MARZO = ['desde' => '2027-03-01', 'hasta' => '2027-03-31', 'granularidad' => 'mes'];

    /** Los roles que no son de administración. */
    private const OTROS_ROLES = [Rol::USUARIO, Rol::LAVANDERIA, Rol::REPARTIDOR];

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();
    }

    #[TestDox('administracion-E17: con pagos confirmados por la pasarela en marzo y en abril, y una orden de marzo sin confirmar, la administración pide los ingresos de marzo y ve la suma de los pagos confirmados en marzo, sin los de abril ni la orden sin confirmar')]
    public function test_administracion_e17_los_ingresos_de_marzo_son_los_pagos_confirmados_en_marzo(): void
    {
        $this->sembrarMarzoYAbril();
        $this->comoAdministracion();

        $this->widget(IngresosDelPeriodo::class, self::MARZO)
            ->assertOk()
            ->assertSee('$350.00')
            ->assertSee('2 pagos confirmados')
            ->assertDontSee('$1,550.00');

        $this->assertSame(
            ['2027-03-01' => ['periodo' => 'marzo 2027', 'pagos' => 2, 'ingresos' => '350.00']],
            $this->filasDeLaTabla(self::MARZO),
        );

        // Abril tiene lo suyo: el pago de las 00:30 del día 1 y el del día 15.
        $this->widget(IngresosDelPeriodo::class, ['desde' => '2027-04-01', 'hasta' => '2027-04-30', 'granularidad' => 'mes'])
            ->assertSee('$700.00')
            ->assertSee('2 pagos confirmados');
    }

    #[TestDox('administracion-E17: la fecha de un ingreso es la de la confirmación del pago en la hora de su plaza: el de las 23:30 del 31 de marzo cuenta en marzo aunque en UTC ya sea abril, y el de las 00:30 del 1 de abril, en abril')]
    public function test_administracion_e17_la_fecha_del_ingreso_es_la_de_la_plaza(): void
    {
        $this->sembrarMarzoYAbril();
        $this->comoAdministracion();

        $filas = $this->filasDeLaTabla(['desde' => '2027-03-31', 'hasta' => '2027-04-01', 'granularidad' => 'dia']);

        $this->assertSame([1, '200.00'], [$filas['2027-03-31']['pagos'], $filas['2027-03-31']['ingresos']]);
        $this->assertSame([1, '300.00'], [$filas['2027-04-01']['pagos'], $filas['2027-04-01']['ingresos']]);

        // El pago de las 23:30 se confirmó cuando en UTC ya era 1 de abril.
        $ultimoDeMarzo = Pago::query()->where('monto', '200.00')->sole();
        $this->assertSame('2027-04-01T05:30:00Z', $ultimoDeMarzo->pagado_en->toIso8601ZuluString());
    }

    #[TestDox('administracion-E17: los ingresos no salen de los montos del pedido sino de lo que la pasarela confirmó: un pedido con monto propio y sin pago confirmado no suma')]
    public function test_administracion_e17_los_ingresos_no_salen_de_los_montos_del_pedido(): void
    {
        $pagado = $this->pagadoEl('2027-03-05 10:00:00', '150.00');
        DB::table('pedidos')->where('id_pedidos', $pagado->getKey())->update(['monto_estimado' => '999.00', 'monto_final' => '999.00']);
        $sinPago = $this->pedidoSinPagarEl('2027-03-06 10:00:00');
        DB::table('pedidos')->where('id_pedidos', $sinPago->getKey())->update(['monto_estimado' => '888.00', 'monto_final' => '888.00']);
        $this->comoAdministracion();

        $this->widget(IngresosDelPeriodo::class, self::MARZO)
            ->assertSee('$150.00')
            ->assertSee('1 pago confirmado')
            ->assertDontSee('999')
            ->assertDontSee('888');
    }

    #[TestDox('administracion-E17: sin pagos confirmados en el periodo, los ingresos son $0.00 y la tabla trae cada periodo con 0')]
    public function test_administracion_e17_sin_pagos_los_ingresos_son_cero(): void
    {
        $this->comoAdministracion();

        $this->widget(IngresosDelPeriodo::class, self::MARZO)
            ->assertSee('$0.00')
            ->assertSee('0 pagos confirmados');

        $this->assertSame(
            ['2027-03-01' => ['periodo' => 'marzo 2027', 'pagos' => 0, 'ingresos' => '0.00']],
            $this->filasDeLaTabla(self::MARZO),
        );
    }

    #[TestDox('administracion-E17: la administración abre los ingresos en el panel, en el grupo de reportes financieros')]
    public function test_administracion_e17_la_administracion_abre_la_pagina_de_ingresos(): void
    {
        $administracion = $this->comoAdministracion();

        $this->assertTrue(app(Autorizacion::class)->puedeVerLosReportesFinancieros($administracion));
        $this->get('/admin/ingresos')->assertOk()->assertSee('Ingresos');
        $this->get('/admin')->assertOk()->assertSee('Reportes financieros');
        $this->assertSame([IngresosDelPeriodo::class, IngresosPorPeriodoTabla::class], (new ReporteDeIngresos)->getWidgets());
    }

    #[TestDox('administracion-E17: una cuenta de usuario, de socio lavandería o de socio repartidor no abre los ingresos, ni sus widgets, y no ve ninguna cifra')]
    public function test_administracion_e17_otra_cuenta_no_ve_los_ingresos(): void
    {
        $this->pagadoEl('2027-03-05 10:00:00', '150.00');
        Filament::setCurrentPanel('admin');

        foreach ((new ReporteDeIngresos)->getWidgets() as $widget) {
            $this->assertFalse($widget::canView(), "{$widget} sin sesión.");
        }

        foreach (self::OTROS_ROLES as $rol) {
            $cuenta = Usuario::factory()->conRol($rol)->create();
            $this->actingAs($cuenta, 'web');

            $this->assertFalse(app(Autorizacion::class)->puedeVerLosReportesFinancieros($cuenta), $rol);
            $respuesta = $this->get('/admin/ingresos')->assertForbidden();
            $this->assertStringNotContainsString('150.00', $respuesta->getContent());

            foreach ((new ReporteDeIngresos)->getWidgets() as $widget) {
                $this->assertFalse($widget::canView(), "{$widget} con {$rol}.");
                Livewire::test($widget, ['pageFilters' => self::MARZO])->assertForbidden();
            }
        }
    }

    #[TestDox('administracion-E17: los widgets de los ingresos no aparecen en el tablero de inicio del panel')]
    public function test_administracion_e17_los_widgets_no_aparecen_en_el_tablero_de_inicio(): void
    {
        $this->comoAdministracion();

        foreach ((new ReporteDeIngresos)->getWidgets() as $widget) {
            $this->assertFalse($widget::isDiscovered(), $widget);
            $this->get('/admin')->assertDontSeeLivewire($widget);
        }
    }

    /**
     * Marzo: un pago de 150 el día 5 y otro de 200 a las 23:30 del día 31; una orden de 500 el día 10 que la pasarela
     * creó y nunca confirmó, y un intento de 700 el día 12 que la pasarela rechazó. Abril: un pago de 300 a las 00:30
     * del día 1 y otro de 400 el día 15.
     */
    private function sembrarMarzoYAbril(): void
    {
        $this->pagadoEl('2027-03-05 10:00:00', '150.00');
        $this->pagadoEl('2027-03-31 23:30:00', '200.00');
        $this->ordenSinConfirmarEl('2027-03-10 10:00:00', '500.00');
        $this->rechazadoEl('2027-03-12 10:00:00', '700.00');
        $this->pagadoEl('2027-04-01 00:30:00', '300.00');
        $this->pagadoEl('2027-04-15 10:00:00', '400.00');
    }

    /**
     * Las filas de la tabla de ingresos por periodo con esos filtros, por la clave de su periodo, como las muestra el
     * panel, sin la clave que les agrega Filament.
     *
     * @param  array<string, string>  $filtros
     * @return array<string, array<string, mixed>>
     */
    private function filasDeLaTabla(array $filtros): array
    {
        return $this->widget(IngresosPorPeriodoTabla::class, $filtros)
            ->instance()->getTableRecords()
            ->map(fn (array $fila): array => array_diff_key($fila, ['__key' => true]))
            ->all();
    }
}
