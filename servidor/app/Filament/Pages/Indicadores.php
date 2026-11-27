<?php

namespace App\Filament\Pages;

use App\Autorizacion\Autorizacion;
use App\Exportacion\Exportadores\EntregasPorRepartidorExportador;
use App\Exportacion\Exportadores\IngresosBrutosExportador;
use App\Exportacion\Exportadores\MontoPromedioExportador;
use App\Exportacion\Exportadores\PedidosPorColoniaExportador;
use App\Exportacion\Exportadores\PedidosPorEstadoExportador;
use App\Exportacion\Exportadores\PedidosPorZonaExportador;
use App\Exportacion\Exportadores\PedidosTotalesExportador;
use App\Exportacion\Exportadores\RankingDeLavanderiasExportador;
use App\Exportacion\Exportadores\RetencionExportador;
use App\Exportacion\Exportadores\UsuariosActivosExportador;
use App\Filament\Actions\DescargarReporte;
use App\Filament\Widgets\Indicadores\EntregasPorRepartidorTabla;
use App\Filament\Widgets\Indicadores\IngresosBrutosGrafica;
use App\Filament\Widgets\Indicadores\MapaDeCalor;
use App\Filament\Widgets\Indicadores\MontoPromedioIndicador;
use App\Filament\Widgets\Indicadores\PedidosPorEstadoGrafica;
use App\Filament\Widgets\Indicadores\PedidosTotalesGrafica;
use App\Filament\Widgets\Indicadores\PedidosTotalesTabla;
use App\Filament\Widgets\Indicadores\RankingDeLavanderiasTabla;
use App\Filament\Widgets\Indicadores\RetencionTabla;
use App\Filament\Widgets\Indicadores\UsuariosActivosGrafica;
use App\Indicadores\Granularidad;
use App\Indicadores\Rango;
use App\Indicadores\Refresco;
use App\Models\Usuario;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;

/**
 * El panel de indicadores, dentro del panel de administración (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5;
 * NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.4; openspec/changes/indicadores/): muestra el estado del negocio con los
 * datos de la base, sin exportar ni procesar nada a mano.
 *
 * - La administración elige el rango y la granularidad, por día, semana o mes, y cada widget los lee de los filtros
 *   de la página (tarea 1.1).
 * - Cada indicador sale de su consulta en App\Indicadores; ningún widget trae cifras propias (indicadores-E23).
 * - Solo la ve el rol de administración, según el componente único de autorización (ADR-0019).
 *
 * Es un segundo tablero de Filament, en /admin/indicadores: el de inicio del panel no cambia.
 */
class Indicadores extends Dashboard
{
    use HasFiltersForm;

    protected static string $routePath = '/indicadores';

    protected static ?string $title = 'Indicadores';

    protected static ?string $navigationLabel = 'Indicadores';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?int $navigationSort = -1;

    public static function canAccess(): bool
    {
        $cuenta = Filament::auth()->user();

        return $cuenta instanceof Usuario && app(Autorizacion::class)->puedeVerLosIndicadores($cuenta);
    }

    /**
     * Cada cuánto se refrescan solas las cifras, o que se actualizan al recargar (indicadores-E21; DD-116).
     */
    public function getSubheading(): string
    {
        return Refresco::descripcion();
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
     * El botón "Descargar", con un reporte por indicador, en CSV y en Excel (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5;
     * openspec/changes/exportacion/). Cada reporte sale de la misma consulta que su widget.
     *
     * @return array<ActionGroup>
     */
    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                DescargarReporte::de(PedidosTotalesExportador::class),
                DescargarReporte::de(PedidosPorEstadoExportador::class),
                DescargarReporte::de(UsuariosActivosExportador::class),
                DescargarReporte::de(RetencionExportador::class),
                DescargarReporte::de(MontoPromedioExportador::class),
                DescargarReporte::de(RankingDeLavanderiasExportador::class),
                DescargarReporte::de(EntregasPorRepartidorExportador::class),
                DescargarReporte::de(IngresosBrutosExportador::class),
                DescargarReporte::de(PedidosPorColoniaExportador::class),
                DescargarReporte::de(PedidosPorZonaExportador::class),
            ])
                ->label('Descargar')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->button(),
        ];
    }

    /**
     * Los widgets de la página, en el orden del A.5.
     *
     * @return array<class-string<Widget>>
     */
    public function getWidgets(): array
    {
        return [
            PedidosTotalesGrafica::class,
            PedidosTotalesTabla::class,
            PedidosPorEstadoGrafica::class,
            UsuariosActivosGrafica::class,
            RetencionTabla::class,
            MontoPromedioIndicador::class,
            RankingDeLavanderiasTabla::class,
            EntregasPorRepartidorTabla::class,
            IngresosBrutosGrafica::class,
            MapaDeCalor::class,
        ];
    }
}
