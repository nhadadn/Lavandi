<?php

namespace App\Lavanderias;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * La cuenta ya administra una lavandería: una cuenta de socio tiene a lo más una (vault/arquitectura/modelo-de-datos.md,
 * "usuarios ||--o| lavanderias"). El registro es para la cuenta que todavía no tiene la suya (alta-lavanderias-E01).
 *
 * Es un error de quien pide, no del servidor: no queda en el registro.
 */
final class CuentaConLavanderia extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Esta cuenta ya tiene una lavandería registrada.');
    }

    public function report(): void
    {
        //
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 409);
    }
}
