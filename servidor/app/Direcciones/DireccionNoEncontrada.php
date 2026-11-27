<?php

namespace App\Direcciones;

use RuntimeException;

/**
 * El servicio de geocodificación no encontró la dirección escrita. El servidor no le inventa un punto y no la guarda;
 * la app ofrece marcarla en el mapa (direcciones-E03).
 */
final class DireccionNoEncontrada extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('El servicio de geocodificación no encontró la dirección.');
    }
}
