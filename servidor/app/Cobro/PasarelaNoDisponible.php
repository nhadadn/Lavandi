<?php

namespace App\Cobro;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * No hay pasarela con qué cobrar: no está configurada, no existe, le falta su llave, no responde, o es la simulada
 * fuera de local y de las pruebas (pago-E17). Es un dato o un servicio que falta, no un error de quien pide: responde
 * 503 y queda en el registro con su motivo.
 */
final class PasarelaNoDisponible extends RuntimeException
{
    public function render(): JsonResponse
    {
        return response()->json(['message' => 'El pago no está disponible por ahora.'], 503);
    }
}
