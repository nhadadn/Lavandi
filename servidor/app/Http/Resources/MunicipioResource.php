<?php

namespace App\Http\Resources;

use App\Models\Municipio;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un municipio del catálogo del territorio, con su estado, como lo elige la app (direcciones-E17). La forma exacta está
 * en App\Http\Controllers\Api\CatalogoDelTerritorioController. Espera el municipio con su entidadFederativa cargada.
 *
 * @mixin Municipio
 */
class MunicipioResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'estado' => [
                'codigo' => $this->entidadFederativa->codigo,
                'nombre' => $this->entidadFederativa->nombre,
            ],
        ];
    }
}
