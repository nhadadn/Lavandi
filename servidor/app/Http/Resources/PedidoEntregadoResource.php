<?php

namespace App\Http\Resources;

use App\Models\Pedido;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un pedido en el historial de la lavandería: su folio y cuándo se entregó (historial-lavanderia-E01;
 * openspec/changes/historial-de-la-lavanderia/specs/historial-lavanderia/spec.md). La forma exacta está en
 * App\Http\Controllers\Api\HistorialDeLaLavanderiaController.
 *
 * La fecha es la del paso a Entregado en la hora de la plaza (design.md, decisiones 1 y 3). No lleva el monto ni la
 * calificación, que esperan DD-013, DD-020, DD-012 y DD-052 (historial-lavanderia-E03 y E04), ni datos del usuario, ni
 * la llave interna ni la plaza.
 *
 * Espera el pedido leído con Pedido::entregados(), que trae entregado_en, y su plaza cargada.
 *
 * @mixin Pedido
 */
class PedidoEntregadoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_publico' => $this->id_publico,
            'folio' => $this->folio,
            'entregado_en' => $this->entregado_en->toIso8601ZuluString(),
            'fecha' => $this->plaza->horaLocal($this->entregado_en)->toDateString(),
        ];
    }
}
