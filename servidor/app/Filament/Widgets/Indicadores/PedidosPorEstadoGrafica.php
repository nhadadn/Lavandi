<?php

namespace App\Filament\Widgets\Indicadores;

use App\Filament\Widgets\Indicadores\Concerns\EsDeIndicadores;
use App\Indicadores\PedidosPorEstado;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Los pedidos del rango por estado, en una dona: activos y completados (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5,
 * "Gráfica de dona"; indicadores-E04). Las cifras salen de App\Indicadores\PedidosPorEstado. Los cancelados esperan
 * DD-016 (indicadores-E05).
 *
 * Dos categorías con su leyenda, y sus cifras también en texto, para no depender del color.
 */
class PedidosPorEstadoGrafica extends ChartWidget
{
    use EsDeIndicadores;

    /** Las dos primeras posiciones de la paleta categórica validada: azul y naranja. */
    private const COLORES = ['#2a78d6', '#eb6834'];

    protected ?string $maxHeight = '300px';

    public function getHeading(): string
    {
        return 'Pedidos por estado';
    }

    /**
     * Los días del rango y las cifras, visibles sin pasar el cursor por la dona. La dona no se agrupa por periodo.
     */
    public function getDescription(): string|Htmlable|null
    {
        return $this->rango()->dias().'. '.$this->getChartAssistiveContent();
    }

    public function getChartAssistiveContent(): string
    {
        $cifras = $this->cifras();

        return "Activos: {$cifras['activos']}. Completados: {$cifras['completados']}.";
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $cifras = $this->cifras();

        return [
            'labels' => ['Activos', 'Completados'],
            'datasets' => [
                [
                    'label' => 'Pedidos',
                    'data' => [$cifras['activos'], $cifras['completados']],
                    'backgroundColor' => self::COLORES,
                    'borderColor' => self::COLORES,
                    'borderWidth' => 2,
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
            'plugins' => ['legend' => ['display' => true, 'position' => 'bottom']],
            'scales' => ['x' => ['display' => false], 'y' => ['display' => false]],
        ];
    }

    /**
     * @return array{activos: int, completados: int}
     */
    private function cifras(): array
    {
        return app(PedidosPorEstado::class)->delRango($this->rango());
    }
}
