<?php

namespace App\Filament\Resources\AvisosMasivos\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * La lista de los avisos masivos enviados, del más reciente al más antiguo, de solo lectura: su título, su texto,
 * cuándo y quién lo mandó, y a cuántos teléfonos salió (administracion-E23). Mientras el trabajo de la cola no los
 * cuenta, dice que el aviso está en cola.
 */
class AvisosMasivosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('titulo')->label('Título')->searchable(),
                TextColumn::make('mensaje')->label('Texto')->limit(60),
                TextColumn::make('enviado_en')
                    ->label('Enviado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('usuario.correo')->label('Enviado por'),
                TextColumn::make('destinatarios')
                    ->label('Teléfonos')
                    ->numeric()
                    ->placeholder('En cola'),
            ])
            ->defaultSort('enviado_en', 'desc')
            ->emptyStateHeading('Todavía no se ha mandado ningún aviso.');
    }
}
