<?php

namespace App\Filament\Resources\Usuarios;

use App\Autorizacion\Autorizacion;
use App\Filament\Resources\Usuarios\Pages\ListUsuarios;
use App\Filament\Resources\Usuarios\Pages\ViewUsuario;
use App\Filament\Resources\Usuarios\Schemas\UsuarioInfolist;
use App\Filament\Resources\Usuarios\Tables\UsuariosTable;
use App\Models\Usuario;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * La gestión de usuarios del panel de administración (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #3,
 * "Consulta de usuarios activos, historial y bloqueo"; ADR-0003; openspec/changes/administracion/, tareas 1.1 y 1.3): la
 * lista de las cuentas de usuario y la ficha de cada una con su historial de pedidos, de solo lectura.
 *
 * - No da de alta, no edita y no borra: está fuera del contrato (proposal.md, "Fuera del contrato"). Borrar la cuenta lo
 *   hace el propio usuario desde la app (ADR-0005).
 * - Las cuentas salen del componente único de autorización (Autorizacion::usuariosDelPanel()), no de una Policy de
 *   Filament (ADR-0019, consecuencias). La URL las nombra por su id_publico.
 * - El filtro de usuarios activos de un periodo espera DD-111 y la tabla actividad_usuarios (administracion-E02).
 */
class UsuarioResource extends Resource
{
    protected static ?string $model = Usuario::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $recordTitleAttribute = 'correo';

    protected static ?string $modelLabel = 'usuario';

    protected static ?string $pluralModelLabel = 'usuarios';

    protected static ?string $slug = 'usuarios';

    public static function infolist(Schema $schema): Schema
    {
        return UsuarioInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsuariosTable::configure($table);
    }

    /**
     * @return Builder<Usuario>
     */
    public static function getEloquentQuery(): Builder
    {
        return app(Autorizacion::class)->usuariosDelPanel(self::cuenta());
    }

    public static function canAccess(): bool
    {
        return app(Autorizacion::class)->puedeEntrarAlPanel(self::cuenta());
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsuarios::route('/'),
            'view' => ViewUsuario::route('/{record}'),
        ];
    }

    private static function cuenta(): Usuario
    {
        /** @var Usuario */
        return Filament::auth()->user();
    }
}
