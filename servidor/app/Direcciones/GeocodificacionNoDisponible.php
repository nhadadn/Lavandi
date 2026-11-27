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
    /**
     * Lo que se dice a quien guarda la dirección de una lavandería cuando el servicio no respondió, escrito una sola vez:
     * el registro y la edición de la app del socio, la edición de la API de la administración y la del panel. Las
     * direcciones del usuario dicen otro, porque ofrecen marcarla en el mapa.
     */
    public const MENSAJE = 'No pudimos ubicar la dirección. Inténtalo más tarde.';

    /** El motivo del 503 de la lavandería, con que la app lo distingue de los otros errores de la dirección. */
    public const MOTIVO = 'geocodificacion_no_disponible';

    public static function porque(string $motivo, ?Throwable $causa = null): self
    {
        return new self("La geocodificación no está disponible: {$motivo}.", previous: $causa);
    }
}
