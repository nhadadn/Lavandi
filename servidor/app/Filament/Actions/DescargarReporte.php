<?php

namespace App\Filament\Actions;

use App\Autorizacion\Autorizacion;
use App\Exportacion\EscribirArchivoDelReporte;
use App\Exportacion\ExportadorDeIndicador;
use App\Models\Usuario;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\ExportDispatcher;
use Filament\Actions\Exports\Models\Export;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Support\Arr;

/**
 * El botón que descarga un reporte del panel en CSV y en Excel (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5;
 * openspec/changes/exportacion/; ADR-0003): la acción de exportación de Filament, con el exportador del reporte.
 *
 * Cambia dos cosas de la de Filament:
 * - no pide columnas: el archivo lleva las del panel (design.md, Non-Goals);
 * - no cuenta registros de un modelo: la exportación nace sin filas y el trabajo de la cola (EscribirArchivoDelReporte)
 *   escribe las del reporte y las cuenta.
 *
 * Lo demás es de Filament: la fila de exports, la cola con su lote, el XLSX, el aviso con los enlaces y la ruta de
 * descarga. Solo la administración la ve y la usa, según el componente único de autorización (ADR-0019).
 */
class DescargarReporte extends ExportAction
{
    /**
     * El botón de descarga de ese reporte, con el nombre que le da su exportador.
     *
     * @param  class-string<ExportadorDeIndicador>  $exportador
     */
    public static function de(string $exportador): static
    {
        return static::make($exportador::nombreDeLaAccion())->exporter($exportador);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->columnMapping(false);
        $this->label(fn (DescargarReporte $action): string => $action->exportador()::titulo());
        $this->modalHeading(fn (DescargarReporte $action): string => 'Descargar '.mb_lcfirst($action->exportador()::titulo()));
        $this->modalDescription('El archivo sale en CSV y en Excel (XLSX), con las cifras del panel para el rango que elijas, con sus dos fechas.');
        $this->modalSubmitActionLabel('Descargar');
        $this->authorize(fn (): bool => self::cuenta() instanceof Usuario && app(Autorizacion::class)->puedeDescargarReportes(self::cuenta()));
        $this->action(fn (DescargarReporte $action, array $data) => $action->generar($data));
    }

    /**
     * @return class-string<ExportadorDeIndicador>
     */
    public function exportador(): string
    {
        /** @var class-string<ExportadorDeIndicador> */
        return $this->getExporter();
    }

    /**
     * Crea la exportación de la cuenta y pone en cola su archivo, como la acción de Filament, sin contar registros.
     *
     * @param  array<string, mixed>  $data
     */
    public function generar(array $data): void
    {
        $exportador = $this->exportador();
        $opciones = [...$this->getOptions(), ...Arr::only($data, ['desde', 'hasta', 'granularidad'])];
        $columnas = collect($exportador::getColumns())
            ->mapWithKeys(fn (ExportColumn $columna): array => [$columna->getName() => $columna->getLabel()])
            ->all();

        $exportacion = app(Export::class);
        $exportacion->user()->associate(self::cuenta());
        $exportacion->exporter = $exportador;
        $exportacion->total_rows = 0;

        $instancia = $exportacion->getExporter(columnMap: $columnas, options: $opciones);
        $exportacion->file_disk = $instancia->getFileDiskName();
        $exportacion->save();
        $exportacion->deleteFileDirectory();
        $exportacion->file_name = $instancia->getFileName($exportacion);
        $exportacion->save();
        $exportacion->unsetRelation('user');

        $conexion = $instancia->getJobConnection();

        app(ExportDispatcher::class)->dispatch(
            export: $exportacion,
            serializedQuery: '',
            columnMap: $columnas,
            options: $opciones,
            formats: $instancia->getFormats(),
            records: null,
            job: EscribirArchivoDelReporte::class,
            chunkSize: 1,
            jobQueue: $instancia->getJobQueue(),
            jobConnection: $conexion,
            jobBatchName: $instancia->getJobBatchName(),
            authGuard: $this->getAuthGuard(),
        );

        // Con la cola en la misma petición, el aviso de archivo listo ya salió.
        if ($conexion === 'sync' || (blank($conexion) && config('queue.default') === 'sync')) {
            $this->successNotification(null);
            $this->successNotificationTitle(null);

            return;
        }

        $this->successNotification(
            Notification::make()
                ->title('El archivo se está generando')
                ->body('Te avisamos aquí, en los avisos del panel, cuando esté listo para descargar.')
                ->success(),
        );
    }

    private static function cuenta(): ?Usuario
    {
        $cuenta = Filament::auth()->user();

        return $cuenta instanceof Usuario ? $cuenta : null;
    }
}
