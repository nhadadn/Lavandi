<?php

namespace App\Filament\Resources\Testimonios;

use App\Autorizacion\Autorizacion;
use App\Filament\Resources\Testimonios\Pages\CreateTestimonio;
use App\Filament\Resources\Testimonios\Pages\EditTestimonio;
use App\Filament\Resources\Testimonios\Pages\ListTestimonios;
use App\Filament\Resources\Testimonios\Schemas\TestimonioForm;
use App\Filament\Resources\Testimonios\Tables\TestimoniosTable;
use App\Models\Testimonio;
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
 * Los testimonios de la portada en el panel (NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2, sección 8; ADR-0011;
 * openspec/changes/landing/, tarea 4.1): la lista con su interruptor de activo y su orden, que se cambia arrastrando, el
 * alta, la edición y el borrado.
 *
 * Solo la administración entra, crea, edita, activa, ordena o borra (landing-E19): lo decide el componente único de
 * autorización (Autorizacion::puedeEditarLaPortada() y testimoniosDelPanel()), no una Policy de Filament (ADR-0019,
 * consecuencias), además de que el panel ya no deja entrar a otra cuenta.
 */
class TestimonioResource extends Resource
{
    protected static ?string $model = Testimonio::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftEllipsis;

    protected static ?string $recordTitleAttribute = 'autor';

    protected static ?string $modelLabel = 'testimonio';

    protected static ?string $pluralModelLabel = 'testimonios';

    protected static ?string $navigationLabel = 'Testimonios de la portada';

    protected static ?string $slug = 'testimonios';

    public static function form(Schema $schema): Schema
    {
        return TestimonioForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TestimoniosTable::configure($table);
    }

    /**
     * @return Builder<Testimonio>
     */
    public static function getEloquentQuery(): Builder
    {
        return app(Autorizacion::class)->testimoniosDelPanel(self::cuenta());
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
        return self::puedeEditar();
    }

    public static function canDeleteAny(): bool
    {
        return self::puedeEditar();
    }

    public static function canReorder(): bool
    {
        return self::puedeEditar();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTestimonios::route('/'),
            'create' => CreateTestimonio::route('/create'),
            'edit' => EditTestimonio::route('/{record}/edit'),
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
