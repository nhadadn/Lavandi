<?php

namespace App\Http\Resources;

use App\Lavanderias\FotosDeLaLavanderia;
use App\Models\Calificacion;
use App\Models\Lavanderia;
use App\Models\LavanderiaFoto;
use App\Models\LavanderiaServicio;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * El perfil de una lavandería como lo ve la app (perfil-lavanderia-E01, E03, E05, E07 y E08). La forma exacta está en
 * App\Http\Controllers\Api\PerfilDeLavanderiaController.
 *
 * Espera la lavandería con sus fotos en orden, sus servicios activos (con su tipo y su unidad) y sus calificaciones ya
 * cargados. No lleva la llave interna, ni la ruta de una foto como campo, ni nada de quien calificó.
 *
 * @mixin Lavanderia
 */
class PerfilDeLavanderiaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $promedio = $this->calificaciones->avg('estrellas');
        $deLaLavanderia = app(FotosDeLaLavanderia::class);

        return [
            'id_publico' => $this->id_publico,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            // Cada foto, en su orden, solo con su URL firmada que vence (perfil-lavanderia-E03 y E04).
            'fotos' => $this->fotos->map(fn (LavanderiaFoto $foto): array => $deLaLavanderia->url($foto))->values()->all(),
            'servicios' => $this->servicios->map(fn (LavanderiaServicio $servicio): array => [
                'codigo' => $servicio->tipo->codigo,
                'nombre' => $servicio->tipo->nombre,
                // Texto con dos decimales, en pesos: el dinero nunca viaja como flotante.
                'precio' => $servicio->precio,
                'unidad' => [
                    'codigo' => $servicio->unidad->codigo,
                    'nombre' => $servicio->unidad->nombre,
                ],
            ])->values()->all(),
            // Vacío, no cero, si no tiene calificaciones (perfil-lavanderia-E07).
            'calificacion_promedio' => $promedio === null ? null : round((float) $promedio, 2),
            'numero_de_calificaciones' => $this->calificaciones->count(),
            // Sin nombre, correo, teléfono, foto ni identificador de quien calificó (perfil-lavanderia-E08).
            'resenas' => $this->calificaciones->map(fn (Calificacion $calificacion): array => [
                'estrellas' => $calificacion->estrellas,
                'comentario' => $calificacion->comentario,
                'fecha' => $calificacion->created_at->toIso8601ZuluString(),
            ])->values()->all(),
        ];
    }
}
