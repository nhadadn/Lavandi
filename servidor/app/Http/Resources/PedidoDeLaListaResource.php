<?php

namespace App\Http\Resources;

use App\Models\Pedido;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un pedido en el historial del usuario: su folio, su fecha, su lavandería y su estado (historial-E01;
 * openspec/changes/historial-y-calificacion/specs/historial/spec.md), y su calificación del servicio, si ya lo calificó
 * (historial-E06). La forma exacta está en App\Http\Controllers\Api\PedidosController.
 *
 * La fecha es la de la recolección en la hora de la plaza, la misma que "ventana.fecha" del resumen
 * (App\Http\Resources\PedidoResource; design.md, decisión 1). El estado se nombra como en el resumen. No lleva la llave
 * interna, ni la cuenta, ni la plaza.
 *
 * Espera cargados plaza, lavanderia, estado.paso y calificacionDelServicio.
 *
 * @mixin Pedido
 */
class PedidoDeLaListaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_publico' => $this->id_publico,
            'folio' => $this->folio,
            'fecha' => $this->plaza->horaLocal($this->ventana_inicio)->toDateString(),
            'lavanderia' => [
                'id_publico' => $this->lavanderia->id_publico,
                'nombre' => $this->lavanderia->nombre,
            ],
            'estado' => PedidoResource::estadoParaElUsuario($this->estado),
            'calificacion' => $this->calificacionDelServicio === null
                ? null
                : new CalificacionResource($this->calificacionDelServicio),
        ];
    }
}
