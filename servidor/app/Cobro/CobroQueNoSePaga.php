<?php

namespace App\Cobro;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * El cobro del pedido no abre checkout: todavía no existe, ya está pagado o ya no se puede pagar (pago-E01). Responde
 * 409 con el motivo y no toca el cobro.
 */
final class CobroQueNoSePaga extends RuntimeException
{
    /**
     * El pedido todavía no tiene cobro: lo crea pago-E14 cuando DD-012 fije el monto.
     */
    public static function sinCobro(): self
    {
        return new self('El cobro de este pedido todavía no está listo.');
    }

    public static function yaPagado(): self
    {
        return new self('Este pedido ya está pagado.');
    }

    /**
     * Vencido o cancelado: lo que pase con el pedido lo fijan DD-056, DD-012 y DD-016.
     */
    public static function yaNoSePaga(): self
    {
        return new self('Este cobro ya no se puede pagar.');
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
