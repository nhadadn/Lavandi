<?php

namespace App\Cobro;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * El pago que se pidió no existe, o la cuenta no lo ve porque es de un pedido de otra: las dos responden igual, 404 y sin
 * ningún dato (ADR-0019, decisión 3).
 */
final class PagoNoVisible extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('No existe el pago.');
    }

    public function report(): void
    {
        //
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => 'No existe el pago.'], 404);
    }
}
