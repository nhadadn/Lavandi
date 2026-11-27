<?php

namespace App\Http\Resources;

use App\Models\ReporteSoporte;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un reporte de Soporte como lo ve quien lo mandó. La forma exacta está en
 * App\Http\Controllers\Api\ReportesDeSoporteController.
 *
 * No lleva la llave interna, la cuenta ni la plaza: el reporte no tiene identificador público, y la API no lo expone
 * (ADR-0016, regla 5).
 *
 * @mixin ReporteSoporte
 */
class ReporteResource extends JsonResource
{
    /**
     * @return array{descripcion: string, pedido: string|null, estado: string, recibido_en: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'descripcion' => $this->mensaje,
            'pedido' => $this->pedido?->id_publico,
            'estado' => $this->estado,
            'recibido_en' => $this->created_at->toIso8601ZuluString(),
        ];
    }
}
