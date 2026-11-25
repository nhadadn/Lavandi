<?php

namespace App\Repartidores;

use App\Models\Pedido;
use App\Models\PedidoAsignacion;
use App\Models\Repartidor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * El repartidor acepta o rechaza con su motivo el pedido que se le ofreció (NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3,
 * Módulo C #2, "Aceptar o rechazar con motivo"; openspec/changes/repartidor/design.md, "Tablas y migración").
 *
 * La respuesta queda en su fila de pedido_asignaciones, con su hora en respondido_en:
 *
 * - Aceptar (repartidor-E15): la oferta pasa a aceptado y el pedido queda suyo, en pedidos.id_repartidores. Desde ahí
 *   tiene sus botones de estado (App\Pedidos\CambioDeEstado). A otro repartidor con una oferta del mismo pedido deja de
 *   aparecerle (App\Autorizacion\Autorizacion::pedidosDelRepartidor()).
 * - Rechazar (repartidor-E16): la oferta pasa a rechazado con su motivo, y el pedido deja de estar asignado a él. Qué
 *   pasa después con el pedido (se reasigna, se avisa o se detiene) espera DD-014 (repartidor-E18): aquí no se toca.
 *
 * Ninguna de las dos cambia el estado del pedido ni agrega un evento a su historial: la oferta no es un estado del
 * pedido (ADR-0021). Una oferta ya respondida no se vuelve a responder, y no se acepta un pedido que ya no está en curso
 * o que tomó otro repartidor: RespuestaNoPermitida, y nada cambia.
 *
 * No decide quién ve el pedido: lo recibe ya leído por App\Autorizacion\Autorizacion::pedidosDelRepartidor(), que
 * también deja fuera uno sin pago acreditado (repartidor-E30).
 */
final class RespuestaDelRepartidor
{
    public const YA_RESPONDIDA = 'La oferta ya fue respondida.';

    public const DE_OTRO_REPARTIDOR = 'El pedido ya lo tomó otro repartidor.';

    public const FUERA_DE_CURSO = 'El pedido ya no está en curso.';

    /**
     * @throws RespuestaNoPermitida
     */
    public function aceptar(Pedido $pedido, Repartidor $repartidor): Pedido
    {
        return DB::transaction(function () use ($pedido, $repartidor): Pedido {
            // Bloquea el pedido: dos repartidores que aceptan a la vez no lo toman los dos.
            $bloqueado = Pedido::query()->whereKey($pedido->getKey())->lockForUpdate()->sole();
            $oferta = $this->ofertaPorResponder($bloqueado, $repartidor);

            if ($bloqueado->id_repartidores !== null && $bloqueado->id_repartidores !== $repartidor->getKey()) {
                throw new RespuestaNoPermitida($bloqueado, $repartidor, self::DE_OTRO_REPARTIDOR);
            }

            if (! Pedido::query()->whereKey($bloqueado->getKey())->activosDelRepartidor()->exists()) {
                throw new RespuestaNoPermitida($bloqueado, $repartidor, self::FUERA_DE_CURSO);
            }

            $oferta->forceFill(['estado' => PedidoAsignacion::ACEPTADO, 'respondido_en' => $this->ahora()])->save();
            $bloqueado->forceFill(['id_repartidores' => $repartidor->getKey()])->save();

            return $bloqueado;
        });
    }

    /**
     * @param  string  $motivo  Ya validado: texto, sin los espacios de las orillas y no vacío (repartidor-E17).
     *
     * @throws RespuestaNoPermitida
     */
    public function rechazar(Pedido $pedido, Repartidor $repartidor, string $motivo): PedidoAsignacion
    {
        return DB::transaction(function () use ($pedido, $repartidor, $motivo): PedidoAsignacion {
            $bloqueado = Pedido::query()->whereKey($pedido->getKey())->lockForUpdate()->sole();
            $oferta = $this->ofertaPorResponder($bloqueado, $repartidor);

            $oferta->forceFill([
                'estado' => PedidoAsignacion::RECHAZADO,
                'motivo' => $motivo,
                'respondido_en' => $this->ahora(),
            ])->save();

            return $oferta->setRelation('pedido', $bloqueado);
        });
    }

    /**
     * La oferta del repartidor que espera su respuesta, la más reciente, con su fila bloqueada.
     *
     * @throws RespuestaNoPermitida
     */
    private function ofertaPorResponder(Pedido $pedido, Repartidor $repartidor): PedidoAsignacion
    {
        return $pedido->asignaciones()
            ->where('id_repartidores', $repartidor->getKey())
            ->where('estado', PedidoAsignacion::OFRECIDO)
            ->orderByDesc('id_pedido_asignaciones')
            ->lockForUpdate()
            ->first()
            ?? throw new RespuestaNoPermitida($pedido, $repartidor, self::YA_RESPONDIDA);
    }

    /**
     * En UTC, como toda fecha que se guarda.
     */
    private function ahora(): CarbonImmutable
    {
        return CarbonImmutable::now()->utc();
    }
}
