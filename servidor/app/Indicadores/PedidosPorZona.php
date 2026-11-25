<?php

namespace App\Indicadores;

use App\Models\PedidoDireccion;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use JsonException;

/**
 * El mapa de calor por zona de servicio (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5, categoría Geográfico;
 * indicadores-E20): cada zona con su número de pedidos del rango, su intensidad y su polígono para pintarla. Las zonas y
 * el punto de cada pedido se cruzan aquí, en el servidor, con PostGIS (ADR-0004; design.md, "Decisions").
 *
 * - Un pedido cuenta en la zona de su plaza que cubre el punto de su dirección de recolección (ST_Covers, que cuenta el
 *   borde como dentro, igual que App\Direcciones\ZonaDeServicio), no en la de su entrega (design.md, pregunta 4).
 * - Salen las zonas activas, también con 0 pedidos, y una inactiva solo si tuvo pedidos en el rango.
 * - El polígono sale simplificado y con cinco decimales, como en la cobertura de la portada, para que no pese en el
 *   panel (indicadores.mapa.simplificacion).
 * - Pedido del rango es el que llegó a Confirmado en el rango, en el día de su plaza (lectura 1). Van de más a menos
 *   pedidos y, con los mismos, por plaza y por zona.
 *
 * Es la consulta del indicador: el widget del mapa y la descarga de exportacion leen de aquí.
 */
final class PedidosPorZona
{
    /**
     * Una fila por zona, con la clave de su llave, en orden: la zona, su plaza, sus pedidos, su intensidad y su polígono
     * en GeoJSON.
     *
     * @return array<string, array{zona: string, plaza: string, pedidos: int, intensidad: float, geometria: array{type: string, coordinates: list<mixed>}}>
     */
    public function delRango(Rango $rango): array
    {
        $tolerancia = (float) config('indicadores.mapa.simplificacion');

        $filas = DB::table('zonas')
            ->join('plazas', 'plazas.id_plazas', '=', 'zonas.id_plazas')
            ->leftJoinSub($this->recolecciones($rango), 'recolecciones', fn (JoinClause $cubre) => $cubre
                ->on('recolecciones.id_plazas', '=', 'zonas.id_plazas')
                ->whereRaw('ST_Covers(zonas.poligono, recolecciones.ubicacion)'))
            ->groupBy('zonas.id_zonas', 'zonas.nombre', 'zonas.activa', 'plazas.nombre')
            ->havingRaw('zonas.activa or count(recolecciones.id_pedidos) > 0')
            ->select('zonas.id_zonas', 'zonas.nombre as zona', 'plazas.nombre as plaza')
            ->selectRaw('count(recolecciones.id_pedidos) as pedidos')
            ->selectRaw('ST_AsGeoJSON(ST_Multi(ST_SimplifyPreserveTopology(zonas.poligono::geometry, ?)), 5) as geojson', [$tolerancia])
            ->orderByRaw('count(recolecciones.id_pedidos) desc')
            ->orderBy('plazas.nombre')
            ->orderBy('zonas.nombre')
            ->get();

        $maximo = (int) $filas->max('pedidos');
        $zonas = [];

        foreach ($filas as $fila) {
            $zonas[(string) $fila->id_zonas] = [
                'zona' => (string) $fila->zona,
                'plaza' => (string) $fila->plaza,
                'pedidos' => (int) $fila->pedidos,
                'intensidad' => Intensidad::de((int) $fila->pedidos, $maximo),
                'geometria' => self::geometria((string) $fila->geojson),
            ];
        }

        return $zonas;
    }

    /**
     * El punto de recolección de cada pedido confirmado del rango, con su plaza.
     */
    private function recolecciones(Rango $rango): Builder
    {
        return DB::table('pedidos')
            ->join('plazas', 'plazas.id_plazas', '=', 'pedidos.id_plazas')
            ->join('pedido_direcciones', fn (JoinClause $direccion) => $direccion
                ->on('pedido_direcciones.id_pedidos', '=', 'pedidos.id_pedidos')
                ->where('pedido_direcciones.tipo', PedidoDireccion::RECOLECCION))
            ->whereNotNull('pedidos.confirmado_en')
            ->whereRaw(PedidosTotales::FECHA_DEL_PEDIDO.' between ? and ?', [
                $rango->desde->toDateString(),
                $rango->hasta->toDateString(),
            ])
            ->select('pedidos.id_pedidos', 'pedidos.id_plazas', 'pedido_direcciones.ubicacion');
    }

    /**
     * @return array{type: string, coordinates: list<mixed>}
     *
     * @throws JsonException
     */
    private static function geometria(string $geoJson): array
    {
        /** @var array{type: string, coordinates: list<mixed>} */
        return json_decode($geoJson, true, 512, JSON_THROW_ON_ERROR);
    }
}
