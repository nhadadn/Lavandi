<?php

namespace App\Calificaciones;

use App\Models\Calificacion;
use App\Models\EstadoPedido;
use App\Models\Pedido;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * La calificación del servicio de un pedido del usuario: de 1 a 5 estrellas con un comentario opcional
 * (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo A #14), guardada como calificación de la lavandería del pedido,
 * con su pedido, su plaza y la cuenta que lo hizo, y sin repartidor (historial-E12;
 * openspec/changes/historial-y-calificacion/design.md, decisión 2). Si el usuario califica también al repartidor lo
 * decide DD-052 (historial-E11 y E14): sería otra fila, sin tocar esta.
 *
 * Recibe el pedido que ya dio el componente de autorización (Autorizacion::pedidosVisibles()): de quién es no se decide
 * aquí (historial-E09).
 *
 * Desde qué estado y hasta cuándo se califica, y si una calificación se cambia, es BR-25 (historial-E10, DD-107). Mientras
 * no responda, la lectura provisional del diseño: solo un pedido Entregado, una vez y sin plazo.
 */
final class CalificacionDelServicio
{
    /**
     * @throws CalificacionNoPermitida si el pedido todavía no está Entregado o ya está calificado
     */
    public function calificar(Pedido $pedido, int $estrellas, ?string $comentario): Calificacion
    {
        if ($pedido->estado->codigo !== EstadoPedido::ENTREGADO) {
            throw CalificacionNoPermitida::todaviaNo();
        }

        try {
            // En su propia transacción: si el único por pedido y lavandería la rechaza, la conexión sigue sana.
            return DB::transaction(fn (): Calificacion => Calificacion::create([
                'id_pedidos' => $pedido->getKey(),
                'id_plazas' => $pedido->id_plazas,
                'id_usuarios' => $pedido->id_usuarios,
                'id_lavanderias' => $pedido->id_lavanderias,
                'estrellas' => $estrellas,
                'comentario' => $comentario,
            ]));
        } catch (UniqueConstraintViolationException) {
            // Una por pedido y calificado (historial-E13): ya la tiene.
            throw CalificacionNoPermitida::yaCalificado();
        }
    }
}
