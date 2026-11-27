<?php

namespace Tests\Feature\Indicadores;

use App\Filament\Pages\Indicadores;
use App\Filament\Widgets\Indicadores\PedidosTotalesGrafica;
use App\Filament\Widgets\Indicadores\PedidosTotalesTabla;
use App\Indicadores\Rango;
use App\Models\EstadoPedido;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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
 * Los pedidos totales por día, semana y mes, en barras y en una tabla que pone cada periodo junto al anterior
 * (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5, categoría Operaciones; openspec/changes/indicadores/design.md).
 *
 * Cuenta como pedido todo pedido que llegó a Confirmado, aunque después se rechace, y su fecha es la de su
 * confirmación en la hora de su plaza (design.md, lectura 1; NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo A
 * #12; C-188). La semana va de lunes a domingo (design.md, pregunta 3).
 *
 * La plaza de prueba está en America/Mexico_City (UTC-6, PedidosDePrueba). El hoy del panel, que no tiene plaza, se
 * lee en la zona horaria de referencia de la configuración (app.zona_horaria_sin_plaza; design.md, pregunta 8).
 *
 * Escenarios: indicadores-E01 e indicadores-E24 · Spec: openspec/changes/indicadores/specs/indicadores/spec.md ·
 * Tareas 1.1 y 6.4
 */
class PedidosTotalesTest extends TestCase
{
    use AgendaDePrueba;
    use EstadosDePrueba;
    use IndicadoresDePrueba;
    use LavanderiasDePrueba;
    use PanelDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    /** El lunes de la semana de prueba; el miércoles es el 2 de diciembre y el domingo, el 6. */
    private const LUNES_DE_PRUEBA = '2026-11-30';

    private const DOMINGO_DE_PRUEBA = '2026-12-06';

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();
    }

    #[TestDox('indicadores-E01: con tres pedidos el lunes y dos el miércoles, las barras diarias de la semana dan 3, 0 y 2, y los demás días 0')]
    public function test_indicadores_e01_las_barras_diarias_dan_3_el_lunes_0_el_martes_y_2_el_miercoles(): void
    {
        $this->sembrarLaSemanaDePrueba();
        $this->comoAdministracion();

        $datos = $this->datosDeLaGrafica(
            $this->widget(PedidosTotalesGrafica::class, $this->filtros(self::LUNES_DE_PRUEBA, self::DOMINGO_DE_PRUEBA)),
        );

        $this->assertSame([3, 0, 2, 0, 0, 0, 0], $datos['datasets'][0]['data']);
        $this->assertCount(7, $datos['labels']);
        $this->assertStringContainsString('30/11/2026', $datos['labels'][0]);
        $this->assertStringContainsString('01/12/2026', $datos['labels'][1]);
        $this->assertStringContainsString('02/12/2026', $datos['labels'][2]);
    }

    #[TestDox('indicadores-E01: la tabla de la semana da 3 el lunes, 0 el martes y 2 el miércoles, cada día junto al anterior')]
    public function test_indicadores_e01_la_tabla_diaria_da_3_el_lunes_0_el_martes_y_2_el_miercoles(): void
    {
        $this->sembrarLaSemanaDePrueba();
        $this->comoAdministracion();

        $tabla = $this->widget(PedidosTotalesTabla::class, $this->filtros(self::LUNES_DE_PRUEBA, self::DOMINGO_DE_PRUEBA));

        $this->assertSame(
            ['2026-11-30', '2026-12-01', '2026-12-02', '2026-12-03', '2026-12-04', '2026-12-05', '2026-12-06'],
            $this->clavesDeLaTabla($tabla),
        );

        $tabla->assertOk()
            ->assertTableColumnStateSet('pedidos', 3, '2026-11-30')
            ->assertTableColumnStateSet('pedidos', 0, '2026-12-01')
            ->assertTableColumnStateSet('pedidos', 2, '2026-12-02')
            ->assertTableColumnStateSet('pedidos', 0, '2026-12-03')
            // Cada día junto al anterior: el martes junto al lunes, el miércoles junto al martes.
            ->assertTableColumnStateSet('pedidos_anterior', 0, '2026-11-30')
            ->assertTableColumnStateSet('pedidos_anterior', 3, '2026-12-01')
            ->assertTableColumnStateSet('pedidos_anterior', 0, '2026-12-02')
            ->assertTableColumnStateSet('pedidos_anterior', 2, '2026-12-03');
    }

    #[TestDox('indicadores-E01: cuenta el pedido que llegó a Confirmado aunque después se rechace, y no el que nunca se pagó')]
    public function test_indicadores_e01_cuenta_el_pedido_confirmado_aunque_se_rechace_y_no_el_que_nunca_se_pago(): void
    {
        $rechazado = $this->pedidoConfirmadoEl(self::LUNES_DE_PRUEBA.' 10:00:00');
        $this->rechazarComoLaLavanderia($rechazado);
        $this->assertSame(EstadoPedido::RECHAZADO, $rechazado->fresh()->estado->codigo);

        $sinPagar = $this->pedidoSinPagarEl(self::LUNES_DE_PRUEBA.' 11:00:00');
        $this->assertNull($sinPagar->confirmado_en);

        $this->comoAdministracion();

        $datos = $this->datosDeLaGrafica(
            $this->widget(PedidosTotalesGrafica::class, $this->filtros(self::LUNES_DE_PRUEBA, self::DOMINGO_DE_PRUEBA)),
        );

        $this->assertSame([1, 0, 0, 0, 0, 0, 0], $datos['datasets'][0]['data']);
    }

    #[TestDox('indicadores-E01: la página de indicadores abre con el rango y la granularidad, y trae la gráfica y la tabla de pedidos totales')]
    public function test_indicadores_e01_la_pagina_abre_con_el_rango_la_granularidad_la_grafica_y_la_tabla(): void
    {
        $this->comoAdministracion();

        $this->get('/admin/indicadores')
            ->assertOk()
            ->assertSee('Indicadores')
            ->assertSee('Desde')
            ->assertSee('Hasta')
            ->assertSee('Ver por')
            ->assertSee('Pedidos totales')
            ->assertSeeLivewire(PedidosTotalesGrafica::class)
            ->assertSeeLivewire(PedidosTotalesTabla::class);
    }

    #[TestDox('indicadores-E02: por semana, cada semana suma sus pedidos, de lunes a domingo, y la tabla la pone junto a la anterior')]
    public function test_indicadores_e02_por_semana_cada_semana_suma_sus_pedidos_junto_a_la_anterior(): void
    {
        $this->sembrarEneroYFebrero();
        $this->comoAdministracion();
        // Del miércoles 7 al miércoles 14 de enero: el rango se ajusta a las semanas completas, del lunes 5 al domingo 18.
        $filtros = $this->filtros('2026-01-07', '2026-01-14', 'semana');

        $datos = $this->datosDeLaGrafica($this->widget(PedidosTotalesGrafica::class, $filtros));
        $this->assertSame([2, 1], $datos['datasets'][0]['data']);
        $this->assertSame(['Semana del 05/01/2026', 'Semana del 12/01/2026'], $datos['labels']);

        $tabla = $this->widget(PedidosTotalesTabla::class, $filtros);
        $this->assertSame(['2026-01-05', '2026-01-12'], $this->clavesDeLaTabla($tabla));
        $tabla->assertTableColumnStateSet('pedidos', 2, '2026-01-05')
            ->assertTableColumnStateSet('pedidos', 1, '2026-01-12')
            // La semana anterior a la primera, la del 29 de diciembre, no tuvo pedidos.
            ->assertTableColumnStateSet('periodo_anterior', 'Semana del 29/12/2025', '2026-01-05')
            ->assertTableColumnStateSet('pedidos_anterior', 0, '2026-01-05')
            ->assertTableColumnStateSet('periodo_anterior', 'Semana del 05/01/2026', '2026-01-12')
            ->assertTableColumnStateSet('pedidos_anterior', 2, '2026-01-12');
    }

    #[TestDox('indicadores-E02: por mes, cada mes suma sus pedidos, y la tabla pone febrero junto a enero')]
    public function test_indicadores_e02_por_mes_cada_mes_suma_sus_pedidos_junto_al_anterior(): void
    {
        $this->sembrarEneroYFebrero();
        $this->comoAdministracion();
        // Del 15 de enero al 10 de febrero: el rango se ajusta a los meses completos.
        $filtros = $this->filtros('2026-01-15', '2026-02-10', 'mes');

        $datos = $this->datosDeLaGrafica($this->widget(PedidosTotalesGrafica::class, $filtros));
        $this->assertSame([3, 3], $datos['datasets'][0]['data']);
        $this->assertSame(['enero 2026', 'febrero 2026'], $datos['labels']);

        $tabla = $this->widget(PedidosTotalesTabla::class, $filtros);
        $this->assertSame(['2026-01-01', '2026-02-01'], $this->clavesDeLaTabla($tabla));
        $tabla->assertTableColumnStateSet('pedidos', 3, '2026-01-01')
            ->assertTableColumnStateSet('pedidos', 3, '2026-02-01')
            ->assertTableColumnStateSet('periodo_anterior', 'diciembre 2025', '2026-01-01')
            ->assertTableColumnStateSet('pedidos_anterior', 0, '2026-01-01')
            ->assertTableColumnStateSet('periodo_anterior', 'enero 2026', '2026-02-01')
            ->assertTableColumnStateSet('pedidos_anterior', 3, '2026-02-01');
    }

    #[TestDox('indicadores-E02: al cambiar la vista a semanal y a mensual, la misma base da los mismos pedidos en total')]
    public function test_indicadores_e02_las_tres_vistas_suman_los_mismos_pedidos(): void
    {
        $this->sembrarEneroYFebrero();
        $this->comoAdministracion();

        foreach (['dia', 'semana', 'mes'] as $granularidad) {
            $datos = $this->datosDeLaGrafica(
                $this->widget(PedidosTotalesGrafica::class, $this->filtros('2026-01-01', '2026-02-28', $granularidad)),
            );

            $this->assertSame(6, array_sum($datos['datasets'][0]['data']), $granularidad);
        }
    }

    #[TestDox('indicadores-E03: un pedido confirmado a las 23:30 en la hora de su plaza, cuando en UTC ya es el día siguiente, cuenta en el día de su plaza')]
    public function test_indicadores_e03_el_pedido_de_las_23_30_cuenta_en_el_dia_de_su_plaza(): void
    {
        // En America/Mexico_City (UTC-6): el martes a las 18:30 es el miércoles a las 00:30 en UTC, y el miércoles a las
        // 23:30 es el jueves a las 05:30. El jueves a las 00:15 ya es jueves en los dos.
        $deLaNoche = $this->pedidoConfirmadoEl('2026-12-02 23:30:00');
        $this->assertSame('2026-12-03 05:30', $deLaNoche->confirmado_en->utc()->format('Y-m-d H:i'));
        $this->pedidoConfirmadoEl('2026-12-01 18:30:00');
        $this->pedidoConfirmadoEl('2026-12-03 00:15:00');
        $this->comoAdministracion();
        $filtros = $this->filtros(self::LUNES_DE_PRUEBA, self::DOMINGO_DE_PRUEBA);

        $datos = $this->datosDeLaGrafica($this->widget(PedidosTotalesGrafica::class, $filtros));
        $this->assertSame([0, 1, 1, 1, 0, 0, 0], $datos['datasets'][0]['data']);

        $this->widget(PedidosTotalesTabla::class, $filtros)
            ->assertTableColumnStateSet('pedidos', 1, '2026-12-02')
            ->assertTableColumnStateSet('pedidos', 1, '2026-12-03');
    }

    #[TestDox('indicadores-E03: la semana y el mes de un pedido también son los de su plaza')]
    public function test_indicadores_e03_la_semana_y_el_mes_tambien_son_los_de_su_plaza(): void
    {
        // El domingo 29 de noviembre a las 23:30 es el lunes 30 en UTC; el lunes 30 a las 23:30, el martes 1 de diciembre.
        $this->pedidoConfirmadoEl('2026-11-29 23:30:00');
        $this->pedidoConfirmadoEl('2026-11-30 23:30:00');
        $this->comoAdministracion();

        $semanas = $this->datosDeLaGrafica(
            $this->widget(PedidosTotalesGrafica::class, $this->filtros('2026-11-23', self::DOMINGO_DE_PRUEBA, 'semana')),
        );
        $this->assertSame(['Semana del 23/11/2026', 'Semana del 30/11/2026'], $semanas['labels']);
        $this->assertSame([1, 1], $semanas['datasets'][0]['data']);

        $meses = $this->datosDeLaGrafica(
            $this->widget(PedidosTotalesGrafica::class, $this->filtros('2026-11-01', '2026-12-31', 'mes')),
        );
        $this->assertSame([2, 0], $meses['datasets'][0]['data']);
    }

    #[TestDox('indicadores-E24: con la zona horaria de referencia de la configuración en UTC-6, a las 19:00 del 31 de marzo en esa zona, cuando en UTC ya es 1 de abril, el hoy del panel y el rango por omisión son de marzo')]
    public function test_indicadores_e24_a_las_19_00_del_ultimo_dia_del_mes_el_hoy_y_el_rango_por_omision_son_de_ese_mes(): void
    {
        config(['app.zona_horaria_sin_plaza' => self::ZONA_HORARIA]);
        $this->pedidoConfirmadoEl('2027-03-31 18:30:00');
        $this->comoAdministracion();
        $this->travelTo(CarbonImmutable::parse('2027-03-31 19:00:00', self::ZONA_HORARIA));
        $this->assertSame('2027-04-01', CarbonImmutable::now('UTC')->toDateString());

        $this->assertSame('2027-03-31', Rango::hoy()->toDateString());
        $this->assertSame('2027-03-01', Rango::desdePorOmision()->toDateString());

        // La página abre en marzo, del día 1 a hoy.
        Livewire::test(Indicadores::class)
            ->assertSet('filters.desde', '2027-03-01')
            ->assertSet('filters.hasta', '2027-03-31');

        // Sin filtros, la gráfica es de marzo, y el pedido de las 18:30 cuenta en su último día.
        $datos = $this->datosDeLaGrafica($this->widget(PedidosTotalesGrafica::class));
        $this->assertCount(31, $datos['labels']);
        $this->assertStringContainsString('31/03/2027', $datos['labels'][30]);
        $this->assertSame(1, $datos['datasets'][0]['data'][30]);

        // La zona sale de la configuración, no de un valor escrito en el código: a las 00:30 del 1 de abril en UTC-6,
        // en la zona de la plaza de prueba 2 (America/Tijuana) todavía es 31 de marzo.
        $this->travelTo(CarbonImmutable::parse('2027-04-01 00:30:00', self::ZONA_HORARIA));
        $this->assertSame('2027-04-01', Rango::hoy()->toDateString());
        config(['app.zona_horaria_sin_plaza' => 'America/Tijuana']);
        $this->assertSame('2027-03-31', Rango::hoy()->toDateString());
    }

    /**
     * Dos pedidos en la semana del lunes 5 de enero de 2026 (el martes y el jueves), uno en la del lunes 12 (el
     * miércoles) y tres en febrero, en tres semanas distintas.
     */
    private function sembrarEneroYFebrero(): void
    {
        foreach (['2026-01-06', '2026-01-08', '2026-01-14', '2026-02-03', '2026-02-10', '2026-02-17'] as $dia) {
            $this->pedidoConfirmadoEl($dia.' 10:00:00');
        }
    }

    /**
     * Tres pedidos confirmados el lunes de la semana de prueba y dos el miércoles, a horas distintas del día.
     */
    private function sembrarLaSemanaDePrueba(): void
    {
        foreach (['09:00:00', '13:15:00', '18:40:00'] as $hora) {
            $this->pedidoConfirmadoEl(self::LUNES_DE_PRUEBA.' '.$hora);
        }

        foreach (['10:00:00', '16:30:00'] as $hora) {
            $this->pedidoConfirmadoEl('2026-12-02 '.$hora);
        }
    }
}
