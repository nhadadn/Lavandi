<?php

namespace App\Filament\Widgets\Indicadores\Concerns;

use App\Autorizacion\Autorizacion;
use App\Indicadores\Rango;
use App\Indicadores\Refresco;
use App\Models\Usuario;
use Filament\Facades\Filament;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Lo que comparten los widgets de la página de indicadores (App\Filament\Pages\Indicadores):
 *
 * - leen el rango y la granularidad de los filtros de la página;
 * - se montan solo para quien ve los indicadores, según el componente único de autorización (ADR-0019), y uno montado
 *   por otra cuenta responde 403;
 * - no salen en el tablero de inicio del panel: Filament los registra como componentes de Livewire, pero solo los pone
 *   la página de indicadores;
 * - se dibujan al abrir la página, sin carga diferida, para que la primera vista ya traiga las cifras;
 * - se refrescan solos con el intervalo de la configuración (App\Indicadores\Refresco; indicadores-E21).
 */
trait EsDeIndicadores
{
    use InteractsWithPageFilters;

    public static function canView(): bool
    {
        $cuenta = Filament::auth()->user();

        return $cuenta instanceof Usuario && app(Autorizacion::class)->puedeVerLosIndicadores($cuenta);
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
     * Cada cuánto sondea el widget, de la configuración (indicadores-E21). Reemplaza los 5 s que trae la gráfica de
     * Filament; sin intervalo, no sondea. Las tablas lo pasan a su poll().
     */
    protected function getPollingInterval(): ?string
    {
        return Refresco::intervalo();
    }
}
