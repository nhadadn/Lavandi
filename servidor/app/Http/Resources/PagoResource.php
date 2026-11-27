<?php

namespace App\Http\Resources;

use App\Models\Pago;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un pago como lo ve la cuenta que hizo el pedido: su identificador público, su pedido, su tipo, su método, su estado,
 * su monto y cuándo se pagó (openspec/changes/pago/design.md, "Contrato de la API").
 *
 * No lleva la llave interna, la plaza, la pasarela ni la referencia de su orden: la app no las necesita, y la orden
 * solo sirve entre servidor/ y la pasarela. Nunca lleva un dato de tarjeta, porque la plataforma no tiene ninguno
 * (pago-E05).
 *
 * @mixin Pago
 */
class PagoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_publico' => $this->id_publico,
            'pedido' => $this->pedido->id_publico,
            'tipo' => $this->tipo,
            'metodo' => $this->metodo,
            'estado' => $this->estado,
            'monto' => $this->monto,
            'pagado_en' => $this->pagado_en?->utc()->toIso8601ZuluString(),
        ];
    }
}
