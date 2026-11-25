<?php

namespace Tests\Feature\Exportacion;

use App\Autorizacion\Autorizacion;
use App\Exportacion\Exportacion;
use App\Filament\Pages\Indicadores;
use App\Filament\Widgets\Indicadores\PedidosTotalesTabla;
use App\Models\Rol;
use App\Models\Usuario;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\AltaLavanderias\Soporte\PanelDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Exportacion\Soporte\DescargasDePrueba;
use Tests\Feature\Indicadores\Soporte\IndicadoresDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * La descarga del indicador de pedidos totales en CSV y en Excel (XLSX), para el rango que elige la administración
 * (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5, "Descarga de reportes en formato CSV y Excel para cualquier rango de
 * fechas"; NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.4, A44-2).
 *
 * - La administración la pide con el botón "Descargar" de la página de indicadores. La exportación de Filament genera
 *   los dos formatos (ADR-0003) y el aviso trae un enlace para cada uno.
 * - El archivo sale de la misma consulta que la tabla del panel (App\Indicadores\PedidosTotales): una fila por periodo,
 *   con las mismas columnas y las mismas cifras.
 *
 * La semana de prueba va del lunes 30 de noviembre al domingo 6 de diciembre de 2026, por día.
 *
 * Escenarios: exportacion-E01 y exportacion-E02 · Spec: openspec/changes/exportacion/specs/exportacion/spec.md · Tarea
 * 1.1
 */
class DescargaCsvYExcelTest extends TestCase
{
    use AgendaDePrueba;
    use DescargasDePrueba;
    use IndicadoresDePrueba;
    use LavanderiasDePrueba;
    use PanelDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    private const SEMANA = ['desde' => '2026-11-30', 'hasta' => '2026-12-06', 'granularidad' => 'dia'];

    private const ENCABEZADO = ['Periodo', 'Pedidos', 'Periodo anterior', 'Pedidos del periodo anterior'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();
    }

    #[TestDox('exportacion-E01: con el indicador de pedidos totales con datos en el rango, la administración pide la descarga en CSV y recibe un archivo con una fila por periodo y las mismas cifras que muestra el panel')]
    public function test_exportacion_e01_el_csv_trae_una_fila_por_periodo_con_las_cifras_del_panel(): void
    {
        $this->sembrarLaSemana();
        $administracion = $this->comoAdministracion();

        $this->pedirDescarga('descargarPedidosTotales', self::SEMANA)->assertHasNoActionErrors();

        $exportacion = Exportacion::sole();
        $this->assertSame($administracion->getKey(), $exportacion->id_usuarios);
        $this->assertNotNull($exportacion->completed_at);
        $this->assertSame(7, $exportacion->successful_rows);

        $respuesta = $this->abrirEnlace($exportacion, 'csv')->assertOk();
        $this->assertStringContainsString('text/csv', (string) $respuesta->headers->get('Content-Type'));
        $this->assertStringContainsString('pedidos-totales-2026-11-30-al-2026-12-06.csv', (string) $respuesta->headers->get('Content-Disposition'));

        $filas = $this->filasDelCsv($exportacion);
        $this->assertSame(self::ENCABEZADO, $filas[0]);
        $this->assertSame($this->filasDelPanel(), array_slice($filas, 1));

        // Las cifras del escenario: 3 el lunes, 0 el martes y 2 el miércoles.
        $this->assertSame(['lun. 30/11/2026', '3'], array_slice($filas[1], 0, 2));
        $this->assertSame(['mar. 01/12/2026', '0'], array_slice($filas[2], 0, 2));
        $this->assertSame(['mié. 02/12/2026', '2'], array_slice($filas[3], 0, 2));
    }

    #[TestDox('exportacion-E02: con el mismo indicador y el mismo rango, la descarga en Excel es un XLSX con las mismas filas y cifras que el CSV, y las cifras son números')]
    public function test_exportacion_e02_el_xlsx_trae_las_mismas_filas_y_cifras_que_el_csv(): void
    {
        $this->sembrarLaSemana();
        $this->comoAdministracion();

        $this->pedirDescarga('descargarPedidosTotales', self::SEMANA)->assertHasNoActionErrors();
        $exportacion = Exportacion::sole();

        $respuesta = $this->abrirEnlace($exportacion, 'xlsx')->assertOk();
        $this->assertStringContainsString('pedidos-totales-2026-11-30-al-2026-12-06.xlsx', (string) $respuesta->headers->get('Content-Disposition'));

        $celdas = $this->celdasDelXlsx($exportacion);
        $valores = $this->valoresDelXlsx($exportacion);
        $this->assertSame(self::ENCABEZADO, $valores[0]);
        $this->assertSame(
            $this->filasDelCsv($exportacion),
            array_map(fn (array $fila): array => array_map('strval', $fila), $valores),
        );

        foreach (array_slice($celdas, 1) as $fila) {
            $this->assertInstanceOf(StringCell::class, $fila[0]);
            $this->assertInstanceOf(NumericCell::class, $fila[1]);
            $this->assertInstanceOf(StringCell::class, $fila[2]);
            $this->assertInstanceOf(NumericCell::class, $fila[3]);
        }

        $this->assertSame([3, 0, 2], array_slice(array_column(array_slice($valores, 1), 1), 0, 3));
    }

    #[TestDox('exportacion-E01: el botón de descarga está en la página de indicadores, con el rango y la granularidad de la página')]
    public function test_exportacion_e01_el_boton_esta_en_la_pagina_con_el_rango_de_la_pagina(): void
    {
        $this->comoAdministracion();

        Livewire::test(Indicadores::class, ['filters' => self::SEMANA])
            ->assertActionExists('descargarPedidosTotales')
            ->mountAction('descargarPedidosTotales')
            ->assertActionDataSet(['desde' => '2026-11-30', 'hasta' => '2026-12-06', 'granularidad' => 'dia']);

        $this->get('/admin/indicadores')->assertOk()->assertSee('Descargar');
    }

    #[TestDox('exportacion-E01: una cuenta que no es de administración no pide descargas, y el componente no se las da')]
    public function test_exportacion_e01_solo_la_administracion_pide_descargas(): void
    {
        Filament::setCurrentPanel('admin');
        $autorizacion = app(Autorizacion::class);

        foreach ([Rol::USUARIO, Rol::LAVANDERIA, Rol::REPARTIDOR] as $rol) {
            $cuenta = Usuario::factory()->conRol($rol)->create();
            $this->actingAs($cuenta, 'web');

            $this->assertFalse($autorizacion->puedeDescargarReportes($cuenta), $rol);
            Livewire::test(Indicadores::class)->assertForbidden();
        }

        $this->assertTrue($autorizacion->puedeDescargarReportes(Usuario::factory()->conRol(Rol::ADMINISTRACION)->create()));
        $this->assertSame(0, Exportacion::count());
    }

    /**
     * Tres pedidos confirmados el lunes 30 de noviembre y dos el miércoles 2 de diciembre.
     */
    private function sembrarLaSemana(): void
    {
        foreach (['08:00', '09:00', '10:00'] as $hora) {
            $this->pedidoConfirmadoEl("2026-11-30 {$hora}:00");
        }

        foreach (['08:00', '09:00'] as $hora) {
            $this->pedidoConfirmadoEl("2026-12-02 {$hora}:00");
        }
    }

    /**
     * Las filas que muestra la tabla de pedidos totales del panel con el mismo rango, cada celda como texto.
     *
     * @return list<list<string>>
     */
    private function filasDelPanel(): array
    {
        $this->actingAs(Usuario::factory()->conRol(Rol::ADMINISTRACION)->create(), 'web');
        $registros = $this->widget(PedidosTotalesTabla::class, self::SEMANA)->instance()->getTableRecords();

        return array_values($registros->map(fn (array $fila): array => array_map('strval', [
            $fila['periodo'],
            $fila['pedidos'],
            $fila['periodo_anterior'],
            $fila['pedidos_anterior'],
        ]))->all());
    }
}
