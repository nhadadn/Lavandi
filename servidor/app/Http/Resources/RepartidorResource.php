<?php

namespace App\Http\Resources;

use App\Autorizacion\Autorizacion;
use App\Identidad\FotoDePerfil;
use App\Models\Repartidor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * El repartidor como lo ve en su módulo: sus cinco datos del registro, su estado y si ya opera (repartidor-E01, E05 y
 * E08). La forma exacta está en App\Http\Controllers\Api\RepartidorController.
 *
 * Nunca la contraseña, la llave interna, la de su cuenta ni la ruta de su foto: la foto sale solo como URL firmada que
 * vence (ADR-0006). Si opera lo dice el componente de autorización.
 *
 * @mixin Repartidor
 */
class RepartidorResource extends JsonResource
{
    /**
     * El repartidor con lo que muestra la respuesta, ya cargado.
     */
    public static function delRepartidor(Repartidor $repartidor): self
    {
        return new self($repartidor->loadMissing(['cuenta', 'plaza']));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_publico' => $this->id_publico,
            'nombre' => $this->cuenta->nombre,
            'telefono' => $this->cuenta->telefono,
            'correo' => $this->cuenta->correo,
            'foto' => app(FotoDePerfil::class)->url($this->cuenta),
            'tipo_vehiculo' => $this->tipo_vehiculo,
            'estado' => $this->estado,
            'puede_operar' => app(Autorizacion::class)->repartidorOpera($this->resource),
            'plaza' => $this->plaza === null ? null : ['nombre' => $this->plaza->nombre],
        ];
    }
}
