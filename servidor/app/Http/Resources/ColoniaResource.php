<?php

namespace App\Http\Resources;

use App\Models\Colonia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una colonia del catálogo, con su tipo y su municipio, para que la app la ofrezca en la lista de su código postal
 * (direcciones-E19). La forma exacta está en App\Http\Controllers\Api\CatalogoDelTerritorioController. Espera la
 * colonia con su municipio cargado.
 *
 * @mixin Colonia
 */
class ColoniaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'nombre' => $this->nombre,
            'tipo' => $this->tipo,
            'municipio' => [
                'codigo' => $this->municipio->codigo,
                'nombre' => $this->municipio->nombre,
            ],
        ];
    }
}
