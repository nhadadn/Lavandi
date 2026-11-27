<?php

namespace App\Filament\Widgets\Finanzas;

use App\Filament\Widgets\Finanzas\Concerns\EsDeReportesFinancieros;
use App\Finanzas\Ingresos;
use App\Finanzas\Pesos;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

/**
 * Los ingresos de todo el periodo que eligió la administración: cuánto suman los pagos que confirmó la pasarela y
 * cuántos son (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #6; administracion-E17). La cifra sale de
 * App\Finanzas\Ingresos.
 */
class IngresosDelPeriodo extends StatsOverviewWidget
{
    use EsDeReportesFinancieros;

    protected function getHeading(): string
    {
        return 'Ingresos del periodo';
    }

    protected function getDescription(): string
    {
        return $this->rango()->dias().'. Lo cobrado con pago confirmado por la pasarela, por el día de la confirmación en la hora de la plaza.';
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $cifras = app(Ingresos::class)->delRango($this->rango());

        return [
            Stat::make('Ingresos', Pesos::texto($cifras['ingresos']))
                ->description($cifras['pagos'] === 1 ? '1 pago confirmado' : Number::format($cifras['pagos']).' pagos confirmados'),
        ];
    }
}
