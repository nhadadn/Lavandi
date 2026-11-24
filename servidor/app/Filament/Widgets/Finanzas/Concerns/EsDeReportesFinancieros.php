<?php

namespace App\Filament\Widgets\Finanzas\Concerns;

use App\Autorizacion\Autorizacion;
use App\Indicadores\Rango;
use App\Models\Usuario;
use Filament\Facades\Filament;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Lo que comparten los widgets de los reportes financieros del panel (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3,
 * Módulo D #6; openspec/changes/administracion/, sección 4 de tasks.md):
 *
 * - leen el rango y la granularidad de los filtros de su página, como los de indicadores (App\Indicadores\Rango);
 * - se montan solo para quien ve los reportes financieros, según el componente único de autorización (ADR-0019), y uno
 *   montado por otra cuenta responde 403;
 * - no salen en el tablero de inicio del panel: solo los pone su página;
 * - se dibujan al abrir la página, sin carga diferida, y no sondean: el contrato no pide que un reporte se refresque
 *   solo, y las cifras se actualizan al cambiar los filtros o recargar.
 */
trait EsDeReportesFinancieros
{
    use InteractsWithPageFilters;

    public static function canView(): bool
    {
        $cuenta = Filament::auth()->user();

        return $cuenta instanceof Usuario && app(Autorizacion::class)->puedeVerLosReportesFinancieros($cuenta);
    }

    public static function isDiscovered(): bool
    {
        return false;
    }

    public static function isLazyLoaded(): bool
    {
        return false;
    }

    /**
     * El rango y la granularidad que eligió la administración en la página.
     */
    protected function rango(): Rango
    {
        return Rango::deLosFiltros($this->pageFilters);
    }

    /**
     * Sin sondeo: quita los 5 s que traen los widgets de estadísticas de Filament.
     */
    protected function getPollingInterval(): ?string
    {
        return null;
    }
}
