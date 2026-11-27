<?php

namespace App\Filament\Resources\Lavanderias\Tables;

use App\Filament\Resources\Lavanderias\Actions\AccionesDeEstado;
use App\Models\Lavanderia;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * La lista de lavanderías del panel, con su estado y su plaza (alta-lavanderias-E13), de todas las plazas
 * (autorizacion-E13). Sin acciones de borrado: la baja no borra (alta-lavanderias-E17).
 */
class LavanderiasTable
{
    /** Cómo se lee cada estado de lavanderias.estado en el panel. */
    public const ESTADOS = [
        Lavanderia::PENDIENTE => 'Pendiente',
        Lavanderia::ACTIVA => 'Activa',
        Lavanderia::SUSPENDIDA => 'Suspendida',
        Lavanderia::BAJA => 'Dada de baja',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')->label('Nombre')->searchable()->sortable(),
                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::ESTADOS[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        Lavanderia::ACTIVA => 'success',
                        Lavanderia::PENDIENTE => 'warning',
                        Lavanderia::SUSPENDIDA => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('plaza.nombre')->label('Plaza')->sortable(),
            ])
            ->defaultSort('nombre')
            ->recordActions([
                EditAction::make()->label('Editar'),
                ...AccionesDeEstado::todas(),
            ]);
    }
}
