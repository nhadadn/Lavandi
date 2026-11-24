<?php

namespace App\Filament\Resources\Repartidores\Tables;

use App\Filament\Resources\Repartidores\Actions\AccionesDeEstado;
use App\Models\Repartidor;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * La lista de repartidores del panel, con sus datos del registro, su estado y su plaza, de todas las plazas y en
 * cualquier estado (repartidor-E05 a E09). Sin acciones de borrado: la baja no borra (repartidor-E07).
 */
class RepartidoresTable
{
    /** Cómo se lee cada estado de repartidores.estado en el panel. */
    public const ESTADOS = [
        Repartidor::PENDIENTE => 'Pendiente',
        Repartidor::ACTIVO => 'Activo',
        Repartidor::SUSPENDIDO => 'Suspendido',
        Repartidor::BAJA => 'Dado de baja',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('cuenta.nombre')->label('Nombre')->searchable()->placeholder('Sin nombre'),
                TextColumn::make('cuenta.correo')->label('Correo')->searchable(),
                TextColumn::make('cuenta.telefono')->label('Teléfono'),
                TextColumn::make('tipo_vehiculo')->label('Vehículo'),
                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::ESTADOS[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        Repartidor::ACTIVO => 'success',
                        Repartidor::PENDIENTE => 'warning',
                        Repartidor::SUSPENDIDO => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('plaza.nombre')->label('Plaza')->placeholder('Sin plaza'),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make()->label('Editar'),
                ...AccionesDeEstado::todas(),
            ]);
    }
}
