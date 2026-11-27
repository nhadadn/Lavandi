<?php

namespace App\Filament\Widgets\Indicadores;

use App\Filament\Widgets\Indicadores\Concerns\EsDeIndicadores;
use App\Indicadores\EntregasPorRepartidor;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Las entregas completadas por cada repartidor en el rango, en una tabla que los pone lado a lado
 * (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5, "Tabla comparativa"; openspec/changes/indicadores/design.md, lectura
 * 5; indicadores-E15). Las cifras salen de App\Indicadores\EntregasPorRepartidor.
 */
class EntregasPorRepartidorTabla extends TableWidget
{
    use EsDeIndicadores;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Entregas completadas por repartidor')
            ->description(fn (): string => $this->rango()->dias().'. Cuenta cada pedido que el repartidor marcó Entregado.')
            ->records(fn (): array => app(EntregasPorRepartidor::class)->delRango($this->rango()))
            ->columns([
                TextColumn::make('repartidor')->label('Repartidor'),
                TextColumn::make('plaza')->label('Plaza')->placeholder('Sin plaza'),
                TextColumn::make('entregas')->label('Entregas completadas')->numeric(),
            ])
            ->emptyStateHeading('No hay repartidores dados de alta.')
            ->paginated(false)
            ->poll(fn (): ?string => $this->getPollingInterval());
    }
}
