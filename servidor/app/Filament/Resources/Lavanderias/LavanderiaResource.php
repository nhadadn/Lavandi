<?php

namespace App\Filament\Resources\Lavanderias;

use App\Autorizacion\Autorizacion;
use App\Filament\Resources\Lavanderias\Pages\EditLavanderia;
use App\Filament\Resources\Lavanderias\Pages\ListLavanderias;
use App\Filament\Resources\Lavanderias\Schemas\LavanderiaForm;
use App\Filament\Resources\Lavanderias\Tables\LavanderiasTable;
use App\Models\Lavanderia;
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
 * La gestión de lavanderías del panel de administración (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #1;
 * ADR-0003; openspec/changes/alta-de-lavanderias/, tareas 3.1 a 3.3): la lista con su estado y su plaza, la edición de
 * sus datos de registro con las validaciones de la app, y la suspensión, su levantamiento y la baja como acciones.
 *
 * - No tiene alta: espera DD-053 (alta-lavanderias-E11).
 * - No borra: la baja deja la fila con estado baja, y el historial se queda (alta-lavanderias-E17).
 * - Las lavanderías salen del componente único de autorización (Autorizacion::lavanderiasDelPanel()), no de una
 *   Policy de Filament (ADR-0019, consecuencias). La URL las nombra por su id_publico.
 */
class LavanderiaResource extends Resource
{
    protected static ?string $model = Lavanderia::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $recordTitleAttribute = 'nombre';

    protected static ?string $modelLabel = 'lavandería';

    protected static ?string $pluralModelLabel = 'lavanderías';

    protected static ?string $slug = 'lavanderias';

    public static function form(Schema $schema): Schema
    {
        return LavanderiaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LavanderiasTable::configure($table);
    }

    /**
     * @return Builder<Lavanderia>
     */
    public static function getEloquentQuery(): Builder
    {
        return app(Autorizacion::class)->lavanderiasDelPanel(self::cuenta())->with('plaza');
    }

    public static function canAccess(): bool
    {
        return app(Autorizacion::class)->puedeEntrarAlPanel(self::cuenta());
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLavanderias::route('/'),
            'edit' => EditLavanderia::route('/{record}/edit'),
        ];
    }

    private static function cuenta(): Usuario
    {
        /** @var Usuario */
        return Filament::auth()->user();
    }
}
