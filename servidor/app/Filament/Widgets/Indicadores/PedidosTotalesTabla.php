<?php

namespace App\Filament\Widgets\Indicadores;

use App\Filament\Widgets\Indicadores\Concerns\EsDeIndicadores;
use App\Indicadores\PedidosTotales;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * La tabla comparativa de los pedidos totales: cada periodo del rango junto al anterior (NSV-2026-001-v8.pdf:22:
 * ANEXO A, Sección A.5, "Gráfica de barras y tabla comparativa"; openspec/changes/indicadores/design.md, lectura 5;
 * indicadores-E01 y E02). Las cifras son las de la gráfica: las dos leen App\Indicadores\PedidosTotales.
 */
class PedidosTotalesTabla extends TableWidget
{
    use EsDeIndicadores;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Pedidos totales, cada periodo junto al anterior')
            ->description(fn (): string => $this->rango()->descripcion())
            ->records(fn (): array => app(PedidosTotales::class)->porPeriodo($this->rango()))
            ->columns([
                TextColumn::make('periodo')->label('Periodo'),
                TextColumn::make('pedidos')->label('Pedidos')->numeric(),
                TextColumn::make('periodo_anterior')->label('Periodo anterior'),
                TextColumn::make('pedidos_anterior')->label('Pedidos del periodo anterior')->numeric(),
            ])
            ->paginated(false)
            ->poll(fn (): ?string => $this->getPollingInterval());
    }
}
