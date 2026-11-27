<?php

namespace App\Indicadores;

use App\Models\EstadoPedido;
use Illuminate\Support\Facades\DB;

/**
 * El indicador de pedidos por estado (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5, categoría Operaciones;
 * indicadores-E04): cuántos pedidos del rango están activos y cuántos completados, por su estado actual.
 *
 * - Pedido del rango es el que llegó a Confirmado en el rango, en el día de su plaza, como en PedidosTotales
 *   (openspec/changes/indicadores/design.md, lectura 1).
 * - Activo es el confirmado que todavía no se entrega ni se cancela: los estados operativos de Confirmado a En entrega
 *   (ADR-0021 y su adenda). Completado es el entregado.
 * - Los cancelados y los rechazados esperan el estado de cancelación (indicadores-E05; DD-016; BR-22): no cuentan como
 *   activos ni como completados, y no se muestran.
 */
final class PedidosPorEstado
{
    /** Los estados del pedido confirmado que todavía no se entrega ni se cancela. */
    public const ACTIVOS = [
        EstadoPedido::CONFIRMADO,
        EstadoPedido::ACEPTADO,
        EstadoPedido::RECOLECTADO,
        EstadoPedido::EN_PROCESO,
        EstadoPedido::LISTO,
        EstadoPedido::EN_ENTREGA,
    ];

    /** El estado del pedido completado. */
    public const COMPLETADOS = [EstadoPedido::ENTREGADO];

    /**
     * @return array{activos: int, completados: int}
     */
    public function delRango(Rango $rango): array
    {
        $fila = DB::table('pedidos')
            ->join('plazas', 'plazas.id_plazas', '=', 'pedidos.id_plazas')
            ->join('estados_pedido', 'estados_pedido.id_estados_pedido', '=', 'pedidos.id_estados_pedido')
            ->whereNotNull('pedidos.confirmado_en')
            ->whereRaw(PedidosTotales::FECHA_DEL_PEDIDO.' between ? and ?', [
                $rango->desde->toDateString(),
                $rango->hasta->toDateString(),
            ])
            ->selectRaw($this->contarLos('activos', self::ACTIVOS), self::ACTIVOS)
            ->selectRaw($this->contarLos('completados', self::COMPLETADOS), self::COMPLETADOS)
            ->first();

        return [
            'activos' => (int) $fila->activos,
            'completados' => (int) $fila->completados,
        ];
    }

    /**
     * @param  list<string>  $codigos
     */
    private function contarLos(string $nombre, array $codigos): string
    {
        $marcas = implode(', ', array_fill(0, count($codigos), '?'));

        return "count(*) filter (where estados_pedido.codigo in ({$marcas})) as {$nombre}";
    }
}
