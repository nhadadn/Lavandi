<?php

namespace App\Filament\Pages;

use App\Autorizacion\Autorizacion;
use App\Cobro\PasarelaNoDisponible;
use App\Exportacion\Exportadores\ConciliacionExportador;
use App\Filament\Actions\DescargarReporte;
use App\Filament\CamposDelRango;
use App\Finanzas\ConciliacionConLaPasarela;
use App\Finanzas\Pesos;
use App\Finanzas\ResultadoDeConciliacion;
use App\Indicadores\Rango;
use App\Models\Usuario;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use UnitEnum;

/**
 * La conciliación con la pasarela, en los reportes financieros del panel (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3,
 * Módulo D #6, "conciliación con Conekta"; openspec/changes/administracion/, tareas 4.4 y 4.5; administracion-E20 y
 * E21).
 *
 * La administración elige un periodo y lo concilia: el panel lee de la pasarela sus pagos de ese periodo, los guarda y
 * muestra cada pago de la plataforma y cada movimiento de la pasarela con su resultado, conciliado o una de las tres
 * diferencias (App\Finanzas\ConciliacionConLaPasarela). Antes de conciliar, la tabla no muestra nada: sin leer la
 * pasarela, todo pago parecería un pedido sin movimiento.
 *
 * Solo la ve el rol de administración, según el componente único de autorización (ADR-0019).
 */
class Conciliacion extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = 'Reportes financieros';

    protected static ?string $navigationLabel = 'Conciliación';

    protected static ?string $title = 'Conciliación con la pasarela';

    protected static ?string $slug = 'conciliacion';

    /**
     * El periodo conciliado, con sus dos días, como lo guarda el formulario: vacío mientras no se concilia.
     *
     * @var array{desde: string, hasta: string}|null
     */
    public ?array $filters = null;

    public static function canAccess(): bool
    {
        $cuenta = Filament::auth()->user();

        return $cuenta instanceof Usuario && app(Autorizacion::class)->puedeVerLosReportesFinancieros($cuenta);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('conciliar')
                ->label('Conciliar un periodo')
                ->icon(Heroicon::OutlinedArrowPath)
                ->modalHeading('Conciliar con la pasarela')
                ->modalDescription('Se leen de la pasarela los pagos que acreditó en el periodo, con sus dos fechas, y se comparan con los que registró la plataforma.')
                ->modalSubmitActionLabel('Conciliar')
                ->fillForm(fn (): array => $this->filters ?? [
                    'desde' => Rango::desdePorOmision()->toDateString(),
                    'hasta' => Rango::hoy()->toDateString(),
                ])
                // Las dos fechas, con la regla del rango (App\Validacion\RangoDeFechas), que usarán también las rutas de
                // la API de las tareas 13.2 y 19.2 (administracion-app-E11; E38 y E60).
                ->schema([CamposDelRango::desde(), CamposDelRango::hasta()])
                ->authorize(fn (): bool => self::canAccess())
                ->action(fn (array $data) => $this->conciliar($data)),
            // La descarga del periodo conciliado, con las filas de la tabla: solo después de conciliar
            // (openspec/changes/exportacion/, exportacion-E03).
            DescargarReporte::de(ConciliacionExportador::class)
                ->label('Descargar')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->modalDescription(fn (): string => 'El archivo sale en CSV y en Excel (XLSX), con la conciliación '.mb_lcfirst($this->rango()->dias()).', como la muestra la tabla.')
                ->options(fn (): array => $this->filters ?? [])
                ->visible(fn (): bool => $this->filters !== null),
        ];
    }

    /**
     * Lee la pasarela para el periodo y deja la tabla con su conciliación. Es privada: Livewire no deja llamarla desde el
     * navegador, y solo se llega a ella por la acción "conciliar", que pregunta antes al componente de autorización.
     *
     * @param  array<string, mixed>  $datos
     */
    private function conciliar(array $datos): void
    {
        $periodo = ['desde' => (string) $datos['desde'], 'hasta' => (string) $datos['hasta']];

        try {
            app(ConciliacionConLaPasarela::class)->conciliar(Rango::deLosFiltros($periodo));
        } catch (PasarelaNoDisponible) {
            Notification::make()
                ->title('No se pudo leer la pasarela')
                ->body('La pasarela no está disponible por ahora. Intenta conciliar otra vez más tarde.')
                ->danger()
                ->send();

            return;
        }

        $this->filters = $periodo;
        $this->resetTable();

        Notification::make()->title('Se concilió el periodo')->body($this->rango()->dias().'.')->success()->send();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(fn (): ?string => $this->filters === null ? null : $this->rango()->dias())
            ->description(fn (): ?string => $this->filters === null ? null : self::resumen($this->reporte()))
            ->records(fn (): array => $this->reporte())
            ->columns([
                TextColumn::make('resultado')->label('Resultado')->badge(),
                TextColumn::make('orden')->label('Orden en la pasarela'),
                TextColumn::make('folio')->label('Folio')->placeholder('Sin pedido'),
                TextColumn::make('plaza')->label('Plaza')->placeholder('—'),
                TextColumn::make('fecha')->label('Fecha del pago'),
                TextColumn::make('monto_plataforma')
                    ->label('Monto en la plataforma')
                    ->formatStateUsing(fn (string $state): string => Pesos::texto($state))
                    ->placeholder('—'),
                TextColumn::make('monto_pasarela')
                    ->label('Monto en la pasarela')
                    ->formatStateUsing(fn (string $state): string => Pesos::texto($state))
                    ->placeholder('—'),
            ])
            ->emptyStateHeading(fn (): string => $this->filters === null
                ? 'Elige un periodo y concilia para leer los pagos de la pasarela.'
                : 'No hay pagos ni movimientos de la pasarela en el periodo.')
            ->paginated(false);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    /**
     * El periodo conciliado.
     */
    private function rango(): Rango
    {
        return Rango::deLosFiltros($this->filters);
    }

    /**
     * La conciliación del periodo con lo que ya se leyó de la pasarela; nada mientras no se concilia.
     *
     * @return array<string, array<string, mixed>>
     */
    private function reporte(): array
    {
        return $this->filters === null ? [] : app(ConciliacionConLaPasarela::class)->reporte($this->rango());
    }

    /**
     * Cuántos hay de cada resultado, en texto: "Conciliado: 3. Movimiento sin pedido: 0. …".
     *
     * @param  array<string, array{resultado: ResultadoDeConciliacion}>  $reporte
     */
    private static function resumen(array $reporte): string
    {
        $partes = [];

        foreach (ConciliacionConLaPasarela::resumen($reporte) as $valor => $cuantos) {
            $partes[] = ResultadoDeConciliacion::from($valor)->getLabel().": {$cuantos}.";
        }

        return implode(' ', $partes);
    }
}
