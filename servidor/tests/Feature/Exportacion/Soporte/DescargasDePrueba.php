<?php

namespace Tests\Feature\Exportacion\Soporte;

use App\Exportacion\Exportacion;
use App\Filament\Pages\Indicadores;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use League\Csv\Reader as LectorDeCsv;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Reader\XLSX\Reader as LectorDeXlsx;

/**
 * Lo que comparten las pruebas de la descarga de reportes (openspec/changes/exportacion/): pedir la descarga en la
 * página de indicadores, como la administración con el botón "Descargar", y leer el CSV y el XLSX que genera, con el
 * enlace del aviso de archivo listo (la ruta de descarga de la exportación de Filament). Se usa junto con
 * PanelDePrueba, después de comoAdministracion().
 *
 * Las pruebas corren la cola en la misma petición (QUEUE_CONNECTION=sync, phpunit.xml): al volver de la acción, el
 * archivo ya está escrito. La generación en segundo plano la prueba GeneracionEnSegundoPlanoTest con la cola de la
 * base.
 */
trait DescargasDePrueba
{
    /**
     * La administración pide la descarga de un reporte, con el rango y la granularidad, en la página de indicadores o en
     * la del reporte que se pase, como la de los ingresos.
     *
     * @param  array<string, mixed>  $datos
     * @param  class-string  $pagina
     */
    protected function pedirDescarga(string $accion, array $datos, string $pagina = Indicadores::class): Testable
    {
        return Livewire::test($pagina)->callAction($accion, data: $datos);
    }

    /**
     * La última exportación que se pidió.
     */
    protected function ultimaExportacion(): Exportacion
    {
        return Exportacion::query()->latest('id')->firstOrFail();
    }

    /**
     * El enlace del aviso de archivo listo para ese formato: la ruta de descarga de la exportación de Filament, firmada,
     * como la arma su aviso.
     */
    protected function enlaceDeDescarga(Exportacion $exportacion, string $formato): string
    {
        return URL::signedRoute(
            'filament.exports.download',
            ['authGuard' => 'web', 'export' => $exportacion, 'format' => $formato],
            absolute: false,
        );
    }

    protected function abrirEnlace(Exportacion $exportacion, string $formato): TestResponse
    {
        return $this->get($this->enlaceDeDescarga($exportacion, $formato));
    }

    /**
     * El CSV tal como lo descarga la administración, en bytes.
     */
    protected function contenidoDelCsv(Exportacion $exportacion): string
    {
        return $this->abrirEnlace($exportacion, 'csv')->assertOk()->streamedContent();
    }

    /**
     * Las filas del CSV, con el encabezado primero, cada celda como texto.
     *
     * @return list<list<string>>
     */
    protected function filasDelCsv(Exportacion $exportacion): array
    {
        $lector = LectorDeCsv::fromString($this->contenidoDelCsv($exportacion));

        return array_values(array_map('array_values', iterator_to_array($lector->getRecords(), false)));
    }

    /**
     * Las celdas del XLSX, fila por fila, con el encabezado primero.
     *
     * @return list<list<Cell>>
     */
    protected function celdasDelXlsx(Exportacion $exportacion): array
    {
        $respuesta = $this->abrirEnlace($exportacion, 'xlsx')->assertOk();
        $archivo = (string) tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($archivo, $respuesta->streamedContent());

        $lector = new LectorDeXlsx;
        $lector->open($archivo);
        $filas = [];

        foreach ($lector->getSheetIterator() as $hoja) {
            foreach ($hoja->getRowIterator() as $fila) {
                $filas[] = array_values($fila->getCells());
            }

            break;
        }

        $lector->close();
        unlink($archivo);

        return $filas;
    }

    /**
     * Los valores del XLSX, fila por fila, con el encabezado primero.
     *
     * @return list<list<mixed>>
     */
    protected function valoresDelXlsx(Exportacion $exportacion): array
    {
        return array_map(
            fn (array $fila): array => array_map(fn (Cell $celda): mixed => $celda->getValue(), $fila),
            $this->celdasDelXlsx($exportacion),
        );
    }
}
