<?php

namespace App\Direcciones;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * La recogida, la entrega o las dos no sirven para un pedido con la lavandería elegida (direcciones-E11 y E12). Lleva
 * el motivo de cada una que se rechazó, por su campo, y responde 422 con la forma que describe
 * App\Http\Controllers\Api\DireccionesController.
 *
 * Es un error de quien pide, no del servidor: no queda en el registro.
 */
final class DireccionesFueraDeServicio extends RuntimeException
{
    /** El mensaje para la persona de cada motivo. */
    public const MENSAJES = [
        ZonaDeServicio::FUERA_DE_LA_ZONA => 'La dirección está fuera de la zona de servicio.',
        ZonaDeServicio::OTRA_PLAZA => 'Esta lavandería no da servicio en esa dirección.',
        RecogidaYEntrega::PLAZA_DESACTUALIZADA => 'Esta dirección cambió de zona de servicio. Guárdala de nuevo para usarla.',
    ];

    /**
     * @param  array<string, string>  $motivos  El motivo de cada campo rechazado: "recogida", "entrega" o los dos.
     */
    public function __construct(public readonly array $motivos)
    {
        parent::__construct('La recogida o la entrega no sirven para la lavandería.');
    }

    public function report(): void
    {
        //
    }

    public function render(): JsonResponse
    {
        $errores = array_map(fn (string $motivo): array => [self::MENSAJES[$motivo]], $this->motivos);

        return response()->json([
            'message' => reset($errores)[0],
            'errors' => $errores,
            'motivos' => $this->motivos,
        ], 422);
    }
}
