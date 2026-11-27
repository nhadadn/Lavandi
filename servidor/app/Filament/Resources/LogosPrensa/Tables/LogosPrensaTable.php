<?php

namespace App\Filament\Resources\LogosPrensa\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

/**
 * La lista de logos de prensa del panel, en el orden en que salen en la portada: se reordena arrastrando y cada uno se
 * activa o se desactiva desde su interruptor (landing-E17; ADR-0011, "su interruptor de activo"). Sin borrado: ningún
 * escenario lo pide, y un logo que ya no debe salir se retira desactivándolo.
 */
class LogosPrensaTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->reorderable('orden')
            ->defaultSort('orden')
            ->columns([
                TextColumn::make('nombre')->label('Medio')->searchable(),
                ToggleColumn::make('activo')->label('Activo en la portada'),
            ])
            ->recordActions([
                EditAction::make()->label('Editar'),
            ]);
    }
}
