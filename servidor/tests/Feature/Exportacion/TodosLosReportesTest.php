<?php

namespace Tests\Feature\Exportacion;

use App\Cobro\PasarelaSimulada;
use App\Exportacion\Exportacion;
use App\Filament\Pages\Conciliacion;
use App\Filament\Pages\Indicadores;
use App\Filament\Pages\ReporteDeIngresos;
use App\Filament\Widgets\Finanzas\IngresosDelPeriodo;
use App\Filament\Widgets\Finanzas\IngresosPorPeriodoTabla;
use App\Filament\Widgets\Indicadores\EntregasPorRepartidorTabla;
use App\Filament\Widgets\Indicadores\IngresosBrutosGrafica;
use App\Filament\Widgets\Indicadores\MapaDeCalor;
use App\Filament\Widgets\Indicadores\MontoPromedioIndicador;
use App\Filament\Widgets\Indicadores\PedidosPorEstadoGrafica;
use App\Filament\Widgets\Indicadores\PedidosTotalesTabla;
use App\Filament\Widgets\Indicadores\RankingDeLavanderiasTabla;
use App\Filament\Widgets\Indicadores\RetencionTabla;
use App\Filament\Widgets\Indicadores\UsuariosActivosGrafica;
use App\Finanzas\Ingresos;
use App\Indicadores\MontoPromedio;
use App\Indicadores\Rango;
use App\Models\Lavanderia;
use App\Models\Pedido;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Cell\EmptyCell;
use OpenSpout\Common\Entity\Cell\NumericCell;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\AltaLavanderias\Soporte\PanelDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Estados\Soporte\EstadosDePrueba;
use Tests\Feature\Exportacion\Soporte\DescargasDePrueba;
use Tests\Feature\Indicadores\Soporte\IndicadoresDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * Cada reporte del panel se descarga en CSV y en Excel, y sus cifras coinciden con las del panel para el mismo rango
 * (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5; NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #6;
 * NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.4, A44-2):
 *
 * - tarea 1.2, operaciones y usuarios (F-I01 a F-I05): pedidos totales, pedidos por estado, usuarios activos, usuarios
 *   que vuelven a pedir por mes de registro y monto promedio por pedido;
 * - tarea 1.3, socios, dinero y geografía (F-I07 a F-I09 y F-I11 por zona; F-I11 por colonia está en
 *   EscrituraDelArchivoTest) y los reportes financieros de ingresos y de conciliación con la pasarela.
 *
 * Siguen bloqueados, y no tienen descarga porque el panel todavía no tiene su cifra: las lavanderías activas frente a
 * registradas (F-I06, DD-083), las comisiones (F-I10 y el reporte de comisiones, DD-020 y DD-013).
 *
 * Las cifras del panel se leen de cada widget o página con los mismos filtros, y el archivo sale de la misma consulta: si
 * alguna difiere, el reporte no es el del panel.
 *
 * El rango de prueba es la semana del lunes 7 al domingo 13 de diciembre de 2026, por día; las cuentas se registran el
 * jueves 3 de diciembre. Todo es ficticio.
 *
 * Escenario: exportacion-E03 · Spec: openspec/changes/exportacion/specs/exportacion/spec.md · Tareas 1.2 y 1.3
 */
class TodosLosReportesTest extends TestCase
{
    use AgendaDePrueba;
    use DescargasDePrueba;
    use EstadosDePrueba;
    use IndicadoresDePrueba;
    use LavanderiasDePrueba;
    use PanelDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    private const SEMANA = ['desde' => '2026-12-07', 'hasta' => '2026-12-13', 'granularidad' => 'dia'];

    private ?Usuario $administracion = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();
        $this->sembrarLaBase();
    }

    #[TestDox('exportacion-E03: el reporte de pedidos totales se descarga en CSV y en Excel con las cifras del panel')]
    public function test_exportacion_e03_pedidos_totales_coincide_con_el_panel(): void
    {
        $registros = $this->comoElPanel(fn () => $this->widget(PedidosTotalesTabla::class, self::SEMANA)->instance()->getTableRecords());
        $panel = array_values($registros->map(fn (array $fila): array => [$fila['periodo'], $fila['pedidos'], $fila['periodo_anterior'], $fila['pedidos_anterior']])->all());

        $exportacion = $this->descargar('descargarPedidosTotales');

        $this->assertReporte($exportacion, ['Periodo', 'Pedidos', 'Periodo anterior', 'Pedidos del periodo anterior'], $panel);
        $this->assertSame(3, array_sum(array_column($panel, 1)));
    }

    #[TestDox('exportacion-E03: el reporte de pedidos por estado se descarga en CSV y en Excel con las cifras de la dona del panel')]
    public function test_exportacion_e03_pedidos_por_estado_coincide_con_el_panel(): void
    {
        $dona = $this->comoElPanel(fn () => $this->datosDeLaGrafica($this->widget(PedidosPorEstadoGrafica::class, self::SEMANA)));
        $panel = array_map(null, $dona['labels'], $dona['datasets'][0]['data']);

        $exportacion = $this->descargar('descargarPedidosPorEstado');

        $this->assertReporte($exportacion, ['Estado', 'Pedidos'], $panel);
        $this->assertSame([['Activos', 2], ['Completados', 1]], $panel);
    }

    #[TestDox('exportacion-E03: el reporte de usuarios activos se descarga en CSV y en Excel con las cifras de la línea del panel')]
    public function test_exportacion_e03_usuarios_activos_coincide_con_el_panel(): void
    {
        $linea = $this->comoElPanel(fn () => $this->datosDeLaGrafica($this->widget(UsuariosActivosGrafica::class, self::SEMANA)));
        $panel = array_map(null, $linea['labels'], $linea['datasets'][0]['data']);

        $exportacion = $this->descargar('descargarUsuariosActivos');

        $this->assertReporte($exportacion, ['Periodo', 'Usuarios activos'], $panel);
        $this->assertSame([1, 2, 0, 0, 0, 0, 0], array_column($panel, 1));
    }

    #[TestDox('exportacion-E03: el reporte de usuarios que vuelven a pedir se descarga en CSV y en Excel con las cifras de la tabla del panel, y un mes sin porcentaje queda vacío')]
    public function test_exportacion_e03_retencion_coincide_con_el_panel(): void
    {
        $filtros = ['desde' => '2026-11-01', 'hasta' => '2026-12-31', 'granularidad' => 'mes'];
        $registros = $this->comoElPanel(fn () => $this->widget(RetencionTabla::class, $filtros)->instance()->getTableRecords());
        $panel = array_values($registros->map(fn (array $fila): array => [$fila['mes'], $fila['con_primer_pedido'], $fila['porcentaje']])->all());

        $exportacion = $this->descargar('descargarUsuariosQueVuelvenAPedir', $filtros);

        $this->assertReporte($exportacion, ['Mes de registro', 'Con primer pedido', 'Volvieron a pedir (%)'], $panel);
        // En diciembre se registraron las dos cuentas de la semana y la del pedido del martes 1: tres pidieron y una
        // volvió a pedir.
        $this->assertSame([['noviembre 2026', 0, null], ['diciembre 2026', 3, 33.3]], $panel);

        // El porcentaje es un número en el Excel, y el que no hay, una celda vacía.
        $celdas = $this->celdasDelXlsx($exportacion);
        $this->assertInstanceOf(EmptyCell::class, $celdas[1][2]);
        $this->assertInstanceOf(NumericCell::class, $celdas[2][2]);
        $this->assertEquals(33.3, $celdas[2][2]->getValue());
    }

    #[TestDox('exportacion-E03: el reporte de monto promedio se descarga en CSV y en Excel con las cifras del indicador del panel')]
    public function test_exportacion_e03_monto_promedio_coincide_con_el_panel(): void
    {
        $this->comoElPanel(fn () => $this->widget(MontoPromedioIndicador::class, self::SEMANA)->assertSee('$250.00'));
        $cifras = app(MontoPromedio::class)->delRango(Rango::deLosFiltros(self::SEMANA));
        $panel = [[
            'Del 07/12/2026 al 13/12/2026',
            $cifras['promedio'],
            'Del 30/11/2026 al 06/12/2026',
            $cifras['promedio_anterior'],
            $cifras['diferencia'],
            $cifras['tendencia']->getLabel(),
        ]];

        $exportacion = $this->descargar('descargarMontoPromedioPorPedido');

        $this->assertReporte(
            $exportacion,
            ['Periodo', 'Monto promedio por pedido', 'Periodo anterior', 'Monto promedio del periodo anterior', 'Diferencia', 'Tendencia'],
            $panel,
        );
        $this->assertSame(['250.00', '200.00', '50.00', 'Subió'], [$panel[0][1], $panel[0][3], $panel[0][4], $panel[0][5]]);
        $fila = $this->celdasDelXlsx($exportacion)[1];
        foreach ([1 => 250.0, 3 => 200.0, 4 => 50.0] as $columna => $monto) {
            $this->assertInstanceOf(NumericCell::class, $fila[$columna]);
            $this->assertEquals($monto, $fila[$columna]->getValue());
        }
    }

    #[TestDox('exportacion-E03: el ranking de calificación por lavandería se descarga en CSV y en Excel con las cifras de la tabla del panel, y el semáforo por su texto')]
    public function test_exportacion_e03_ranking_coincide_con_el_panel(): void
    {
        config(['indicadores.semaforo.corte_alto' => '4.5', 'indicadores.semaforo.corte_bajo' => '4.0']);
        $this->calificar($this->lavanderia, [5, 4]);
        $sinCalificar = Lavanderia::factory()->en($this->plaza)->create(['nombre' => 'Lavandería sin calificar']);

        $registros = $this->comoElPanel(fn () => $this->widget(RankingDeLavanderiasTabla::class, self::SEMANA)->instance()->getTableRecords());
        $panel = array_values($registros->map(fn (array $fila): array => [
            $fila['lugar'],
            $fila['lavanderia'],
            $fila['promedio'] === null ? null : number_format($fila['promedio'], 1, '.', ''),
            $fila['semaforo']?->getLabel(),
        ])->all());

        $exportacion = $this->descargar('descargarCalificacionPorLavanderia');

        $this->assertReporte($exportacion, ['Lugar', 'Lavandería', 'Calificación promedio', 'Semáforo'], $panel);
        $this->assertSame([[1, $this->lavanderia->nombre, '4.5', 'Verde'], [null, $sinCalificar->nombre, null, null]], $panel);
    }

    #[TestDox('exportacion-E03: las entregas completadas por repartidor se descargan en CSV y en Excel con las cifras de la tabla del panel')]
    public function test_exportacion_e03_entregas_por_repartidor_coinciden_con_el_panel(): void
    {
        $registros = $this->comoElPanel(fn () => $this->widget(EntregasPorRepartidorTabla::class, self::SEMANA)->instance()->getTableRecords());
        $panel = array_values($registros->map(fn (array $fila): array => [$fila['repartidor'], $fila['plaza'], $fila['entregas']])->all());

        $exportacion = $this->descargar('descargarEntregasPorRepartidor');

        $this->assertReporte($exportacion, ['Repartidor', 'Plaza', 'Entregas completadas'], $panel);
        // El pedido entregado del lunes 7.
        $this->assertSame([1], array_column($panel, 2));
    }

    #[TestDox('exportacion-E03: el mapa de calor por zona de servicio se descarga en CSV y en Excel con las cifras de la tabla del mapa')]
    public function test_exportacion_e03_pedidos_por_zona_coinciden_con_el_panel(): void
    {
        $zonas = $this->comoElPanel(fn () => $this->datosDelMapa($this->widget(MapaDeCalor::class, self::SEMANA)->set('agrupacion', MapaDeCalor::POR_ZONA))['zonas']);
        $panel = array_map(fn (array $zona): array => [$zona['nombre'], $zona['detalle'], $zona['pedidos']], $zonas);

        $exportacion = $this->descargar('descargarPedidosPorZona');

        $this->assertReporte($exportacion, ['Zona de servicio', 'Plaza', 'Pedidos'], $panel);
        $this->assertSame([[$this->zona->nombre, $this->plaza->nombre, 3]], $panel);
    }

    #[TestDox('exportacion-E03: los ingresos brutos se descargan en CSV y en Excel con las cifras de las barras del panel, en pesos con dos decimales')]
    public function test_exportacion_e03_ingresos_brutos_coinciden_con_el_panel(): void
    {
        $barras = $this->comoElPanel(fn () => $this->datosDeLaGrafica($this->widget(IngresosBrutosGrafica::class, self::SEMANA)));
        $panel = array_map(
            fn (string $periodo, float $ingresos): array => [$periodo, number_format($ingresos, 2, '.', '')],
            $barras['labels'],
            $barras['datasets'][0]['data'],
        );

        $exportacion = $this->descargar('descargarIngresosBrutos');

        $this->assertReporte($exportacion, ['Periodo', 'Ingresos brutos'], $panel);
        // Los pagos del lunes 7 y del martes 8, de 100 cada uno.
        $this->assertSame(['100.00', '200.00', '0.00', '0.00', '0.00', '0.00', '0.00'], array_column($panel, 1));
    }

    #[TestDox('exportacion-E03: el reporte financiero de ingresos se descarga en CSV y en Excel desde su página, con las cifras de su tabla y el total del periodo')]
    public function test_exportacion_e03_ingresos_por_periodo_coinciden_con_el_reporte_financiero(): void
    {
        $registros = $this->comoElPanel(fn () => $this->widget(IngresosPorPeriodoTabla::class, self::SEMANA)->instance()->getTableRecords());
        $this->comoElPanel(fn () => $this->widget(IngresosDelPeriodo::class, self::SEMANA)->assertSee(['$300.00', '3 pagos confirmados']));
        $total = app(Ingresos::class)->delRango(Rango::deLosFiltros(self::SEMANA));
        $panel = [
            ...array_values($registros->map(fn (array $fila): array => [$fila['periodo'], $fila['pagos'], $fila['ingresos']])->all()),
            ['Total del periodo', $total['pagos'], $total['ingresos']],
        ];

        $exportacion = $this->descargar('descargarIngresosPorPeriodo', pagina: ReporteDeIngresos::class);

        $this->assertReporte($exportacion, ['Periodo', 'Pagos confirmados', 'Ingresos'], $panel);
        $this->assertSame(['Total del periodo', 3, '300.00'], $panel[7]);
    }

    #[TestDox('exportacion-E03: la conciliación con la pasarela se descarga en CSV y en Excel desde su página, después de conciliar, con el periodo conciliado y las filas de su tabla')]
    public function test_exportacion_e03_conciliacion_coincide_con_la_pagina(): void
    {
        $periodo = ['desde' => self::SEMANA['desde'], 'hasta' => self::SEMANA['hasta']];
        // Un pago de la pasarela que no es de ningún pedido: una diferencia en el reporte.
        app(PasarelaSimulada::class)->acreditar('orden-de-otro-sistema', '80.00', CarbonImmutable::parse('2026-12-09 16:00:00', 'UTC'));
        $this->entrarComoAdministracion();

        $pagina = Livewire::test(Conciliacion::class)
            ->assertActionHidden('descargarConciliacionConLaPasarela')
            ->callAction('conciliar', data: $periodo)
            ->assertHasNoActionErrors()
            ->assertActionVisible('descargarConciliacionConLaPasarela');
        $panel = array_values($pagina->instance()->getTableRecords()->map(fn (array $fila): array => [
            $fila['resultado']->getLabel(),
            $fila['orden'],
            $fila['folio'],
            $fila['plaza'],
            $fila['fecha'],
            $fila['monto_plataforma'],
            $fila['monto_pasarela'],
        ])->all());

        $pagina->callAction('descargarConciliacionConLaPasarela')->assertHasNoActionErrors();
        $exportacion = $this->ultimaExportacion();

        $this->assertReporte(
            $exportacion,
            ['Resultado', 'Orden en la pasarela', 'Folio', 'Plaza', 'Fecha del pago', 'Monto en la plataforma', 'Monto en la pasarela'],
            $panel,
        );
        $this->assertSame(['Conciliado', 'Conciliado', 'Conciliado', 'Movimiento sin pedido'], array_column($panel, 0));
        // El archivo es del periodo conciliado: lo dice su nombre.
        $this->assertSame('conciliacion-con-la-pasarela-2026-12-07-al-2026-12-13', $exportacion->file_name);
    }

    /**
     * Dos cuentas registradas el jueves 3 de diciembre. La primera hizo dos pedidos en la semana, el lunes 7 de 200 y
     * el martes 8 de 300, y el primero se entregó; la segunda, uno el martes 8 de 250. En la semana anterior hubo un
     * pedido de 200. La primera usó la aplicación el lunes y el martes; la segunda, el martes.
     */
    private function sembrarLaBase(): void
    {
        $this->elJuevesALas('08:00:00');
        $una = $this->cuentaDeUsuario();
        $otra = $this->cuentaDeUsuario();

        $this->conMonto($this->pedidoConfirmadoEl('2026-12-01 10:00:00'), '200.00');
        $entregado = $this->conMonto($this->pedidoConfirmadoEl('2026-12-07 10:00:00', $una), '200.00');
        $this->aceptarComoLaLavanderia($entregado);
        $this->llevarHastaEntregado($entregado);
        $this->conMonto($this->pedidoConfirmadoEl('2026-12-08 10:00:00', $una), '300.00');
        $this->conMonto($this->pedidoConfirmadoEl('2026-12-08 11:00:00', $otra), '250.00');

        DB::table('actividad_usuarios')->insert([
            ['id_usuarios' => $una->getKey(), 'fecha' => '2026-12-07'],
            ['id_usuarios' => $una->getKey(), 'fecha' => '2026-12-08'],
            ['id_usuarios' => $otra->getKey(), 'fecha' => '2026-12-08'],
        ]);
    }

    /**
     * Nada calcula todavía el monto de un pedido (DD-012): la prueba lo escribe en los dos campos.
     */
    private function conMonto(Pedido $pedido, string $monto): Pedido
    {
        DB::table('pedidos')->where('id_pedidos', $pedido->getKey())->update(['monto_estimado' => $monto, 'monto_final' => $monto]);

        return $pedido;
    }

    /**
     * Lo que muestra el panel, como lo ve la administración.
     */
    private function comoElPanel(callable $leer): mixed
    {
        $this->entrarComoAdministracion();

        return $leer();
    }

    /**
     * La administración pide la descarga con esos filtros, o con los de la semana, en la página de indicadores o en la
     * que se pase.
     *
     * @param  array<string, string>|null  $filtros
     * @param  class-string  $pagina
     */
    private function descargar(string $accion, ?array $filtros = null, string $pagina = Indicadores::class): Exportacion
    {
        $this->entrarComoAdministracion();
        $this->pedirDescarga($accion, $filtros ?? self::SEMANA, $pagina)->assertHasNoActionErrors();

        return $this->ultimaExportacion();
    }

    /**
     * Lo que el widget del mapa de calor le da al mapa: los datos en JSON del atributo data-mapa-datos.
     *
     * @return array{agrupacion: string, puntos: list<array<string, mixed>>, zonas: list<array<string, mixed>>}
     */
    private function datosDelMapa(Testable $mapa): array
    {
        $this->assertSame(1, preg_match('/data-mapa-datos="([^"]*)"/', $mapa->html(), $coincidencia), 'El widget no trae los datos del mapa.');

        return json_decode(html_entity_decode($coincidencia[1], ENT_QUOTES | ENT_HTML5), true, 512, JSON_THROW_ON_ERROR);
    }

    private function entrarComoAdministracion(): void
    {
        if ($this->administracion === null) {
            $this->administracion = $this->comoAdministracion();
        }

        $this->actingAs($this->administracion, 'web');
    }

    /**
     * El CSV trae el encabezado y las filas del panel, y el XLSX, las mismas filas y cifras, con los números como
     * números.
     *
     * @param  list<string>  $encabezado
     * @param  list<list<mixed>>  $panel
     */
    private function assertReporte(Exportacion $exportacion, array $encabezado, array $panel): void
    {
        $comoTexto = fn (array $fila): array => array_map(fn (mixed $celda): string => (string) $celda, $fila);

        $csv = $this->filasDelCsv($exportacion);
        $this->assertSame($encabezado, $csv[0]);
        $this->assertSame(array_map($comoTexto, $panel), array_slice($csv, 1));

        // El Excel guarda los números como números: "250.00" se lee 250. Se comparan como números; el texto, igual.
        $xlsx = $this->valoresDelXlsx($exportacion);
        $this->assertSame($encabezado, $xlsx[0]);
        $this->assertCount(count($csv), $xlsx);

        foreach (array_slice($csv, 1) as $i => $fila) {
            foreach ($fila as $j => $celda) {
                $valor = $xlsx[$i + 1][$j] ?? null;

                is_numeric($celda)
                    ? $this->assertEquals((float) $celda, $valor, "Fila {$i}, columna {$j}.")
                    : $this->assertSame($celda, (string) $valor, "Fila {$i}, columna {$j}.");
            }
        }

        $this->assertSame(count($panel), $exportacion->successful_rows);
    }
}
