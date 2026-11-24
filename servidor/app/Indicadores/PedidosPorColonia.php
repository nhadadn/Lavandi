<?php

namespace App\Indicadores;

use App\Models\PedidoDireccion;
use Illuminate\Support\Facades\DB;

/**
 * El mapa de calor por colonia (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5, categoría Geográfico; indicadores-E19):
 * cuántos pedidos del rango hubo en cada colonia, con un punto para dibujarla y su intensidad. Los agregados se calculan
 * aquí, en el servidor, con PostGIS (ADR-0004; ADR-0009, decisión 3).
 *
 * - La colonia es la que se capturó en la dirección de recolección del pedido, la copia de pedido_direcciones
 *   (design.md, pregunta 4: la recolección es la recomendación). No se deduce del mapa: no hay geocodificación inversa
 *   (presupuesto-de-mapas.md, decisión 4).
 * - Una colonia es su texto sin espacios a los lados, en su municipio y su plaza: la misma colonia escrita igual en dos
 *   municipios son dos colonias. Escrita de dos formas distintas, se cuenta como dos (design.md, "Risks / Trade-offs").
 * - El punto de cada colonia es el centro de las recolecciones de sus pedidos (ST_Centroid de su colección de puntos).
 * - La intensidad es la parte de la colonia con más pedidos, de 0 a 1, con dos decimales.
 * - Pedido del rango es el que llegó a Confirmado en el rango, en el día de su plaza, como en PedidosTotales
 *   (lectura 1). Van de más a menos pedidos y, con los mismos, por colonia.
 *
 * Es la consulta del indicador: el widget del mapa y la descarga de exportacion leen de aquí.
 */
final class PedidosPorColonia
{
    /**
     * Una fila por colonia con pedidos en el rango, en orden: la colonia, su municipio, su plaza, sus pedidos, su
     * intensidad y su punto.
     *
     * @return array<string, array{colonia: string, municipio: string|null, plaza: string, pedidos: int, intensidad: float, latitud: float, longitud: float}>
     */
    public function delRango(Rango $rango): array
    {
        $colonia = 'btrim(pedido_direcciones.colonia)';
        $centro = 'ST_Centroid(ST_Collect(pedido_direcciones.ubicacion::geometry))';

        $filas = DB::table('pedidos')
            ->join('plazas', 'plazas.id_plazas', '=', 'pedidos.id_plazas')
            ->join('pedido_direcciones', fn ($direccion) => $direccion
                ->on('pedido_direcciones.id_pedidos', '=', 'pedidos.id_pedidos')
                ->where('pedido_direcciones.tipo', PedidoDireccion::RECOLECCION))
            ->whereNotNull('pedidos.confirmado_en')
            ->whereRaw(PedidosTotales::FECHA_DEL_PEDIDO.' between ? and ?', [
                $rango->desde->toDateString(),
                $rango->hasta->toDateString(),
            ])
            ->groupBy('plazas.id_plazas', 'plazas.nombre', 'pedido_direcciones.municipio')
            ->groupByRaw($colonia)
            ->select('plazas.id_plazas', 'plazas.nombre as plaza', 'pedido_direcciones.municipio')
            ->selectRaw("{$colonia} as colonia")
            ->selectRaw('count(*) as pedidos')
            ->selectRaw("ST_Y({$centro}) as latitud")
            ->selectRaw("ST_X({$centro}) as longitud")
            ->orderByRaw('count(*) desc')
            ->orderByRaw($colonia)
            ->orderBy('pedido_direcciones.municipio')
            ->orderBy('plazas.nombre')
            ->get();

        $maximo = (int) $filas->max('pedidos');
        $colonias = [];

        foreach ($filas as $fila) {
            $colonias["{$fila->id_plazas}|{$fila->municipio}|{$fila->colonia}"] = [
                'colonia' => (string) $fila->colonia,
                'municipio' => $fila->municipio === null ? null : (string) $fila->municipio,
                'plaza' => (string) $fila->plaza,
                'pedidos' => (int) $fila->pedidos,
                'intensidad' => Intensidad::de((int) $fila->pedidos, $maximo),
                'latitud' => (float) $fila->latitud,
                'longitud' => (float) $fila->longitud,
            ];
        }

        return $colonias;
    }
}
