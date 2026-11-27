<?php

namespace Tests\Feature\Exportacion;

use App\Exportacion\Exportacion;
use App\Models\Pedido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Cell\NumericCell;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\AltaLavanderias\Soporte\PanelDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Exportacion\Soporte\DescargasDePrueba;
use Tests\Feature\Indicadores\Soporte\IndicadoresDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;
use ZipArchive;

/**
 * Cómo se escriben los archivos de las descargas (NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.4, "Reportes exportables
 * generados correctamente"; ADR-0003):
 *
 * - el CSV va en UTF-8 con su marca de orden de bytes, para que Excel lea los acentos y las eñes como se capturaron
 *   (design.md, pregunta abierta 1);
 * - toda celda de texto que empieza con =, +, -, @, tabulador o retorno de carro se escribe como texto y no como
 *   fórmula: en el CSV, con un apóstrofo delante; en el XLSX, como celda de texto, sin fórmula;
 * - las celdas de números siguen siendo números, sin ese escape, aunque sean negativas.
 *
 * El mapa de calor por colonia es el reporte con texto capturado por las personas: la colonia de cada dirección. La
 * cifra negativa es la diferencia del monto promedio cuando baja.
 *
 * Escenarios: exportacion-E07, exportacion-E08 y exportacion-E09 · Spec: openspec/changes/exportacion/specs/
 * exportacion/spec.md · Tarea 3.1
 */
class EscrituraDelArchivoTest extends TestCase
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

    /** La marca de orden de bytes de UTF-8. */
    private const BOM = "\xEF\xBB\xBF";

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();
    }

    #[TestDox('exportacion-E07: con una colonia con acento y eñe, el CSV del mapa de calor va en UTF-8 con su marca de orden de bytes, para que Excel lea el nombre con su acento y su eñe')]
    public function test_exportacion_e07_el_csv_conserva_acentos_y_enes_para_excel(): void
    {
        $this->pedidoEnLaColonia('2026-12-01 10:00:00', 'Peñón de Asunción');
        $this->comoAdministracion();

        $exportacion = $this->descargarElMapaPorColonia();

        $csv = $this->contenidoDelCsv($exportacion);
        $this->assertStringStartsWith(self::BOM, $csv, 'Excel lee el CSV en UTF-8 solo con la marca de orden de bytes.');
        $this->assertSame(1, substr_count($csv, self::BOM));
        $this->assertTrue(mb_check_encoding($csv, 'UTF-8'));
        $this->assertStringContainsString("Pe\u{00F1}\u{00F3}n de Asunci\u{00F3}n", $csv);

        $filas = $this->filasDelCsv($exportacion);
        $this->assertSame(['Colonia', 'Municipio', 'Plaza', 'Pedidos'], $filas[0]);
        $this->assertSame(['Peñón de Asunción', 'Municipio de prueba', $this->plaza->nombre, '1'], $filas[1]);
        $this->assertSame('Peñón de Asunción', $this->valoresDelXlsx($exportacion)[1][0]);
    }

    #[TestDox('exportacion-E08: con una colonia capturada como "=1+1", en el CSV y en el Excel del mapa de calor la celda queda como texto y Excel no la calcula')]
    public function test_exportacion_e08_un_texto_que_empieza_con_signo_igual_queda_como_texto(): void
    {
        $this->pedidoEnLaColonia('2026-12-01 10:00:00', '=1+1');
        $this->comoAdministracion();

        $exportacion = $this->descargarElMapaPorColonia();

        // En el CSV, con un apóstrofo delante: Excel lo muestra como texto.
        $this->assertSame("'=1+1", $this->filasDelCsv($exportacion)[1][0]);

        // En el XLSX, como celda de texto, sin fórmula.
        $hoja = $this->hojaDelXlsx($exportacion);
        $this->assertStringNotContainsString('<f>', $hoja);
        $this->assertStringContainsString('>=1+1<', $hoja);
        $this->assertSame('=1+1', $this->valoresDelXlsx($exportacion)[1][0]);
    }

    #[TestDox('exportacion-E08: también se escriben como texto las que empiezan con +, -, @, tabulador o retorno de carro, y las que ya empiezan con apóstrofo')]
    public function test_exportacion_e08_los_demas_comienzos_peligrosos_tambien_quedan_como_texto(): void
    {
        $colonias = ['+Colonia', '-Colonia', '@Colonia', "\tColonia", "\rColonia", "'Colonia", 'Colonia normal'];

        foreach ($colonias as $i => $colonia) {
            $this->pedidoEnLaColonia(sprintf('2026-12-0%d 10:00:00', $i % 6 + 1), $colonia);
        }
        $this->comoAdministracion();

        $exportacion = $this->descargarElMapaPorColonia();

        $enElCsv = array_column(array_slice($this->filasDelCsv($exportacion), 1), 0);
        $this->assertEqualsCanonicalizing(
            ["'+Colonia", "'-Colonia", "'@Colonia", "'\tColonia", "'\rColonia", "''Colonia", 'Colonia normal'],
            $enElCsv,
        );

        // En el XLSX, cada una como se capturó, en una celda de texto. El XML del XLSX lee un retorno de carro como salto
        // de línea: la celda sigue siendo texto.
        $this->assertEqualsCanonicalizing(
            array_map(fn (string $colonia): string => str_replace("\r", "\n", $colonia), $colonias),
            array_column(array_slice($this->valoresDelXlsx($exportacion), 1), 0),
        );
        $this->assertStringNotContainsString('<f>', $this->hojaDelXlsx($exportacion));
    }

    #[TestDox('exportacion-E09: con una cifra negativa en un reporte, en el CSV y en el Excel la celda queda como número, sin el escape de las celdas de texto')]
    public function test_exportacion_e09_los_numeros_siguen_siendo_numeros(): void
    {
        // El monto promedio baja de 300 a 200: la diferencia es -100.00.
        $this->conMonto($this->pedidoConfirmadoEl('2026-11-24 10:00:00'), '300.00');
        $this->conMonto($this->pedidoConfirmadoEl('2026-12-01 10:00:00'), '200.00');
        $this->comoAdministracion();

        $this->pedirDescarga('descargarMontoPromedioPorPedido', self::SEMANA)->assertHasNoActionErrors();
        $exportacion = $this->ultimaExportacion();

        $fila = $this->filasDelCsv($exportacion)[1];
        $this->assertSame(['200.00', '300.00', '-100.00', 'Bajó'], [$fila[1], $fila[3], $fila[4], $fila[5]]);
        $this->assertStringContainsString(',-100.00,', $this->contenidoDelCsv($exportacion));

        $celda = $this->celdasDelXlsx($exportacion)[1][4];
        $this->assertInstanceOf(NumericCell::class, $celda);
        $this->assertEquals(-100.0, $celda->getValue());
    }

    #[TestDox('exportacion-E09: un texto que parece un número negativo sí lleva el escape, y la cifra de pedidos de su fila sigue siendo número')]
    public function test_exportacion_e09_solo_las_celdas_de_texto_llevan_el_escape(): void
    {
        $this->pedidoEnLaColonia('2026-12-01 10:00:00', '-5');
        $this->comoAdministracion();

        $exportacion = $this->descargarElMapaPorColonia();

        $this->assertSame(["'-5", '1'], [$this->filasDelCsv($exportacion)[1][0], $this->filasDelCsv($exportacion)[1][3]]);
        $celdas = $this->celdasDelXlsx($exportacion)[1];
        $this->assertSame('-5', $celdas[0]->getValue());
        $this->assertInstanceOf(NumericCell::class, $celdas[3]);
    }

    private function descargarElMapaPorColonia(): Exportacion
    {
        $this->pedirDescarga('descargarPedidosPorColonia', self::SEMANA)->assertHasNoActionErrors();

        return $this->ultimaExportacion();
    }

    /**
     * Un pedido confirmado a esa hora de la plaza, con la recolección en esa colonia, dentro de la zona de prueba.
     */
    private function pedidoEnLaColonia(string $fechaYHora, string $colonia): Pedido
    {
        return $this->pedidoConfirmadoEl($fechaYHora, recogida: ['colonia' => $colonia]);
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
     * El XML de la primera hoja del XLSX, donde se ve si una celda es texto o fórmula.
     */
    private function hojaDelXlsx(Exportacion $exportacion): string
    {
        $archivo = (string) tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($archivo, $this->abrirEnlace($exportacion, 'xlsx')->assertOk()->streamedContent());

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($archivo));
        $hoja = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $compartidas = (string) $zip->getFromName('xl/sharedStrings.xml');
        $zip->close();
        unlink($archivo);

        return $hoja.$compartidas;
    }
}
