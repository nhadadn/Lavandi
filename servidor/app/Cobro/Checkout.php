<?php

namespace App\Cobro;

use App\Models\Pago;

/**
 * El checkout de un cobro: el cobro, ya ligado a su orden en la pasarela, y la URL que abre la app (pago-E01).
 */
final readonly class Checkout
{
    public function __construct(
        public Pago $cobro,
        public string $url,
    ) {}
}
