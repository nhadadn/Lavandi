<?php

namespace App\Pedidos;

use App\Filament\EstadosDelPedido;
use App\Models\Pedido;
use App\Models\PedidoDireccion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Los marcadores del mapa del monitoreo de pedidos (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #4, "pedidos
 * activos con mapa"; administracion-E09 y administracion-E10; design.md, lectura 5; BR-30):
 *
 * - uno en la dirección de recolección de cada pedido;
 * - otro en la de entrega, cuando es distinta: cuando su copia salió de otra dirección guardada que la de la recolección
 *   (pedido_direcciones.id_direcciones). Si salió de la misma, el pedido lleva un solo marcador, el de la recolección,
 *   que dice "Recolección y entrega".
 *
 * Cada marcador lleva el folio, el estado operativo (App\Filament\EstadosDelPedido, como la tabla) y la plaza del pedido,
 * y el punto que se guardó con la dirección, leído con PostGIS: no se geocodifica nada (presupuesto-de-mapas.md,
 * decisión 3). No hay marcadores de repartidores: su posición está fuera del contrato (proposal.md).
 *
 * Los pedidos llegan del componente de autorización (Autorizacion::pedidosActivosDelPanel()): esta clase no decide quién
 * ve cuáles.
 */
final class MarcadoresDelMonitoreo
{
    public const RECOLECCION = 'recoleccion';

    public const ENTREGA = 'entrega';

    /**
     * Los marcadores de esos pedidos, de la recolección más próxima a la más lejana, como la tabla del monitoreo; la
     * recolección de cada pedido va antes que su entrega.
     *
     * @param  Builder<Pedido>  $pedidos
     * @return list<array{pedido: string, folio: string, estado: string, plaza: string, tipo: string, etiqueta: string, latitud: float, longitud: float}>
     */
    public function deLosPedidos(Builder $pedidos): array
    {
        return $pedidos
            ->with(['estado', 'plaza', 'direcciones' => fn (HasMany $direcciones) => $direcciones->conCoordenadas()])
            ->deLaRecoleccionMasProxima()
            ->get()
            ->flatMap(fn (Pedido $pedido): array => $this->delPedido($pedido))
            ->values()
            ->all();
    }

    /**
     * @return list<array{pedido: string, folio: string, estado: string, plaza: string, tipo: string, etiqueta: string, latitud: float, longitud: float}>
     */
    private function delPedido(Pedido $pedido): array
    {
        $recoleccion = $pedido->direcciones->firstWhere('tipo', PedidoDireccion::RECOLECCION);
        $entrega = $pedido->direcciones->firstWhere('tipo', PedidoDireccion::ENTREGA);

        if ($recoleccion === null) {
            return [];
        }

        $enOtraDireccion = $entrega !== null && $entrega->id_direcciones !== $recoleccion->id_direcciones;

        return array_values(array_filter([
            $this->marcador($pedido, $recoleccion, self::RECOLECCION, $enOtraDireccion ? 'Recolección' : 'Recolección y entrega'),
            $enOtraDireccion ? $this->marcador($pedido, $entrega, self::ENTREGA, 'Entrega') : null,
        ]));
    }

    /**
     * @return array{pedido: string, folio: string, estado: string, plaza: string, tipo: string, etiqueta: string, latitud: float, longitud: float}
     */
    private function marcador(Pedido $pedido, PedidoDireccion $direccion, string $tipo, string $etiqueta): array
    {
        return [
            'pedido' => $pedido->id_publico,
            'folio' => $pedido->folio,
            'estado' => EstadosDelPedido::nombre($pedido->estado),
            'plaza' => $pedido->plaza->nombre,
            'tipo' => $tipo,
            'etiqueta' => $etiqueta,
            'latitud' => (float) $direccion->getAttribute('latitud'),
            'longitud' => (float) $direccion->getAttribute('longitud'),
        ];
    }
}
