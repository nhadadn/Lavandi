<?php

namespace App\Indicadores;

use App\Models\EstadoPedido;
use App\Models\Repartidor;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * El indicador de entregas completadas por repartidor (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5, categoría Socios;
 * indicadores-E15): cuántas entregas completó cada repartidor en el rango, en una tabla que los pone lado a lado
 * (openspec/changes/indicadores/design.md, lectura 5).
 *
 * - Una entrega es del repartidor que marcó el paso del pedido a Entregado: el evento de pedido_eventos con su cuenta
 *   (openspec/changes/repartidor/design.md, lectura 5), como su historial (Autorizacion::entregasDelRepartidor()).
 * - Cuenta en el rango por el día de ese paso, en la hora de la plaza del pedido, como sus estadísticas
 *   (App\Repartidores\EstadisticasDelRepartidor).
 * - Salen los repartidores dados de alta que no se dieron de baja, aunque no tengan entregas; uno pendiente o dado de
 *   baja sale solo si tiene entregas en el rango: sus entregas se conservan (repartidor-E07). Van de más a menos
 *   entregas y, con las mismas, por nombre.
 *
 * Es la consulta del indicador: la tabla y la descarga de exportacion leen de aquí.
 */
final class EntregasPorRepartidor
{
    /** El día de la entrega: el de su paso a Entregado, en la hora de la plaza del pedido. */
    private const DIA_DE_LA_ENTREGA = '(pedido_eventos.ocurrido_en at time zone plazas.zona_horaria)::date';

    /**
     * Una fila por repartidor, con la clave de su id_publico, en orden: su nombre, su plaza y sus entregas del rango.
     *
     * @return array<string, array{repartidor: string, plaza: string|null, entregas: int}>
     */
    public function delRango(Rango $rango): array
    {
        $filas = DB::table('repartidores')
            ->join('usuarios', 'usuarios.id_usuarios', '=', 'repartidores.id_usuarios')
            ->leftJoin('plazas', 'plazas.id_plazas', '=', 'repartidores.id_plazas')
            ->leftJoinSub($this->entregas($rango), 'entregas', 'entregas.id_usuarios', '=', 'repartidores.id_usuarios')
            ->where(fn (Builder $salen) => $salen
                ->where(fn (Builder $deAlta) => $deAlta
                    ->whereNull('repartidores.deleted_at')
                    ->where('repartidores.estado', '<>', Repartidor::PENDIENTE))
                ->orWhereNotNull('entregas.entregas'))
            ->select('repartidores.id_publico', 'plazas.nombre as plaza')
            ->selectRaw('coalesce(usuarios.nombre, usuarios.correo) as repartidor')
            ->selectRaw('coalesce(entregas.entregas, 0) as entregas')
            ->orderByRaw('coalesce(entregas.entregas, 0) desc')
            ->orderByRaw('coalesce(usuarios.nombre, usuarios.correo)')
            ->orderBy('repartidores.id_repartidores')
            ->get();

        $tabla = [];

        foreach ($filas as $fila) {
            $tabla[(string) $fila->id_publico] = [
                'repartidor' => (string) $fila->repartidor,
                'plaza' => $fila->plaza === null ? null : (string) $fila->plaza,
                'entregas' => (int) $fila->entregas,
            ];
        }

        return $tabla;
    }

    /**
     * Las entregas del rango de cada cuenta que marcó Entregado, de pedidos confirmados.
     */
    private function entregas(Rango $rango): Builder
    {
        return DB::table('pedido_eventos')
            ->join('estados_pedido', 'estados_pedido.id_estados_pedido', '=', 'pedido_eventos.id_estados_pedido')
            ->join('pedidos', 'pedidos.id_pedidos', '=', 'pedido_eventos.id_pedidos')
            ->join('plazas', 'plazas.id_plazas', '=', 'pedidos.id_plazas')
            ->where('estados_pedido.codigo', EstadoPedido::ENTREGADO)
            ->whereNotNull('pedidos.confirmado_en')
            ->whereNotNull('pedido_eventos.id_usuarios')
            ->whereRaw(self::DIA_DE_LA_ENTREGA.' between ? and ?', [$rango->desde->toDateString(), $rango->hasta->toDateString()])
            ->groupBy('pedido_eventos.id_usuarios')
            ->select('pedido_eventos.id_usuarios')
            ->selectRaw('count(distinct pedido_eventos.id_pedidos) as entregas');
    }
}
