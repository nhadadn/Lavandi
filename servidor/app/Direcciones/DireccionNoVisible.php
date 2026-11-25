<?php

namespace App\Direcciones;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * La dirección pedida no existe, está borrada o es de otra cuenta. Las tres responden igual, 404 y sin ningún dato:
 * quien no debe verla no se entera de que existe (direcciones-E14; ADR-0019, decisión 3).
 *
 * Es un error de quien pide, no del servidor: no queda en el registro.
 */
final class DireccionNoVisible extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('No existe la dirección.');
    }

    public function report(): void
    {
        //
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => 'No existe la dirección.'], 404);
    }
}
