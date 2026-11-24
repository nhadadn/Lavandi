<?php

namespace App\Filament\Resources\Testimonios\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

/**
 * La lista de testimonios del panel, en el orden en que salen en la portada: se reordena arrastrando y cada uno se
 * activa o se desactiva desde su interruptor (landing-E15; ADR-0011, "su interruptor de activo").
 */
class TestimoniosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->reorderable('orden')
            ->defaultSort('orden')
            ->columns([
                TextColumn::make('autor')->label('Autor')->searchable(),
                TextColumn::make('texto')->label('Testimonio')->limit(80)->wrap(),
                ToggleColumn::make('activo')->label('Activo en la portada'),
            ])
            ->recordActions([
                EditAction::make()->label('Editar'),
                DeleteAction::make()->label('Borrar'),
            ]);
    }
}
