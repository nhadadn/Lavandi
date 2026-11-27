<?php

namespace App\Direcciones;

use App\Models\Municipio;

/**
 * Elige el servicio de geocodificación de cada entorno (direcciones-E15): con la llave de Google, el real, en cualquier
 * entorno; sin llave y fuera de producción, el de prueba; en producción sin llave, el real, que responde que no está
 * disponible (direcciones-E03 y su 503).
 */
final class GeocodificadorSegunEntorno implements Geocodificador
{
    public function __construct(
        private readonly GeocodificadorDeGoogle $real,
        private readonly GeocodificadorDePrueba $deprueba,
    ) {}

    public function geocodificar(string $texto, Municipio $municipio): ?Punto
    {
        $conLlave = (string) config('services.geocodificacion.llave') !== '';

        if ($conLlave || app()->isProduction()) {
            return $this->real->geocodificar($texto, $municipio);
        }

        return $this->deprueba->geocodificar($texto, $municipio);
    }
}
