<?php

namespace App\Exportacion;

use App\Exportacion\Exportadores\ConciliacionExportador;
use App\Exportacion\Exportadores\EntregasPorRepartidorExportador;
use App\Exportacion\Exportadores\IngresosBrutosExportador;
use App\Exportacion\Exportadores\IngresosExportador;
use App\Exportacion\Exportadores\MontoPromedioExportador;
use App\Exportacion\Exportadores\PedidosPorColoniaExportador;
use App\Exportacion\Exportadores\PedidosPorEstadoExportador;
use App\Exportacion\Exportadores\PedidosPorZonaExportador;
use App\Exportacion\Exportadores\PedidosTotalesExportador;
use App\Exportacion\Exportadores\RankingDeLavanderiasExportador;
use App\Exportacion\Exportadores\RetencionExportador;
use App\Exportacion\Exportadores\UsuariosActivosExportador;
use App\Indicadores\Granularidad;
use App\Models\Usuario;
use App\Validacion\RangoDeFechas;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\ExportDispatcher;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/**
 * La creación de la descarga de un reporte: la fila de exports de la cuenta y la cola que escribe su archivo en CSV y
 * en XLSX (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5; openspec/changes/exportacion/; ADR-0022, decisiones 9 y 15).
 *
 * Salió de App\Filament\Actions\DescargarReporte::generar() sin cambiar lo que hace: el botón del panel la llama, y la
 * API de la administración también (POST /api/administracion/descargas; administracion-app-E11 y E56). Lo de cada lado
 * se queda en su lado: el panel arma las opciones con su formulario y avisa con sus notificaciones; la API valida con
 * reglas() y pide con pedir().
 *
 * - No pide columnas: el archivo lleva las del panel (openspec/changes/exportacion/design.md, Non-Goals).
 * - No cuenta registros de un modelo: la exportación nace sin filas y el trabajo de la cola (EscribirArchivoDelReporte)
 *   escribe las del reporte y las cuenta.
 * - Lo demás es de Filament: la cola con su lote, el XLSX, el aviso con los enlaces del panel y la ruta de descarga.
 *
 * Quién puede pedirla lo decide antes el componente único de autorización (puedeDescargarReportes(); ADR-0019).
 */
final class CreacionDeDescarga
{
    /** El campo del reporte en la petición de la API. */
    public const REPORTE = 'reporte';

    /** El campo de la granularidad, con el nombre que le da el formulario de la descarga del panel. */
    public const GRANULARIDAD = 'granularidad';

    /**
     * Los doce reportes que se descargan, uno por exportador de App\Exportacion\Exportadores\: los indicadores del A.5 en
     * el orden de la página de indicadores, y los dos reportes financieros. Su código en la API es el NOMBRE del
     * exportador, el mismo con el que empieza el nombre de su archivo.
     *
     * No están las lavanderías activas frente a registradas (F-I06, DD-083) ni las comisiones (F-I10 y las del periodo,
     * DD-020 y DD-013): el panel todavía no tiene su cifra (administracion-app-E63).
     *
     * @var list<class-string<ExportadorDeIndicador>>
     */
    public const REPORTES = [
        PedidosTotalesExportador::class,
        PedidosPorEstadoExportador::class,
        UsuariosActivosExportador::class,
        RetencionExportador::class,
        MontoPromedioExportador::class,
        RankingDeLavanderiasExportador::class,
        EntregasPorRepartidorExportador::class,
        IngresosBrutosExportador::class,
        PedidosPorColoniaExportador::class,
        PedidosPorZonaExportador::class,
        IngresosExportador::class,
        ConciliacionExportador::class,
    ];

    private const NO_ES_UN_REPORTE = 'Ese no es un reporte que se pueda descargar.';

    private const GRANULARIDAD_INVALIDA = 'Elige ver el reporte por día, por semana o por mes.';

    /**
     * Crea la descarga de la cuenta y pone en cola su archivo, como la acción de exportación de Filament, sin contar
     * registros. La devuelve como quedó después de ponerla en cola: con la cola en la misma petición, ya terminada.
     *
     * @param  class-string<ExportadorDeIndicador>  $exportador
     * @param  array<string, mixed>  $opciones  El rango y la granularidad, como los arma el formulario de la descarga.
     * @param  string|null  $guardia  La guardia de los enlaces del aviso de archivo listo, que es del panel. Por omisión,
     *                                la de la configuración, con la que Filament los arma fuera del panel.
     */
    public function crear(Usuario $cuenta, string $exportador, array $opciones, ?string $guardia = null): Exportacion
    {
        $columnas = collect($exportador::getColumns())
            ->mapWithKeys(fn (ExportColumn $columna): array => [$columna->getName() => $columna->getLabel()])
            ->all();

        /** @var Exportacion $exportacion */
        $exportacion = app(Export::class);
        $exportacion->user()->associate($cuenta);
        $exportacion->exporter = $exportador;
        $exportacion->total_rows = 0;

        $instancia = $exportacion->getExporter(columnMap: $columnas, options: $opciones);
        $exportacion->file_disk = $instancia->getFileDiskName();
        $exportacion->save();
        $exportacion->deleteFileDirectory();
        $exportacion->file_name = $instancia->getFileName($exportacion);
        $exportacion->save();
        $exportacion->unsetRelation('user');

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
            jobConnection: $instancia->getJobConnection(),
            jobBatchName: $instancia->getJobBatchName(),
            authGuard: $guardia ?? (string) config('auth.defaults.guard'),
        );

        return $exportacion->refresh();
    }

    /**
     * Crea la descarga que pide la API, con sus datos ya validados con reglas(): el reporte por su código, y su rango y
     * su granularidad, con las mismas opciones que arma el formulario del panel para ese reporte. La conciliación lleva
     * solo su periodo, como la del panel.
     *
     * @param  array<string, mixed>  $datos
     */
    public function pedir(Usuario $cuenta, array $datos): Exportacion
    {
        return $this->crear(
            $cuenta,
            self::exportadorDe((string) $datos[self::REPORTE]),
            Arr::only($datos, [RangoDeFechas::INICIO, RangoDeFechas::FIN, self::GRANULARIDAD]),
        );
    }

    /**
     * Las reglas de la petición de una descarga en la API: uno de los doce reportes; el rango, con la regla de dominio
     * del rango (App\Validacion\RangoDeFechas; exportacion-E04 a E06); y la granularidad, una de las opciones del formulario
     * del panel (día, semana o mes). La conciliación no la lleva, como en el panel: si llega, no cuenta.
     *
     * @return array<string, list<mixed>>
     */
    public static function reglas(): array
    {
        return [
            self::REPORTE => ['required', 'string', Rule::in(self::codigos())],
            ...RangoDeFechas::reglas(),
            self::GRANULARIDAD => [
                'exclude_if:'.self::REPORTE.','.ConciliacionExportador::NOMBRE,
                'required',
                Rule::in(array_keys(Granularidad::opciones())),
            ],
        ];
    }

    /**
     * Los mensajes propios de reglas(), con los de la regla del rango.
     *
     * @return array<string, string>
     */
    public static function mensajes(): array
    {
        return [
            self::REPORTE.'.required' => self::NO_ES_UN_REPORTE,
            self::REPORTE.'.string' => self::NO_ES_UN_REPORTE,
            self::REPORTE.'.in' => self::NO_ES_UN_REPORTE,
            ...RangoDeFechas::mensajes(),
            self::GRANULARIDAD.'.required' => self::GRANULARIDAD_INVALIDA,
            self::GRANULARIDAD.'.in' => self::GRANULARIDAD_INVALIDA,
        ];
    }

    /**
     * Los códigos de los doce reportes, en el orden de REPORTES.
     *
     * @return list<string>
     */
    public static function codigos(): array
    {
        return array_map(fn (string $exportador): string => $exportador::NOMBRE, self::REPORTES);
    }

    /**
     * El exportador del reporte con ese código, que ya pasó reglas().
     *
     * @return class-string<ExportadorDeIndicador>
     */
    public static function exportadorDe(string $codigo): string
    {
        return self::REPORTES[(int) array_search($codigo, self::codigos(), true)];
    }
}
