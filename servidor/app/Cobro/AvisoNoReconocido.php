<?php

namespace App\Cobro;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * El aviso no es uno que la pasarela mande: no es JSON o le falta su identificador de evento. No se guarda; responde
 * 400 y queda en el registro con su motivo.
 */
final class AvisoNoReconocido extends RuntimeException
{
    public function render(): JsonResponse
    {
        return response()->json(['message' => 'El aviso no se reconoce.'], 400);
    }
}
