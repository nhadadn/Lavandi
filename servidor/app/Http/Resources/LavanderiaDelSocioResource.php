<?php

namespace App\Http\Resources;

use App\Lavanderias\FotosDeLaLavanderia;
use App\Models\Lavanderia;
use App\Models\LavanderiaHorario;
use App\Models\LavanderiaServicio;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * La lavandería como la ve su socio en la app: lo que registró y edita, nombre, dirección, foto, servicios con su precio
 * y ventanas (alta-lavanderias-E01, E18 y E19). La forma exacta está en
 * App\Http\Controllers\Api\LavanderiaDelSocioController.
 *
 * No lleva llaves internas, ni la plaza, ni la ruta de la foto: la foto sale con su URL firmada. Tampoco lleva su
 * estado: lo que ve la lavandería suspendida o dada de baja lo fija DD-104 (alta-lavanderias-E16).
 *
 * @mixin Lavanderia
 */
class LavanderiaDelSocioResource extends JsonResource
{
    /**
     * La lavandería con lo que el recurso lee: el municipio con su estado, los servicios activos con su tipo y su
     * unidad, las ventanas en orden y las fotos.
     */
    public static function cargar(Lavanderia $lavanderia): Lavanderia
    {
        return $lavanderia->load([
            'municipio.entidadFederativa',
            'servicios' => fn (HasMany $servicios) => $servicios
                ->where('activo', true)
                ->whereHas('tipo', fn (Builder $tipo) => $tipo->where('activo', true))
                ->with(['tipo', 'unidad'])
                ->orderBy('id_tipos_servicio'),
            'horarios' => fn (HasMany $horarios) => $horarios
                ->orderBy('dia_semana')
                ->orderBy('hora_inicio')
                ->orderBy('id_lavanderia_horarios'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $fotos = app(FotosDeLaLavanderia::class);
        $principal = $fotos->principal($this->resource);

        return [
            'id_publico' => $this->id_publico,
            'nombre' => $this->nombre,
            'direccion' => [
                'calle' => $this->calle,
                'numero' => $this->numero,
                'colonia' => $this->colonia,
                'codigo_postal' => $this->codigo_postal,
                // Las lavanderías de prueba no tienen municipio del catálogo.
                'municipio' => $this->municipio === null ? null : [
                    'codigo' => $this->municipio->codigo,
                    'nombre' => $this->municipio->nombre,
                    'estado' => $this->municipio->entidadFederativa?->nombre,
                ],
            ],
            'foto' => $principal === null ? null : $fotos->url($principal),
            'servicios' => $this->servicios->map(fn (LavanderiaServicio $servicio): array => [
                'tipo' => $servicio->tipo->codigo,
                'nombre' => $servicio->tipo->nombre,
                // Texto con dos decimales, en pesos: el dinero nunca viaja como flotante.
                'precio' => $servicio->precio,
                'unidad' => [
                    'codigo' => $servicio->unidad->codigo,
                    'nombre' => $servicio->unidad->nombre,
                ],
            ])->values()->all(),
            'ventanas' => $this->horarios->map(fn (LavanderiaHorario $ventana): array => [
                'dia_semana' => $ventana->dia_semana,
                // La columna es time (HH:MM:SS); la app la recibe como la manda, HH:MM.
                'hora_inicio' => substr($ventana->hora_inicio, 0, 5),
                'hora_fin' => substr($ventana->hora_fin, 0, 5),
            ])->values()->all(),
        ];
    }
}
