<?php

namespace App\Exportacion;

use App\Autorizacion\Autorizacion;
use App\Models\Usuario;
use Filament\Actions\Exports\Jobs\PrepareCsvExport;
use Illuminate\Contracts\Filesystem\Filesystem;
use League\Csv\Bom;
use League\Csv\Writer;
use League\Flysystem\UnableToWriteFile;
use SplTempFileObject;

/**
 * El trabajo de la cola que escribe el CSV de un reporte del panel (openspec/changes/exportacion/design.md;
 * exportacion-E01 a E03). Toma el lugar del primer trabajo de la exportación de Filament (PrepareCsvExport), que reparte
 * los registros de un modelo en lotes: un reporte de indicadores no tiene registros, sino las filas de su consulta, y
 * caben en un solo archivo.
 *
 * - Escribe el encabezado aparte, en UTF-8 con su marca de orden de bytes, como Filament, y las filas en un archivo de
 *   página, con las celdas que da el exportador (ExportadorDeIndicador::filasDelArchivo()). Lo demás lo sigue haciendo
 *   Filament con los mismos archivos: el aviso de archivo listo, el XLSX y la descarga.
 * - Antes de escribir, vuelve a preguntar al componente único de autorización si la cuenta que pidió la descarga
 *   todavía puede bajar reportes: si ya no, no escribe nada.
 */
class EscribirArchivoDelReporte extends PrepareCsvExport
{
    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $cuenta = $this->export->user;

        if (! $cuenta instanceof Usuario || ! app(Autorizacion::class)->puedeDescargarReportes($cuenta)) {
            return;
        }

        /** @var ExportadorDeIndicador $exportador */
        $exportador = $this->exporter;
        $filas = $exportador->filasDelArchivo();

        $encabezado = Writer::from(new SplTempFileObject);
        $encabezado->setOutputBOM(Bom::Utf8);
        $encabezado->setDelimiter($exportador::getCsvDelimiter());
        $encabezado->insertOne(array_values($this->columnMap));

        $contenido = Writer::from(new SplTempFileObject);
        $contenido->setDelimiter($exportador::getCsvDelimiter());
        $contenido->insertAll($filas);

        $this->escribir('headers.csv', $encabezado->toString());
        $this->escribir(str_pad('1', 16, '0', STR_PAD_LEFT).'.csv', $contenido->toString());

        $this->export->forceFill([
            'total_rows' => count($filas),
            'processed_rows' => count($filas),
            'successful_rows' => count($filas),
        ])->save();
    }

    private function escribir(string $nombre, string $contenido): void
    {
        $ruta = $this->export->getFileDirectory().DIRECTORY_SEPARATOR.$nombre;

        if (! $this->export->getFileDisk()->put($ruta, $contenido, Filesystem::VISIBILITY_PRIVATE)) {
            throw UnableToWriteFile::atLocation($ruta);
        }
    }
}
