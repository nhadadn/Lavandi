<?php

namespace App\Filament\Widgets\Finanzas;

use App\Filament\Widgets\Finanzas\Concerns\EsDeReportesFinancieros;
use App\Finanzas\Ingresos;
use App\Finanzas\Pesos;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Los ingresos de cada periodo del rango, por día, semana o mes (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D
 * #6, "Ingresos por periodo"; administracion-E17): cuántos pagos confirmó la pasarela y cuánto suman. Las cifras salen
 * de App\Finanzas\Ingresos, la misma consulta del indicador de ingresos brutos (indicadores-E16).
 */
class IngresosPorPeriodoTabla extends TableWidget
{
    use EsDeReportesFinancieros;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Ingresos por periodo')
            ->description(fn (): string => $this->rango()->descripcion())
            ->records(fn (): array => app(Ingresos::class)->porPeriodo($this->rango()))
            ->columns([
                TextColumn::make('periodo')->label('Periodo'),
                TextColumn::make('pagos')->label('Pagos confirmados')->numeric(),
                TextColumn::make('ingresos')
                    ->label('Ingresos')
                    ->formatStateUsing(fn (string $state): string => Pesos::texto($state)),
            ])
            ->paginated(false);
    }
}
