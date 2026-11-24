<?php

namespace App\Direcciones;

use App\Models\Direccion;

/**
 * La recogida y la entrega que eligió la cuenta, leídas con su punto (Direccion::conCoordenadas()). Sin entrega, la
 * entrega es la misma dirección de la recogida (direcciones-E09).
 */
final readonly class DireccionesDelPedido
{
    public function __construct(
        public Direccion $recogida,
        public Direccion $entrega,
        public bool $entregaEnLaRecogida,
    ) {}
}
