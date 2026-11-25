<?php

namespace App\Filament\Resources\Usuarios\Schemas;

use App\Filament\Resources\Usuarios\HistorialDelUsuario;
use App\Filament\Resources\Usuarios\Tables\UsuariosTable;
use App\Models\Usuario;
use Filament\Facades\Filament;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * La ficha del usuario: sus datos de cuenta y su historial de pedidos, con su folio, su fecha, su lavandería, su estado
 * y su monto (administracion-E01). Es de solo lectura. Las filas del historial las arma App\Filament\Resources\Usuarios\
 * HistorialDelUsuario, que lee los pedidos del componente de autorización.
 */
class UsuarioInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Cuenta')
                ->columns(2)
                ->schema([
                    TextEntry::make('correo')->label('Correo'),
                    TextEntry::make('nombre')->label('Nombre')->placeholder('Sin nombre'),
                    TextEntry::make('telefono')->label('Teléfono')->placeholder('Sin teléfono'),
                    TextEntry::make('estado')
                        ->label('Estado')
                        ->badge()
                        ->formatStateUsing(fn (string $state): string => UsuariosTable::ESTADOS[$state] ?? $state)
                        ->color(fn (string $state): string => $state === Usuario::BLOQUEADO ? 'danger' : 'success'),
                ]),
            Section::make('Historial de pedidos')
                ->schema([
                    RepeatableEntry::make('historial')
                        ->hiddenLabel()
                        ->state(fn (Usuario $record): array => app(HistorialDelUsuario::class)->de(self::cuenta(), $record))
                        ->placeholder('Sin pedidos.')
                        ->table([
                            TableColumn::make('Folio'),
                            TableColumn::make('Fecha'),
                            TableColumn::make('Lavandería'),
                            TableColumn::make('Estado'),
                            TableColumn::make('Monto'),
                        ])
                        ->schema([
                            TextEntry::make('folio'),
                            TextEntry::make('fecha'),
                            TextEntry::make('lavanderia'),
                            TextEntry::make('estado'),
                            TextEntry::make('monto')->placeholder('Sin monto'),
                        ]),
                ]),
        ]);
    }

    private static function cuenta(): Usuario
    {
        /** @var Usuario */
        return Filament::auth()->user();
    }
}
