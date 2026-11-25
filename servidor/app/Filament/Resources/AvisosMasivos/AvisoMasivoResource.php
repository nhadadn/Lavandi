<?php

namespace App\Filament\Resources\AvisosMasivos;

use App\Autorizacion\Autorizacion;
use App\Filament\Resources\AvisosMasivos\Pages\CreateAvisoMasivo;
use App\Filament\Resources\AvisosMasivos\Pages\ListAvisosMasivos;
use App\Filament\Resources\AvisosMasivos\Schemas\AvisoMasivoForm;
use App\Filament\Resources\AvisosMasivos\Tables\AvisosMasivosTable;
use App\Models\AvisoMasivo;
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
 * Los avisos masivos del panel (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #7; openspec/changes/
 * administracion/, tarea 5.1; administracion-E23): la lista de los avisos enviados y el alta, que escribe el aviso con
 * su título y su texto y lo manda por FCM a todas las cuentas con un teléfono registrado.
 *
 * Un aviso enviado no se edita ni se borra: ya salió a los teléfonos y queda como historial. El aviso a un segmento
 * espera DD-113 (administracion-E24).
 *
 * Solo la administración entra, ve y manda: lo decide el componente único de autorización
 * (Autorizacion::puedeMandarAvisosMasivos() y avisosMasivosDelPanel(); ADR-0019).
 */
class AvisoMasivoResource extends Resource
{
    protected static ?string $model = AvisoMasivo::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?string $recordTitleAttribute = 'titulo';

    protected static ?string $modelLabel = 'aviso masivo';

    protected static ?string $pluralModelLabel = 'avisos masivos';

    protected static ?string $navigationLabel = 'Avisos masivos';

    protected static ?string $slug = 'avisos-masivos';

    public static function form(Schema $schema): Schema
    {
        return AvisoMasivoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AvisosMasivosTable::configure($table);
    }

    /**
     * @return Builder<AvisoMasivo>
     */
    public static function getEloquentQuery(): Builder
    {
        return app(Autorizacion::class)->avisosMasivosDelPanel(self::cuenta());
    }

    public static function canAccess(): bool
    {
        return self::puedeMandar();
    }

    public static function canViewAny(): bool
    {
        return self::puedeMandar();
    }

    public static function canCreate(): bool
    {
        return self::puedeMandar();
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
            'index' => ListAvisosMasivos::route('/'),
            'create' => CreateAvisoMasivo::route('/create'),
        ];
    }

    private static function puedeMandar(): bool
    {
        $cuenta = Filament::auth()->user();

        return $cuenta instanceof Usuario && app(Autorizacion::class)->puedeMandarAvisosMasivos($cuenta);
    }

    private static function cuenta(): Usuario
    {
        /** @var Usuario */
        return Filament::auth()->user();
    }
}
