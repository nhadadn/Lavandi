<?php

namespace App\Cobro;

use Carbon\CarbonImmutable;

/**
 * Un pago que acreditó la pasarela, como lo da su propia API, sin proveedor: el id de su orden, el monto, la comisión si
 * la trae y la fecha del pago (segunda adenda del 2026-11-22 de ADR-0010, decisión 6). Lo lee la conciliación del
 * Módulo D (App\Finanzas\ConciliacionConLaPasarela). No lleva ningún dato de tarjeta.
 *
 * Los montos van como texto con dos decimales, en pesos: el dinero nunca pasa por un flotante.
 */
final readonly class MovimientoDePasarela
{
    public function __construct(
        public string $idOrden,
        public string $monto,
        public ?string $comision,
        public CarbonImmutable $pagadoEn,
    ) {}
}
