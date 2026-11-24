<?php

namespace App\Filament\Pages;

use App\Autorizacion\Autorizacion;
use App\Exportacion\Exportadores\IngresosExportador;
use App\Filament\Actions\DescargarReporte;
use App\Filament\Widgets\Finanzas\IngresosDelPeriodo;
use App\Filament\Widgets\Finanzas\IngresosPorPeriodoTabla;
use App\Indicadores\Granularidad;
use App\Indicadores\Rango;
use App\Models\Usuario;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use UnitEnum;

/**
 * Los ingresos por periodo, en los reportes financieros del panel (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo
 * D #6, "Ingresos por periodo, comisiones y conciliación con Conekta"; openspec/changes/administracion/, tarea 4.1;
 * administracion-E17).
 *
 * - La administración elige el periodo, sus dos días incluidos, y si lo ve por día, semana o mes, como en los
 *   indicadores (App\Indicadores\Rango).
 * - Ingresos son los pagos que confirmó la pasarela, por el día de la confirmación en la hora de la plaza
 *   (App\Finanzas\Ingresos).
 * - Las comisiones del periodo esperan la base que fije EL CLIENTE (administracion-E19, DD-020) y la conciliación vive
 *   en su propia página.
 * - Solo la ve el rol de administración, según el componente único de autorización (ADR-0019).
 *
 * Es otro tablero de Filament, en /admin/ingresos, con sus filtros: el de inicio del panel no cambia.
 */
class ReporteDeIngresos extends Dashboard
{
    use HasFiltersForm;

    protected static string $routePath = '/ingresos';

    protected static ?string $title = 'Ingresos';

    protected static ?string $navigationLabel = 'Ingresos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Reportes financieros';

    public static function canAccess(): bool
    {
        $cuenta = Filament::auth()->user();

        return $cuenta instanceof Usuario && app(Autorizacion::class)->puedeVerLosReportesFinancieros($cuenta);
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->schema([
                    DatePicker::make('desde')
                        ->label('Desde')
                        ->default(fn (): string => Rango::desdePorOmision()->toDateString()),
                    DatePicker::make('hasta')
                        ->label('Hasta')
                        ->default(fn (): string => Rango::hoy()->toDateString()),
                    Select::make('granularidad')
                        ->label('Ver por')
                        ->options(Granularidad::opciones())
                        ->default(Granularidad::Dia->value)
                        ->selectablePlaceholder(false),
                ])
                ->columns(3)
                ->columnSpanFull(),
        ]);
    }

    /**
     * El botón "Descargar", con el reporte de ingresos en CSV y en Excel, con las cifras de la página para el rango que
     * se elija (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5; openspec/changes/exportacion/).
     *
     * @return array<DescargarReporte>
     */
    protected function getHeaderActions(): array
    {
        return [
            DescargarReporte::de(IngresosExportador::class)
                ->label('Descargar')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray'),
        ];
    }

    /**
     * @return array<class-string<Widget>>
     */
    public function getWidgets(): array
    {
        return [
            IngresosDelPeriodo::class,
            IngresosPorPeriodoTabla::class,
        ];
    }
}
