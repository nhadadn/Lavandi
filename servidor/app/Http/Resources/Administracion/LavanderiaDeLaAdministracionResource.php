<?php

namespace App\Http\Resources\Administracion;

use App\Http\Resources\LavanderiaDelSocioResource;
use App\Lavanderias\EstadoDeLaLavanderia;
use App\Lavanderias\FotosDeLaLavanderia;
use App\Models\Lavanderia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * La lavandería como la ve la administración en la app (administracion-app-E13; NSV-2026-001-v8.pdf:19:ANEXO A, Sección
 * A.3, Módulo D #1). La forma exacta está en App\Http\Controllers\Api\Administracion\LavanderiasController.
 *
 * - En la lista, su resumen: su nombre, su estado y su plaza (resumen()), como la tabla del panel.
 * - En la ficha, además, sus datos editables tal como los ve y los edita su socio en la app, con el mismo recurso que
 *   usa la edición del panel (App\Http\Resources\LavanderiaDelSocioResource), y las transiciones de estado que permite
 *   ahora. Cuáles permite lo decide App\Lavanderias\EstadoDeLaLavanderia con sus puede…(), no este recurso (ADR-0022,
 *   decisión 9). Y la marca de las fotos de esta lectura, que la app devuelve al editar la lista de fotos
 *   (FotosDeLaLavanderia::marcaDeLaLectura(); administracion-app-E14).
 *
 * Como el del socio, no lleva llaves internas, ni la ruta de una foto, ni la cuenta de su socio (ADR-0016, regla 5). La
 * plaza va por su nombre, como en el recurso del repartidor.
 *
 * @mixin Lavanderia
 */
class LavanderiaDeLaAdministracionResource extends JsonResource
{
    /** Suspender: POST …/suspension (alta-lavanderias-E14). */
    public const SUSPENDER = 'suspender';

    /** Levantar la suspensión: DELETE …/suspension (alta-lavanderias-E15). */
    public const LEVANTAR_SUSPENSION = 'levantar_suspension';

    /** Dar de baja: POST …/baja (alta-lavanderias-E17). */
    public const DAR_DE_BAJA = 'dar_de_baja';

    /**
     * La lavandería con lo que lee la ficha: lo del recurso del socio y su plaza.
     */
    public static function cargar(Lavanderia $lavanderia): Lavanderia
    {
        return LavanderiaDelSocioResource::cargar($lavanderia)->load('plaza');
    }

    /**
     * La lavandería en la lista: su id_publico, su nombre, su estado y su plaza. Espera cargada la plaza.
     *
     * @return array{id_publico: string, nombre: string, estado: string, plaza: array{nombre: string}}
     */
    public static function resumen(Lavanderia $lavanderia): array
    {
        return [
            'id_publico' => $lavanderia->id_publico,
            'nombre' => $lavanderia->nombre,
            'estado' => $lavanderia->estado,
            'plaza' => ['nombre' => $lavanderia->plaza->nombre],
        ];
    }

    /**
     * La ficha: lo del recurso del socio, el resumen, las transiciones, en el orden de los botones del panel
     * (App\Filament\Resources\Lavanderias\Actions\AccionesDeEstado), y la marca de las fotos leídas. Espera la
     * lavandería de cargar().
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $estado = app(EstadoDeLaLavanderia::class);

        return [
            ...(new LavanderiaDelSocioResource($this->resource))->toArray($request),
            ...self::resumen($this->resource),
            'transiciones' => array_keys(array_filter([
                self::SUSPENDER => $estado->puedeSuspenderse($this->resource),
                self::LEVANTAR_SUSPENSION => $estado->puedeLevantarseLaSuspension($this->resource),
                self::DAR_DE_BAJA => $estado->puedeDarseDeBaja($this->resource),
            ])),
            'fotos_leidas' => app(FotosDeLaLavanderia::class)->marcaDeLaLectura($this->resource),
        ];
    }
}
