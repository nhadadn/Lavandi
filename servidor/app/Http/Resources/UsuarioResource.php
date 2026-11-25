<?php

namespace App\Http\Resources;

use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * La cuenta como la ve la app: su identificador público, su correo y su rol. Nunca la contraseña ni su hash, ni la
 * llave interna (registro-E06; ADR-0016, regla 5).
 *
 * @mixin Usuario
 */
class UsuarioResource extends JsonResource
{
    /**
     * @return array{id_publico: string, correo: string, rol: string|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'id_publico' => $this->id_publico,
            'correo' => $this->correo,
            // En la v1, una cuenta tiene un solo rol (DD-053).
            'rol' => $this->roles->first()?->codigo,
        ];
    }
}
