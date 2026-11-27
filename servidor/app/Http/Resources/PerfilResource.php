<?php

namespace App\Http\Resources;

use App\Identidad\FotoDePerfil;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * El perfil de la cuenta como lo ve la app. La forma exacta está en App\Http\Controllers\Api\PerfilController.
 *
 * Solo lleva lo que se lista aquí: nunca la contraseña, la llave interna ni un dato de tarjeta (perfil-usuario-E06;
 * ADR-0016, regla 5). La foto sale solo como URL firmada que vence, nunca su ruta (perfil-usuario-E03; ADR-0006).
 *
 * @mixin Usuario
 */
class PerfilResource extends JsonResource
{
    /**
     * @return array{id_publico: string, nombre: string|null, correo: string, foto: array{url: string, vence_en: string}|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'id_publico' => $this->id_publico,
            'nombre' => $this->nombre,
            'correo' => $this->correo,
            'foto' => app(FotoDePerfil::class)->url($this->resource),
        ];
    }
}
