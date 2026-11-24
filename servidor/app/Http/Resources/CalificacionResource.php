<?php

namespace App\Http\Resources;

use App\Models\Calificacion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * La calificación del servicio de un pedido, como la ve el usuario que la hizo: sus estrellas y su comentario tal como lo
 * escribió (historial-E06 y E07). No lleva la llave interna, ni la cuenta, ni la lavandería, ni la plaza. La forma exacta
 * está en App\Http\Controllers\Api\CalificacionesController.
 *
 * @mixin Calificacion
 */
class CalificacionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'estrellas' => $this->estrellas,
            'comentario' => $this->comentario,
        ];
    }
}
