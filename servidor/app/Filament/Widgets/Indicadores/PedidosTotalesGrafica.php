<?php

namespace App\Filament\Widgets\Indicadores;

use App\Filament\Widgets\Indicadores\Concerns\EsDeIndicadores;
use App\Indicadores\PedidosTotales;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Los pedidos totales de cada periodo del rango, en barras (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5, categoría
 * Operaciones; indicadores-E01 a E03). Las cifras salen de App\Indicadores\PedidosTotales; la tabla comparativa es
 * PedidosTotalesTabla.
 *
 * Una sola serie: sin leyenda, porque el título la nombra.
 */
class PedidosTotalesGrafica extends ChartWidget
{
    use EsDeIndicadores;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '300px';

    public function getHeading(): string
    {
        return 'Pedidos totales';
    }

    public function getDescription(): string|Htmlable|null
    {
        return $this->rango()->descripcion();
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $filas = app(PedidosTotales::class)->porPeriodo($this->rango());

        return [
            'labels' => array_values(array_column($filas, 'periodo')),
            'datasets' => [
                [
                    'label' => 'Pedidos',
                    'data' => array_values(array_column($filas, 'pedidos')),
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
            'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
        ];
    }
}
