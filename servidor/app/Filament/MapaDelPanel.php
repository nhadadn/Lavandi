<?php

namespace App\Filament;

/**
 * Lo que comparten los mapas del panel de administración, todos con Google Maps JavaScript (ADR-0009, decisión 3): el
 * mapa de calor de los indicadores (indicadores-E19 e indicadores-E20) y el mapa del monitoreo de pedidos
 * (administracion-E09 y administracion-E10).
 *
 * - Una sola llave, la de la configuración (services.google_maps_panel.llave, GOOGLE_MAPS_PANEL_LLAVE): de navegador, de
 *   cada entorno y restringida a la dirección del panel (ADR-0012). Sin ella, ningún mapa se monta y el panel lo dice.
 * - Las mismas piezas de Blade: el aviso sin llave (filament.mapas.sin-llave), el contenedor que Livewire no toca
 *   (filament.mapas.contenedor, con wire:ignore) y el cargador de la API (filament.mapas.cargador), que la carga una
 *   sola vez por página. Cada mapa se crea una vez al abrir la página; el refresco cambia solo sus datos, para no
 *   gastar cargas de mapa (presupuesto-de-mapas.md).
 */
final class MapaDelPanel
{
    /**
     * La llave de Google Maps JavaScript del panel, de la configuración; null si no hay.
     */
    public static function llave(): ?string
    {
        $llave = config('services.google_maps_panel.llave');

        return is_string($llave) && trim($llave) !== '' ? trim($llave) : null;
    }
}
