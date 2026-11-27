<?php

namespace App\Filament\Widgets\Indicadores;

use App\Filament\Widgets\Indicadores\Concerns\EsDeIndicadores;
use App\Indicadores\MontoPromedio;
use App\Indicadores\Tendencia;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

/**
 * El monto promedio por pedido, como indicador destacado con su tendencia frente al periodo anterior de la misma
 * duración (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5, "Indicador destacado con tendencia"; indicadores-E09). Las
 * cifras salen de App\Indicadores\MontoPromedio.
 *
 * La tendencia va con su texto y la diferencia en pesos, no solo con el color y la flecha.
 */
class MontoPromedioIndicador extends StatsOverviewWidget
{
    use EsDeIndicadores;

    protected function getHeading(): string
    {
        return 'Monto promedio por pedido';
    }

    /**
     * Los días del rango, que son el periodo; el anterior son los mismos días justo antes.
     */
    protected function getDescription(): string
    {
        $anterior = $this->rango()->anteriorDeLaMismaDuracion();

        return $this->rango()->dias().'. El periodo anterior es '.mb_lcfirst($anterior->dias()).'.';
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $cifras = app(MontoPromedio::class)->delRango($this->rango());
        $stat = Stat::make('Monto promedio por pedido', $cifras['promedio'] === null ? 'Sin monto' : self::pesos($cifras['promedio']));

        if ($cifras['promedio'] === null) {
            return [$stat->description('No hay pedidos con monto en el periodo.')->color('gray')];
        }

        if ($cifras['tendencia'] === null) {
            return [$stat->description('Sin pedidos con monto en el periodo anterior para comparar.')->color('gray')];
        }

        $tendencia = $cifras['tendencia'];
        $anterior = self::pesos((string) $cifras['promedio_anterior']);
        $diferencia = self::pesos(ltrim((string) $cifras['diferencia'], '-'));

        return [
            $stat
                ->description(match ($tendencia) {
                    Tendencia::Subio => "Subió {$diferencia} frente al periodo anterior ({$anterior})",
                    Tendencia::Bajo => "Bajó {$diferencia} frente al periodo anterior ({$anterior})",
                    Tendencia::Igual => "Igual que en el periodo anterior ({$anterior})",
                })
                ->descriptionIcon(match ($tendencia) {
                    Tendencia::Subio => Heroicon::ArrowTrendingUp,
                    Tendencia::Bajo => Heroicon::ArrowTrendingDown,
                    Tendencia::Igual => Heroicon::Minus,
                })
                ->color($tendencia->getColor()),
        ];
    }

    /**
     * Un monto en pesos, como en la ficha del usuario: "$250.00".
     */
    private static function pesos(string $monto): string
    {
        return (string) Number::currency((float) $monto, in: 'MXN', locale: 'es_MX');
    }
}
