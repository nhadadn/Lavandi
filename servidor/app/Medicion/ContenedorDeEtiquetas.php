<?php

namespace App\Medicion;

/**
 * El contenedor de Google Tag Manager de las páginas públicas, por el que entran Google Analytics 4 y Meta Pixel
 * (NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2; ADR-0011). Su identificador sale de config/medicion.php, que lo lee del
 * .env de cada entorno.
 *
 * Solo vale un identificador con la forma de los de GTM, "GTM-" y letras mayúsculas o números: va dentro de un script de
 * la página, así que cualquier otra cosa se trata como si no hubiera identificador, y la página no mide (medicion-E02).
 *
 * Escenarios: medicion-E01 y medicion-E02 · Spec: openspec/changes/medicion-de-la-landing/specs/medicion/spec.md · Tarea
 * 1.1
 */
final class ContenedorDeEtiquetas
{
    /** La forma de un identificador de contenedor de GTM. */
    private const FORMA = '/^GTM-[A-Z0-9]{4,12}$/';

    /**
     * El identificador del contenedor de este entorno; ninguno si no está configurado o no tiene la forma de GTM.
     */
    public function identificador(): ?string
    {
        $identificador = config('medicion.gtm');

        if (! is_string($identificador)) {
            return null;
        }

        $identificador = trim($identificador);

        return preg_match(self::FORMA, $identificador) === 1 ? $identificador : null;
    }
}
