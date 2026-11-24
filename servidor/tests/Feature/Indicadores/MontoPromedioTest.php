<?php

namespace Tests\Feature\Indicadores;

use App\Filament\Pages\Indicadores;
use App\Filament\Widgets\Indicadores\MontoPromedioIndicador;
use App\Indicadores\MontoPromedio;
use App\Indicadores\Rango;
use App\Indicadores\Tendencia;
use App\Models\Pedido;
use App\Models\Usuario;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\AltaLavanderias\Soporte\PanelDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Indicadores\Soporte\IndicadoresDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * El monto promedio por pedido, como indicador destacado con su tendencia frente al periodo anterior de la misma
 * duración (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5, categoría Usuarios; NSV-2026-001-v8.pdf:46:ANEXO D, Sección
 * D.5; design.md, lectura 4).
 *
 * - El periodo es el rango de la página, con sus dos días; el anterior, los mismos días justo antes.
 * - Pedido del periodo es el que llegó a Confirmado en el periodo, en el día de su plaza (lectura 1).
 * - Qué monto cuenta, el estimado o el final, es DD-012 (indicadores-E10, bloqueado): estas pruebas no lo afirman. Cada
 *   pedido de prueba lleva el mismo monto en los dos campos, así que la cifra no depende de esa respuesta.
 *
 * Todo es ficticio: los montos son los del escenario.
 *
 * Escenario: indicadores-E09 · Spec: openspec/changes/indicadores/specs/indicadores/spec.md · Tarea 2.4
 */
class MontoPromedioTest extends TestCase
{
    use AgendaDePrueba;
    use IndicadoresDePrueba;
    use LavanderiasDePrueba;
    use PanelDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    /** El periodo: la semana del lunes 7 al domingo 13 de diciembre de 2026, por día. */
    private const PERIODO = ['desde' => '2026-12-07', 'hasta' => '2026-12-13', 'granularidad' => 'dia'];

    private ?Usuario $administracion = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();
    }

    #[TestDox('indicadores-E09: con tres pedidos del periodo de 200, 250 y 300 pesos, y dos del periodo anterior de 200, el indicador da 250 pesos y dice que subió')]
    public function test_indicadores_e09_el_promedio_da_250_y_la_tendencia_dice_que_subio(): void
    {
        $this->sembrarElEscenario();
        $this->administracion = $this->comoAdministracion();

        $this->assertSame([
            'promedio' => '250.00',
            'promedio_anterior' => '200.00',
            'diferencia' => '50.00',
            'tendencia' => Tendencia::Subio,
        ], app(MontoPromedio::class)->delRango(Rango::deLosFiltros(self::PERIODO)));

        $stat = $this->stat(self::PERIODO);
        $this->assertSame('$250.00', (string) $stat->getValue());
        $this->assertSame('Subió $50.00 frente al periodo anterior ($200.00)', (string) $stat->getDescription());
        $this->assertSame('success', $stat->getDescriptionColor() ?? $stat->getColor());

        $this->widget(MontoPromedioIndicador::class, self::PERIODO)
            ->assertSee('Monto promedio por pedido')
            ->assertSee('$250.00')
            ->assertSee('Subió $50.00 frente al periodo anterior ($200.00)');
    }

    #[TestDox('indicadores-E09: el periodo anterior tiene la misma duración que el rango y termina el día antes de que empiece')]
    public function test_indicadores_e09_el_periodo_anterior_tiene_la_misma_duracion(): void
    {
        // Del 30 de noviembre al 6 de diciembre: el anterior de la semana del 7 al 13.
        $this->pedidoConMonto('2026-11-30 10:00:00', '100.00');
        $this->pedidoConMonto('2026-12-06 18:00:00', '300.00');
        // El 29 de noviembre ya es de antes del anterior; el 14 de diciembre, de después del periodo.
        $this->pedidoConMonto('2026-11-29 10:00:00', '9000.00');
        $this->pedidoConMonto('2026-12-14 10:00:00', '9000.00');
        $this->pedidoConMonto('2026-12-07 10:00:00', '150.00');

        $cifras = app(MontoPromedio::class)->delRango(Rango::deLosFiltros(self::PERIODO));

        $this->assertSame(['150.00', '200.00', '-50.00', Tendencia::Bajo], [
            $cifras['promedio'], $cifras['promedio_anterior'], $cifras['diferencia'], $cifras['tendencia'],
        ]);

        // Un rango de tres días compara con los tres días de antes.
        $tresDias = app(MontoPromedio::class)->delRango(Rango::deLosFiltros(['desde' => '2026-12-07', 'hasta' => '2026-12-09']));
        $this->assertSame(['150.00', '300.00'], [$tresDias['promedio'], $tresDias['promedio_anterior']]);
    }

    #[TestDox('indicadores-E09: si bajó o no cambió frente al periodo anterior, el indicador lo dice con su texto')]
    public function test_indicadores_e09_la_tendencia_dice_si_bajo_o_no_cambio(): void
    {
        $this->pedidoConMonto('2026-12-01 10:00:00', '300.00');
        $this->pedidoConMonto('2026-12-08 10:00:00', '200.00');
        $this->administracion = $this->comoAdministracion();

        $stat = $this->stat(self::PERIODO);
        $this->assertSame('$200.00', (string) $stat->getValue());
        $this->assertSame('Bajó $100.00 frente al periodo anterior ($300.00)', (string) $stat->getDescription());
        $this->assertSame('danger', $stat->getDescriptionColor() ?? $stat->getColor());

        $this->pedidoConMonto('2026-12-09 10:00:00', '400.00');
        $stat = $this->stat(self::PERIODO);
        $this->assertSame('$300.00', (string) $stat->getValue());
        $this->assertSame('Igual que en el periodo anterior ($300.00)', (string) $stat->getDescription());
    }

    #[TestDox('indicadores-E09: sin pedidos con monto en el periodo, el indicador lo dice y no muestra tendencia; sin pedidos en el anterior, no hay con qué comparar')]
    public function test_indicadores_e09_sin_montos_no_hay_promedio_ni_tendencia(): void
    {
        $this->administracion = $this->comoAdministracion();

        $this->assertSame(
            ['promedio' => null, 'promedio_anterior' => null, 'diferencia' => null, 'tendencia' => null],
            app(MontoPromedio::class)->delRango(Rango::deLosFiltros(self::PERIODO)),
        );
        $stat = $this->stat(self::PERIODO);
        $this->assertSame('Sin monto', (string) $stat->getValue());
        $this->assertSame('No hay pedidos con monto en el periodo.', (string) $stat->getDescription());

        // Un pedido del periodo sin ningún monto (DD-012) no entra al promedio.
        $this->pedidoConfirmadoEl('2026-12-08 10:00:00');
        $this->assertSame('Sin monto', (string) $this->stat(self::PERIODO)->getValue());

        $this->pedidoConMonto('2026-12-09 10:00:00', '180.00');
        $stat = $this->stat(self::PERIODO);
        $this->assertSame('$180.00', (string) $stat->getValue());
        $this->assertSame('Sin pedidos con monto en el periodo anterior para comparar.', (string) $stat->getDescription());
    }

    #[TestDox('indicadores-E09: un pedido que nunca se pagó no cuenta, y el día de cada pedido es el de su plaza')]
    public function test_indicadores_e09_el_pedido_sin_pagar_no_cuenta_y_el_dia_es_el_de_la_plaza(): void
    {
        $sinPagar = $this->pedidoSinPagarEl('2026-12-08 10:00:00');
        $this->ponerMonto($sinPagar, '9000.00');
        // El domingo 13 a las 23:30 en la plaza, cuando en UTC ya es lunes 14: es del periodo.
        $this->pedidoConMonto('2026-12-13 23:30:00', '120.00');
        // El domingo 6 a las 23:30 en la plaza: es del periodo anterior.
        $this->pedidoConMonto('2026-12-06 23:30:00', '100.00');

        $cifras = app(MontoPromedio::class)->delRango(Rango::deLosFiltros(self::PERIODO));

        $this->assertSame(['120.00', '100.00', Tendencia::Subio], [$cifras['promedio'], $cifras['promedio_anterior'], $cifras['tendencia']]);
    }

    #[TestDox('indicadores-E09: el monto promedio está en la página de indicadores')]
    public function test_indicadores_e09_el_monto_promedio_esta_en_la_pagina(): void
    {
        $this->sembrarElEscenario();
        $this->administracion = $this->comoAdministracion();

        $this->assertContains(MontoPromedioIndicador::class, (new Indicadores)->getWidgets());
        $this->get('/admin/indicadores?'.http_build_query(['filters' => self::PERIODO]))
            ->assertOk()
            ->assertSeeLivewire(MontoPromedioIndicador::class)
            ->assertSee('$250.00');
    }

    /**
     * Tres pedidos del periodo de 200, 250 y 300 pesos, y dos del periodo anterior de 200.
     */
    private function sembrarElEscenario(): void
    {
        $this->pedidoConMonto('2026-12-01 10:00:00', '200.00');
        $this->pedidoConMonto('2026-12-03 10:00:00', '200.00');
        $this->pedidoConMonto('2026-12-08 10:00:00', '200.00');
        $this->pedidoConMonto('2026-12-09 10:00:00', '250.00');
        $this->pedidoConMonto('2026-12-10 10:00:00', '300.00');
    }

    /**
     * Un pedido confirmado a esa hora de la plaza, con ese monto. Nada calcula todavía el monto de un pedido (DD-012 y
     * DD-010): la prueba lo escribe en la base, y en los dos campos, para no afirmar cuál cuenta (indicadores-E10).
     */
    private function pedidoConMonto(string $fechaYHora, string $monto): Pedido
    {
        $pedido = $this->pedidoConfirmadoEl($fechaYHora);
        $this->ponerMonto($pedido, $monto);

        return $pedido;
    }

    private function ponerMonto(Pedido $pedido, string $monto): void
    {
        DB::table('pedidos')->where('id_pedidos', $pedido->getKey())->update(['monto_estimado' => $monto, 'monto_final' => $monto]);
    }

    /**
     * El indicador destacado del widget, con esos filtros, como lo ve la administración. Se lee del método protegido
     * getStats() del widget, sin cambiar su visibilidad.
     *
     * Vuelve a entrar con la cuenta de administración: crear un pedido con la API olvida la sesión del panel.
     *
     * @param  array<string, string>  $filtros
     */
    private function stat(array $filtros): Stat
    {
        $this->administracion ??= $this->comoAdministracion();
        $this->actingAs($this->administracion, 'web');

        $widget = $this->widget(MontoPromedioIndicador::class, $filtros)->instance();
        $stats = (fn (): array => $this->getStats())->call($widget);
        $this->assertCount(1, $stats);

        return $stats[0];
    }
}
