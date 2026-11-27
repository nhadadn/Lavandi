<?php

namespace App\Cobro;

/**
 * La orden que crea la pasarela para un cobro: su referencia, que se guarda en pagos.id_pasarela_orden, y la URL de su
 * checkout, que abre la app (pago-E01; ADR-0010, decisión 1). No lleva ningún dato de tarjeta.
 */
final readonly class OrdenDePasarela
{
    public function __construct(
        public string $id,
        public string $urlDelCheckout,
    ) {}
}
