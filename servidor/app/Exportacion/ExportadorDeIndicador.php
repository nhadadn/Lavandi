<?php

namespace App\Exportacion;

use App\Indicadores\Granularidad;
use App\Indicadores\Rango;
use BackedEnum;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use League\Csv\EscapeFormula;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;

/**
 * Lo que comparten los exportadores de los reportes del panel: un exportador de Filament por reporte (ADR-0003;
 * openspec/changes/exportacion/design.md, "Decisions").
 *
 * - Las filas salen de la misma consulta del indicador que usa el panel, con el mismo rango: no hay una segunda fuente
 *   de cifras (exportacion-E01 y E03). Las columnas son las que muestra el panel, con sus nombres.
 * - El rango y la granularidad los elige la administración en el formulario de la descarga, con los de la página por
 *   omisión. El rango se ajusta a periodos completos como en el panel (App\Indicadores\Rango).
 * - Un reporte de indicadores no se calcula por registros de Eloquent: el trabajo de la cola
 *   (EscribirArchivoDelReporte) escribe el CSV con filasDelArchivo(), y Filament arma el XLSX a partir de él, con las
 *   cifras como números (makeXlsxRow()).
 *
 * Cada reporte da su nombre, sus columnas, cuáles son números y sus filas.
 */
abstract class ExportadorDeIndicador extends Exporter
{
    /** El modelo de la exportación: los reportes no exportan registros de un modelo del negocio. */
    protected static ?string $model = Exportacion::class;

    /** El nombre del reporte en el archivo, en minúsculas y con guiones: "pedidos-totales". */
    public const NOMBRE = '';

    /**
     * El nombre del reporte en el botón y en el aviso: "Pedidos totales".
     */
    abstract public static function titulo(): string;

    /**
     * Las columnas del archivo, en orden, por su nombre en las filas, con la etiqueta que les da el panel.
     *
     * @return array<string, string>
     */
    abstract protected static function columnas(): array;

    /**
     * Las columnas que llevan números.
     *
     * @return list<string>
     */
    abstract protected static function columnasNumericas(): array;

    /**
     * Las filas del reporte para el rango, cada una con sus columnas por nombre, de la consulta del indicador.
     *
     * @return iterable<array<string, mixed>>
     */
    abstract protected function filas(Rango $rango): iterable;

    /**
     * El nombre de la acción de descarga en la página de indicadores: "descargarPedidosTotales".
     */
    public static function nombreDeLaAccion(): string
    {
        return 'descargar'.Str::studly(static::NOMBRE);
    }

    /**
     * @return array<ExportColumn>
     */
    public static function getColumns(): array
    {
        return array_map(
            fn (string $nombre, string $etiqueta): ExportColumn => ExportColumn::make($nombre)->label($etiqueta),
            array_keys(static::columnas()),
            static::columnas(),
        );
    }

    /**
     * El rango y la granularidad de la descarga, con los de la página de indicadores por omisión.
     *
     * @return array<Component>
     */
    public static function getOptionsFormComponents(): array
    {
        return [
            DatePicker::make('desde')
                ->label('Desde')
                ->required()
                ->default(fn (mixed $livewire): string => self::filtroDeLaPagina($livewire, 'desde') ?? Rango::desdePorOmision()->toDateString()),
            // Las dos fechas van incluidas y no hay tope de duración (exportacion-E04 y E05); al revés, no (E06).
            DatePicker::make('hasta')
                ->label('Hasta')
                ->required()
                ->afterOrEqual('desde')
                ->validationMessages(['after_or_equal' => 'La fecha de fin no puede ser anterior a la de inicio.'])
                ->default(fn (mixed $livewire): string => self::filtroDeLaPagina($livewire, 'hasta') ?? Rango::hoy()->toDateString()),
            Select::make('granularidad')
                ->label('Ver por')
                ->options(Granularidad::opciones())
                ->required()
                ->selectablePlaceholder(false)
                ->default(fn (mixed $livewire): string => self::filtroDeLaPagina($livewire, 'granularidad') ?? Granularidad::Dia->value),
        ];
    }

    /**
     * El rango de la descarga, como lo arma el panel con los mismos filtros.
     */
    public function rango(): Rango
    {
        return Rango::deLosFiltros($this->options);
    }

    /**
     * Las filas del archivo, sin el encabezado, con sus celdas en el orden de las columnas.
     *
     * @return list<list<bool|float|int|string|null>>
     */
    public function filasDelArchivo(): array
    {
        $numericas = array_flip(static::columnasNumericas());
        $nombres = array_keys($this->columnMap);
        $escape = new EscapeFormula;
        $filas = [];

        foreach ($this->filas($this->rango()) as $fila) {
            $filas[] = array_map(function (string $nombre) use ($fila, $numericas, $escape): bool|float|int|string|null {
                $celda = self::celda($fila[$nombre] ?? null);

                // Solo las celdas de texto llevan el escape de fórmulas; los números se quedan como números (E08 y E09).
                return isset($numericas[$nombre]) ? $celda : $escape->escapeRecord([$celda])[0];
            }, $nombres);
        }

        return $filas;
    }

    /**
     * El nombre del archivo, sin la extensión: el reporte y sus dos días, "pedidos-totales-2026-11-30-al-2026-12-06".
     */
    public function getFileName(Export $export): string
    {
        $rango = $this->rango();

        return sprintf('%s-%s-al-%s', static::NOMBRE, $rango->desde->toDateString(), $rango->hasta->toDateString());
    }

    public function getFileDisk(): string
    {
        return (string) config('exportacion.disco');
    }

    public static function getCompletedNotificationTitle(Export $export): string
    {
        $exportador = $export->exporter;

        return is_a($exportador, self::class, true)
            ? 'El archivo de '.mb_lcfirst($exportador::titulo()).' está listo'
            : 'El archivo está listo';
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $rango = Rango::deLosFiltros($export->getOptions());
        $filas = (int) $export->successful_rows;

        return $rango->descripcion().'. '.($filas === 1 ? '1 fila' : Number::format($filas).' filas').'. Descárgalo en CSV o en Excel.';
    }

    /**
     * El encabezado del XLSX: las etiquetas de las columnas, como texto.
     *
     * @param  array<mixed>  $values
     */
    public function makeXlsxHeaderRow(array $values, ?Style $style = null): Row
    {
        return new Row(array_map(fn (mixed $valor): Cell => new StringCell((string) $valor, null), array_values($values)), $style);
    }

    /**
     * Una fila del XLSX, que Filament lee del CSV: las columnas de números como números (exportacion-E02 y E09), y las
     * demás como celdas de texto, nunca de fórmula, con el texto como se capturó: sin el apóstrofo del escape del CSV
     * (exportacion-E08). Una celda de texto del XLSX no se calcula, así que no necesita el apóstrofo.
     *
     * @param  array<mixed>  $values
     */
    public function makeXlsxRow(array $values, ?Style $style = null): Row
    {
        $numericas = array_flip(static::columnasNumericas());
        $nombres = array_keys($this->columnMap);
        $escape = new EscapeFormula;
        $celdas = [];

        foreach (array_values($values) as $posicion => $valor) {
            $texto = (string) $valor;
            $esNumero = isset($numericas[$nombres[$posicion] ?? '']) && is_numeric($texto);

            $celdas[] = match (true) {
                $texto === '' => Cell::fromValue(null),
                $esNumero => Cell::fromValue(self::numero($texto)),
                default => new StringCell((string) $escape->unescapeRecord([$texto])[0], null),
            };
        }

        return new Row($celdas, $style);
    }

    /**
     * Una celda del CSV: los números y el texto como vienen de la consulta; un color del semáforo o una tendencia, por
     * su texto.
     */
    private static function celda(mixed $valor): bool|float|int|string|null
    {
        return match (true) {
            $valor === null, is_bool($valor), is_int($valor), is_float($valor), is_string($valor) => $valor,
            $valor instanceof HasLabel => (string) $valor->getLabel(),
            $valor instanceof BackedEnum => (string) $valor->value,
            default => (string) $valor,
        };
    }

    private static function numero(string $texto): int|float
    {
        return preg_match('/^-?\d+$/', $texto) === 1 ? (int) $texto : (float) $texto;
    }

    /**
     * Un filtro de la página de indicadores, si la descarga se pide desde ella.
     */
    private static function filtroDeLaPagina(mixed $livewire, string $filtro): ?string
    {
        $valor = is_object($livewire) && property_exists($livewire, 'filters') ? ($livewire->filters[$filtro] ?? null) : null;

        return is_string($valor) && $valor !== '' ? $valor : null;
    }
}
