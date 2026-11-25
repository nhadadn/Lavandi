<?php

namespace App\Filament\Widgets\Indicadores;

use App\Filament\Widgets\Indicadores\Concerns\EsDeIndicadores;
use App\Finanzas\Ingresos;
use App\Finanzas\Pesos;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Los ingresos brutos de cada periodo del rango, en barras (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5, categoría
 * Financiero; indicadores-E16): lo cobrado con pago confirmado por la pasarela, sin descontar nada. Las cifras salen de
 * App\Finanzas\Ingresos, la misma consulta del reporte financiero de ingresos (administracion-E17), así que cada barra
 * es la cifra de ese reporte para su periodo.
 *
 * El A.5 pide barras agrupadas; por qué se agrupan lo decide EL CLIENTE (indicadores-E17, BR-31, DD-115). Mientras, es
 * una sola serie, una barra por periodo, sin leyenda: el título la nombra. Las cifras van también en texto.
 */
class IngresosBrutosGrafica extends ChartWidget
{
    use EsDeIndicadores;

    protected ?string $maxHeight = '300px';

    public function getHeading(): string
    {
        return 'Ingresos brutos';
    }

    public function getDescription(): string|Htmlable|null
    {
        return 'Lo cobrado con pago confirmado por la pasarela, en pesos. '.$this->rango()->descripcion();
    }

    public function getChartAssistiveContent(): string
    {
        return implode('. ', array_map(
            fn (array $fila): string => "{$fila['periodo']}: ".Pesos::texto($fila['ingresos']),
            $this->filas(),
        )).'.';
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * Los montos van a la gráfica como números, solo para dibujarla; la cifra exacta, con sus dos decimales, es la del
     * reporte y la de la descarga.
     *
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $filas = $this->filas();

        return [
            'labels' => array_values(array_column($filas, 'periodo')),
            'datasets' => [
                [
                    'label' => 'Ingresos brutos',
                    'data' => array_values(array_map(fn (array $fila): float => (float) $fila['ingresos'], $filas)),
                    'borderRadius' => 4,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['y' => ['beginAtZero' => true]],
        ];
    }

    /**
     * @return array<string, array{periodo: string, pagos: int, ingresos: string}>
     */
    private function filas(): array
    {
        return app(Ingresos::class)->porPeriodo($this->rango());
    }
}
