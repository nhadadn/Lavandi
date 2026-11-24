<?php

namespace App\Direcciones;

use RuntimeException;
use Throwable;

/**
 * El servicio de geocodificación no se pudo consultar. No es lo mismo que no encontrar la dirección: aquí no se sabe
 * si existe, así que tampoco se guarda (direcciones-E03).
 */
final class GeocodificacionNoDisponible extends RuntimeException
{
    public static function porque(string $motivo, ?Throwable $causa = null): self
    {
        return new self("La geocodificación no está disponible: {$motivo}.", previous: $causa);
    }
}
