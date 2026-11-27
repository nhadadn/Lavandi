<?php

namespace App\Landing;

use App\Models\Testimonio;

/**
 * Las tarjetas de "Testimonios" (NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2, sección 8: "3 a 5 tarjetas de
 * opiniones de usuarios beta (marcador de posición en lanzamiento)"; design.md, lectura 2):
 *
 * - los testimonios activos, en el orden fijado en el panel, hasta cinco (landing-E15);
 * - con menos de tres activos, se completan tres con marcadores de posición (landing-E16), cuyo texto es
 *   config('landing.testimonios.marcador'), que decide EL CLIENTE (DD-117).
 */
final class TestimoniosDeLaPortada
{
    public const MINIMO = 3;

    public const MAXIMO = 5;

    /**
     * Cada tarjeta es un testimonio, o null si es un marcador de posición.
     *
     * @return list<Testimonio|null>
     */
    public function tarjetas(): array
    {
        $activos = Testimonio::query()->activosEnOrden()->limit(self::MAXIMO)->get()->all();

        return array_pad($activos, self::MINIMO, null);
    }
}
