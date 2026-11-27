<?php

namespace App\Filament\Resources\LogosPrensa;

use App\Autorizacion\Autorizacion;
use App\Filament\Resources\LogosPrensa\Pages\CreateLogoPrensa;
use App\Filament\Resources\LogosPrensa\Pages\EditLogoPrensa;
use App\Filament\Resources\LogosPrensa\Pages\ListLogosPrensa;
use App\Filament\Resources\LogosPrensa\Schemas\LogoPrensaForm;
use App\Filament\Resources\LogosPrensa\Tables\LogosPrensaTable;
use App\Models\LogoPrensa;
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
 * Los logos de prensa de la portada en el panel (NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2, sección 10: "Logos de
 * medios o menciones, activables conforme se obtengan"; ADR-0011; openspec/changes/landing/, tarea 4.2): la lista con su
 * interruptor de activo y su orden, que se cambia arrastrando, el alta y la edición. No se borran: ningún escenario lo
 * pide, y un logo que ya no debe salir se retira desactivándolo (landing-E17).
 *
 * Solo la administración entra, crea, edita, activa u ordena (landing-E19): lo decide el componente único de
 * autorización (Autorizacion::puedeEditarLaPortada() y logosPrensaDelPanel()), no una Policy de Filament (ADR-0019,
 * consecuencias), además de que el panel ya no deja entrar a otra cuenta.
 */
class LogoPrensaResource extends Resource
{
    protected static ?string $model = LogoPrensa::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static ?string $recordTitleAttribute = 'nombre';

    protected static ?string $modelLabel = 'logo de prensa';

    protected static ?string $pluralModelLabel = 'logos de prensa';

    protected static ?string $navigationLabel = 'Prensa de la portada';

    protected static ?string $slug = 'logos-prensa';

    public static function form(Schema $schema): Schema
    {
        return LogoPrensaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LogosPrensaTable::configure($table);
    }

    /**
     * @return Builder<LogoPrensa>
     */
    public static function getEloquentQuery(): Builder
    {
        return app(Autorizacion::class)->logosPrensaDelPanel(self::cuenta());
    }

    public static function canAccess(): bool
    {
        return self::puedeEditar();
    }

    public static function canViewAny(): bool
    {
        return self::puedeEditar();
    }

    public static function canCreate(): bool
    {
        return self::puedeEditar();
    }

    public static function canEdit(Model $record): bool
    {
        return self::puedeEditar();
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canReorder(): bool
    {
        return self::puedeEditar();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLogosPrensa::route('/'),
            'create' => CreateLogoPrensa::route('/create'),
            'edit' => EditLogoPrensa::route('/{record}/edit'),
        ];
    }

    private static function puedeEditar(): bool
    {
        $cuenta = Filament::auth()->user();

        return $cuenta instanceof Usuario && app(Autorizacion::class)->puedeEditarLaPortada($cuenta);
    }

    private static function cuenta(): Usuario
    {
        /** @var Usuario */
        return Filament::auth()->user();
    }
}
