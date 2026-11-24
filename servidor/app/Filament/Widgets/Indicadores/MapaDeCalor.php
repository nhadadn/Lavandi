<?php

namespace App\Filament\Widgets\Indicadores;

use App\Filament\MapaDelPanel;
use App\Filament\Widgets\Indicadores\Concerns\EsDeIndicadores;
use App\Indicadores\Intensidad;
use App\Indicadores\PedidosPorColonia;
use App\Indicadores\PedidosPorZona;
use Filament\Widgets\Widget;

/**
 * El mapa de calor de los pedidos del rango, por colonia o por zona de servicio, a elección de la administración
 * (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5, "Mapa interactivo de calor"; indicadores-E19 e indicadores-E20). Las
 * cifras salen de App\Indicadores\PedidosPorColonia y App\Indicadores\PedidosPorZona, calculadas en el servidor.
 *
 * - Por colonia, un punto por colonia, más oscuro y más grande cuantos más pedidos; por zona, cada zona pintada con su
 *   número de pedidos.
 * - El mapa es Google Maps JavaScript (ADR-0009, decisión 3), sin su capa de calor: dibuja los agregados del servidor.
 *   Se carga una vez al abrir la página; el refresco, los filtros y el cambio de agrupación cambian solo los datos, sin
 *   crear otro mapa, para no gastar cargas de mapa (presupuesto-de-mapas.md).
 * - La llave, el aviso sin ella, el contenedor y el cargador de la API son los de todos los mapas del panel
 *   (App\Filament\MapaDelPanel). Sin llave, el mapa no se monta y el panel lo dice, como la app sin la llave de Android.
 * - Las mismas cifras van en una tabla, para leerlas sin el mapa y sin depender del color.
 */
class MapaDeCalor extends Widget
{
    use EsDeIndicadores;

    public const POR_COLONIA = 'colonia';

    public const POR_ZONA = 'zona';

    /** Las agrupaciones que elige la administración, con su nombre. */
    public const AGRUPACIONES = [self::POR_COLONIA => 'Colonia', self::POR_ZONA => 'Zona de servicio'];

    protected string $view = 'filament.widgets.indicadores.mapa-de-calor';

    protected int|string|array $columnSpan = 'full';

    /** Por colonia o por zona de servicio; una que no existe se toma como por colonia. */
    public string $agrupacion = self::POR_COLONIA;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $comun = [
            'llave' => MapaDelPanel::llave(),
            'intervalo' => $this->getPollingInterval(),
            'rampa' => Intensidad::RAMPA,
            'agrupaciones' => self::AGRUPACIONES,
        ];

        return $this->agrupacion() === self::POR_ZONA
            ? [...$comun, ...$this->porZona()]
            : [...$comun, ...$this->porColonia()];
    }

    private function agrupacion(): string
    {
        return array_key_exists($this->agrupacion, self::AGRUPACIONES) ? $this->agrupacion : self::POR_COLONIA;
    }

    /**
     * @return array<string, mixed>
     */
    private function porColonia(): array
    {
        $colonias = array_values(app(PedidosPorColonia::class)->delRango($this->rango()));

        return [
            'descripcion' => $this->rango()->dias().'. Cada punto es una colonia de recolección; más oscuro, más pedidos.',
            'columnas' => ['Colonia', 'Municipio', 'Plaza'],
            'filas' => array_map(fn (array $colonia): array => [
                'textos' => [$colonia['colonia'], $colonia['municipio'] ?? 'Sin municipio', $colonia['plaza']],
                'pedidos' => $colonia['pedidos'],
            ], $colonias),
            'datos' => [
                'agrupacion' => self::POR_COLONIA,
                'puntos' => array_map(fn (array $colonia): array => [
                    'nombre' => $colonia['colonia'],
                    'detalle' => implode(', ', array_filter([$colonia['municipio'], $colonia['plaza']])),
                    'pedidos' => $colonia['pedidos'],
                    'intensidad' => $colonia['intensidad'],
                    'color' => Intensidad::color($colonia['intensidad']),
                    'latitud' => $colonia['latitud'],
                    'longitud' => $colonia['longitud'],
                ], $colonias),
                'zonas' => [],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function porZona(): array
    {
        $zonas = array_values(app(PedidosPorZona::class)->delRango($this->rango()));

        return [
            'descripcion' => $this->rango()->dias().'. Cada zona de servicio, pintada por su número de pedidos de recolección; más oscuro, más pedidos.',
            'columnas' => ['Zona de servicio', 'Plaza'],
            'filas' => array_map(fn (array $zona): array => [
                'textos' => [$zona['zona'], $zona['plaza']],
                'pedidos' => $zona['pedidos'],
            ], $zonas),
            'datos' => [
                'agrupacion' => self::POR_ZONA,
                'puntos' => [],
                'zonas' => array_map(fn (array $zona): array => [
                    'nombre' => $zona['zona'],
                    'detalle' => $zona['plaza'],
                    'pedidos' => $zona['pedidos'],
                    'intensidad' => $zona['intensidad'],
                    'color' => Intensidad::color($zona['intensidad']),
                    'geometria' => $zona['geometria'],
                ], $zonas),
            ],
        ];
    }
}
