<?php

namespace App\Lavanderias;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * La dirección de la lavandería no sirve: la geocodificación no la ubica, o su punto queda fuera de las zonas de
 * servicio activas del territorio (alta-lavanderias-E10; NSV-2026-001-v8.pdf:4:Cláusula I). Responde 422 en el campo de
 * la dirección, con su motivo, y no se guarda nada.
 *
 * Es un error de quien pide, no del servidor: no queda en el registro.
 */
final class DireccionNoUbicable extends RuntimeException
{
    public const NO_ENCONTRADA = 'direccion_no_encontrada';

    public const FUERA_DE_LA_ZONA = 'fuera_de_la_zona_de_servicio';

    /**
     * Al editar: la dirección nueva cae en una zona de otra plaza. La lavandería no cambia de plaza
     * (alta-lavanderias-E25), porque su historial y sus hijas van atados a su plaza por las llaves compuestas
     * (modelo-E06).
     */
    public const EN_OTRA_PLAZA = 'en_otra_plaza';

    /** El mensaje para la persona de cada motivo. */
    public const MENSAJES = [
        self::NO_ENCONTRADA => 'No encontramos la dirección. Revisa la calle, el número, la colonia y el código postal.',
        self::FUERA_DE_LA_ZONA => 'La dirección está fuera de las zonas de servicio.',
        self::EN_OTRA_PLAZA => 'La dirección nueva queda en otra plaza de servicio. Ese cambio todavía no se puede hacer.',
    ];

    public function __construct(public readonly string $motivo)
    {
        parent::__construct(self::MENSAJES[$motivo]);
    }

    public function report(): void
    {
        //
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => ['direccion' => [$this->getMessage()]],
            'motivo' => $this->motivo,
        ], 422);
    }
}
