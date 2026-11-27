<?php

namespace App\Filament\Widgets\Indicadores;

use App\Filament\Widgets\Indicadores\Concerns\EsDeIndicadores;
use App\Indicadores\RetencionPorMesDeRegistro;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Los usuarios que vuelven a pedir, por mes de registro, en una tabla de cohortes (NSV-2026-001-v8.pdf:22:ANEXO A,
 * Sección A.5, "Tabla de cohortes"; indicadores-E08): una fila por mes del rango, con las cuentas que hicieron su
 * primer pedido y el porcentaje que volvió a pedir. Las cifras salen de App\Indicadores\RetencionPorMesDeRegistro.
 */
class RetencionTabla extends TableWidget
{
    use EsDeIndicadores;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Usuarios que vuelven a pedir, por mes de registro')
            ->description('Pedidos hasta hoy de las cuentas registradas cada mes del rango.')
            ->records(fn (): array => app(RetencionPorMesDeRegistro::class)->porMes($this->rango()))
            ->columns([
                TextColumn::make('mes')->label('Mes de registro'),
                TextColumn::make('con_primer_pedido')->label('Con primer pedido')->numeric(),
                TextColumn::make('porcentaje')
                    ->label('Volvieron a pedir')
                    ->formatStateUsing(fn (?float $state): ?string => $state === null ? null : self::porcentaje($state))
                    ->placeholder('—'),
            ])
            ->paginated(false)
            ->poll(fn (): ?string => $this->getPollingInterval());
    }

    /**
     * El porcentaje con un decimal solo si lo tiene: "50 %", "33.3 %".
     */
    private static function porcentaje(float $valor): string
    {
        return rtrim(rtrim(number_format($valor, 1, '.', ''), '0'), '.').' %';
    }
}
