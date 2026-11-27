<?php

namespace App\Plazas;

use App\Models\Plaza;
use App\Models\Zona;

/**
 * Dónde cae un punto: la zona que lo contiene y su plaza, que está activa (plazas-E04).
 */
final readonly class PuntoEnPlaza
{
    public function __construct(
        public Zona $zona,
        public Plaza $plaza,
    ) {}
}
