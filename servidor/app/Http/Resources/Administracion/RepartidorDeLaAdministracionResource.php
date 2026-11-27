<?php

namespace App\Http\Resources\Administracion;

use App\Http\Resources\RepartidorResource;
use App\Models\Repartidor;
use App\Repartidores\EstadoDelRepartidor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/**
 * El repartidor como lo ve la administración en la app (administracion-app-E19; NSV-2026-001-v8.pdf:19:ANEXO A, Sección
 * A.3, Módulo D #2). La forma exacta está en App\Http\Controllers\Api\Administracion\RepartidoresController.
 *
 * - En la lista, su resumen: su nombre, su estado y su plaza (resumen()), como la tabla del panel.
 * - En la ficha, sus cinco datos del registro, su estado y su plaza tal como los ve él en su perfil, con el mismo
 *   recurso (App\Http\Resources\RepartidorResource), salvo si opera, y las transiciones de estado que permite ahora.
 *   Cuáles permite lo deciden los puede…() de App\Repartidores\EstadoDelRepartidor, los mismos que muestran u ocultan
 *   los botones del panel, no este recurso (ADR-0022, decisión 9). Van en la misma forma que las de la lavandería
 *   (openspec/changes/administracion-en-la-app/design.md, sección 4, F-D01 y F-D02).
 *
 * Como el de su perfil, nunca lleva la contraseña, las llaves internas ni la ruta de su foto: la foto sale solo como
 * URL firmada que vence (ADR-0006). Tampoco su posición (DD-077).
 *
 * @mixin Repartidor
 */
class RepartidorDeLaAdministracionResource extends JsonResource
{
    /** Lo que la lista y la ficha muestran del repartidor, para cargarlo junto. */
    public const RELACIONES = ['cuenta', 'plaza'];

    /** Dar de alta en una plaza: POST …/alta (repartidor-E05). */
    public const DAR_DE_ALTA = 'dar_de_alta';

    /** Suspender: POST …/suspension (repartidor-E06). */
    public const SUSPENDER = 'suspender';

    /** Levantar la suspensión: DELETE …/suspension (repartidor-E06). */
    public const LEVANTAR_SUSPENSION = 'levantar_suspension';

    /** Dar de baja: POST …/baja (repartidor-E07 y E31). */
    public const DAR_DE_BAJA = 'dar_de_baja';

    /**
     * El repartidor con lo que muestra la ficha, ya cargado.
     */
    public static function delRepartidor(Repartidor $repartidor): self
    {
        return new self($repartidor->loadMissing(self::RELACIONES));
    }

    /**
     * El repartidor en la lista: su id_publico, su nombre, su estado y su plaza, o ninguna. Espera cargadas su cuenta y
     * su plaza (RELACIONES).
     *
     * @return array{id_publico: string, nombre: string, estado: string, plaza: array{nombre: string}|null}
     */
    public static function resumen(Repartidor $repartidor): array
    {
        return [
            'id_publico' => $repartidor->id_publico,
            'nombre' => $repartidor->cuenta->nombre,
            'estado' => $repartidor->estado,
            'plaza' => $repartidor->plaza === null ? null : ['nombre' => $repartidor->plaza->nombre],
        ];
    }

    /**
     * La ficha: lo del recurso de su perfil, sin si opera, y las transiciones, en el orden de los botones del panel
     * (App\Filament\Resources\Repartidores\Actions\AccionesDeEstado::todas()).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $estado = app(EstadoDelRepartidor::class);

        return [
            ...Arr::except((new RepartidorResource($this->resource))->toArray($request), ['puede_operar']),
            'transiciones' => array_keys(array_filter([
                self::DAR_DE_ALTA => $estado->puedeDarseDeAlta($this->resource),
                self::SUSPENDER => $estado->puedeSuspenderse($this->resource),
                self::LEVANTAR_SUSPENSION => $estado->puedeLevantarseLaSuspension($this->resource),
                self::DAR_DE_BAJA => $estado->puedeDarseDeBaja($this->resource),
            ])),
        ];
    }
}
