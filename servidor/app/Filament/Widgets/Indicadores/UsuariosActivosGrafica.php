<?php

namespace App\Filament\Widgets\Indicadores;

use App\Filament\Widgets\Indicadores\Concerns\EsDeIndicadores;
use App\Indicadores\UsuariosActivos;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Los usuarios activos de cada periodo del rango, en una línea de tendencia (NSV-2026-001-v8.pdf:22:ANEXO A, Sección
 * A.5, "Gráfica de línea de tendencia"; indicadores-E06). Las cifras salen de App\Indicadores\UsuariosActivos.
 *
 * Una sola serie: sin leyenda, porque el título la nombra. Sus cifras van también en texto.
 */
class UsuariosActivosGrafica extends ChartWidget
{
    use EsDeIndicadores;

    protected ?string $maxHeight = '300px';

    public function getHeading(): string
    {
        return 'Usuarios activos';
    }

    public function getDescription(): string|Htmlable|null
    {
        return 'Personas distintas. '.$this->rango()->descripcion();
    }

    public function getChartAssistiveContent(): string
    {
        return implode('. ', array_map(
            fn (array $fila): string => "{$fila['periodo']}: {$fila['personas']}",
            $this->filas(),
        )).'.';
    }

    protected function getType(): string
    {
        return 'line';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $filas = $this->filas();

        return [
            'labels' => array_values(array_column($filas, 'periodo')),
            'datasets' => [
                [
                    'label' => 'Personas',
                    'data' => array_values(array_column($filas, 'personas')),
                    'borderWidth' => 2,
                    'pointRadius' => 4,
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

    /**
     * @return array<string, array{periodo: string, personas: int}>
     */
    private function filas(): array
    {
        return app(UsuariosActivos::class)->porPeriodo($this->rango());
    }
}
