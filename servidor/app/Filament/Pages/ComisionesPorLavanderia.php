<?php

namespace App\Filament\Pages;

use App\Autorizacion\Autorizacion;
use App\Comisiones\PorcentajesDeComision;
use App\Models\Lavanderia;
use App\Models\Usuario;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * El porcentaje de comisión propio de cada lavandería, en el panel (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo
 * D #5, "Porcentaje de comisión por socio o global"; openspec/changes/administracion/, tarea 3.1; administracion-E12 y
 * E13).
 *
 * Una fila por lavandería, de todas las plazas, con su porcentaje propio y el que le aplica: el propio si tiene; si no,
 * el global, que es uno para todas las plazas (design.md, lectura 2 y Open Questions 4). Los botones fijan el propio o lo quitan, y entonces le vuelve a aplicar
 * el global. Las lavanderías salen del componente de autorización; las reglas y la vigencia, de
 * App\Comisiones\PorcentajesDeComision.
 *
 * El porcentaje que la plataforma le retiene a un repartidor espera DD-019 (administracion-E14).
 */
class ComisionesPorLavanderia extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|UnitEnum|null $navigationGroup = 'Comisiones';

    protected static ?string $navigationLabel = 'Comisión por lavandería';

    protected static ?string $title = 'Comisión por lavandería';

    protected static ?string $slug = 'comision-por-lavanderia';

    public static function canAccess(): bool
    {
        return app(Autorizacion::class)->puedeEntrarAlPanel(self::cuenta());
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => app(Autorizacion::class)->lavanderiasDelPanel(self::cuenta())->with('plaza'))
            ->columns([
                TextColumn::make('nombre')->label('Lavandería')->searchable()->sortable(),
                TextColumn::make('plaza.nombre')->label('Plaza')->sortable(),
                TextColumn::make('propio')
                    ->label('Porcentaje propio')
                    ->state(fn (Lavanderia $record): string => ($propio = self::porcentajes()->propioVigente($record)) === null
                        ? 'Sin porcentaje propio'
                        : "{$propio->porcentaje} %"),
                TextColumn::make('aplica')
                    ->label('Porcentaje que aplica')
                    ->state(fn (Lavanderia $record): string => self::queAplica($record)),
            ])
            ->defaultSort('nombre')
            ->recordActions([
                Action::make('fijarPropio')
                    ->label('Fijar porcentaje propio')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->modalHeading('Porcentaje propio de la lavandería')
                    ->modalDescription('Le aplica a esta lavandería en lugar del global. El anterior queda como historial.')
                    ->modalSubmitActionLabel('Guardar')
                    ->fillForm(fn (Lavanderia $record): array => ['porcentaje' => self::porcentajes()->propioVigente($record)?->porcentaje])
                    ->schema([ComisionGlobal::campoDelPorcentaje()])
                    ->authorize(fn (): bool => self::canAccess())
                    ->action(function (Lavanderia $record, array $data, Action $action): void {
                        self::porcentajes()->fijarPropio($record, $data['porcentaje']);
                        $action->success();
                    })
                    ->successNotificationTitle('Se guardó el porcentaje propio.'),
                Action::make('quitarPropio')
                    ->label('Quitar porcentaje propio')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Quitar el porcentaje propio')
                    ->modalDescription('A la lavandería le vuelve a aplicar el porcentaje global. El propio queda como historial.')
                    ->modalSubmitActionLabel('Quitar')
                    ->visible(fn (Lavanderia $record): bool => self::porcentajes()->propioVigente($record) !== null)
                    ->authorize(fn (): bool => self::canAccess())
                    ->action(function (Lavanderia $record, Action $action): void {
                        self::porcentajes()->quitarPropio($record);
                        $action->success();
                    })
                    ->successNotificationTitle('Se quitó el porcentaje propio.'),
            ])
            ->emptyStateHeading('No hay lavanderías.');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    /**
     * El porcentaje que le aplica a la lavandería, con de dónde sale.
     */
    private static function queAplica(Lavanderia $lavanderia): string
    {
        $aplica = self::porcentajes()->queAplica($lavanderia);

        return match (true) {
            $aplica === null => 'Sin porcentaje',
            $aplica->id_lavanderias !== null => "{$aplica->porcentaje} % (propio)",
            default => "{$aplica->porcentaje} % (global)",
        };
    }

    private static function porcentajes(): PorcentajesDeComision
    {
        return app(PorcentajesDeComision::class);
    }

    private static function cuenta(): Usuario
    {
        /** @var Usuario */
        return Filament::auth()->user();
    }
}
