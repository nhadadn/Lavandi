<?php

namespace App\Filament\Resources\Repartidores;

use App\Autorizacion\Autorizacion;
use App\Filament\Resources\Repartidores\Pages\EditRepartidor;
use App\Filament\Resources\Repartidores\Pages\ListRepartidores;
use App\Filament\Resources\Repartidores\Schemas\RepartidorForm;
use App\Filament\Resources\Repartidores\Tables\RepartidoresTable;
use App\Models\Repartidor;
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
 * La gestión de repartidores del panel de administración (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #2,
 * "Alta, baja, suspensión y edición de socios repartidores"; ADR-0003; openspec/changes/repartidor/, tarea 1.2): la
 * lista con su estado y su plaza, el alta en una plaza del registro pendiente, la suspensión, su levantamiento y la baja
 * como acciones, y la edición de sus cinco datos con las reglas del registro.
 *
 * - No tiene alta desde cero: el alta autoriza un registro que ya existe (design.md, lectura 1).
 * - No borra: la baja deja la fila con estado baja y en borrado suave, y sus entregas se quedan (repartidor-E07).
 * - Los repartidores salen del componente único de autorización (Autorizacion::repartidoresDelPanel()), no de una Policy
 *   de Filament (ADR-0019). La URL los nombra por su id_publico.
 */
class RepartidorResource extends Resource
{
    protected static ?string $model = Repartidor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?string $recordTitleAttribute = 'tipo_vehiculo';

    protected static ?string $modelLabel = 'repartidor';

    protected static ?string $pluralModelLabel = 'repartidores';

    protected static ?string $slug = 'repartidores';

    public static function form(Schema $schema): Schema
    {
        return RepartidorForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RepartidoresTable::configure($table);
    }

    /**
     * @return Builder<Repartidor>
     */
    public static function getEloquentQuery(): Builder
    {
        return app(Autorizacion::class)->repartidoresDelPanel(self::cuenta())->with(['cuenta', 'plaza']);
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

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getRecordTitle(?Model $record): string
    {
        /** @var Repartidor|null $record */
        return $record?->cuenta?->getFilamentName() ?? 'Repartidor';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRepartidores::route('/'),
            'edit' => EditRepartidor::route('/{record}/edit'),
        ];
    }

    private static function cuenta(): Usuario
    {
        /** @var Usuario */
        return Filament::auth()->user();
    }
}
