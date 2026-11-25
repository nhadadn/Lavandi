<?php

namespace App\Direcciones;

/**
 * Un punto del mapa, en grados decimales de WGS 84 (SRID 4326): el que da la geocodificación de una dirección escrita
 * o el pin de una marcada en el mapa.
 */
final readonly class Punto
{
    public function __construct(
        public float $latitud,
        public float $longitud,
    ) {}
}
