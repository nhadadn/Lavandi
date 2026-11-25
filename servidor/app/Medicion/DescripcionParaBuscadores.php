<?php

namespace App\Medicion;

/**
 * La descripción para buscadores de cada página pública de la landing (NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2;
 * medicion-E07), tomada de config/landing.php. Los textos son de EL CLIENTE (DD-117):
 *
 * - configurado, va tal cual, sin los espacios de los lados;
 * - sin texto, fuera de producción, un marcador de contenido de prueba, marcado como tal y propio de la página, como los
 *   de la portada;
 * - sin texto, en producción, ninguno: no se inventa una descripción, y las páginas de los documentos legales no admiten
 *   texto de prueba en producción (textos-legales-E02).
 *
 * Escenario: medicion-E07 · Spec: openspec/changes/medicion-de-la-landing/specs/medicion/spec.md · Tarea 2.1
 */
final class DescripcionParaBuscadores
{
    /** Las páginas públicas, por su nombre, con lo que dice su marcador de prueba. */
    private const PAGINAS = [
        'portada' => 'la portada',
        'aviso-de-privacidad' => 'el aviso de privacidad',
        'terminos' => 'los términos y condiciones',
        'borrado-de-cuenta' => 'la página del borrado de la cuenta',
    ];

    /**
     * La descripción de la página; ninguna si la página no es pública o, en producción, si no tiene texto.
     */
    public function de(?string $pagina): ?string
    {
        if ($pagina === null || ! array_key_exists($pagina, self::PAGINAS)) {
            return null;
        }

        $texto = config("landing.descripciones.{$pagina}");

        if (is_string($texto) && trim($texto) !== '') {
            return trim($texto);
        }

        return app()->isProduction() ? null : 'Contenido de prueba: descripción de '.self::PAGINAS[$pagina].' para buscadores.';
    }
}
