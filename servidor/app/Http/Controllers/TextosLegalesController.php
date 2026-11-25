<?php

namespace App\Http\Controllers;

use App\TextosLegales\DocumentosLegales;
use Illuminate\Contracts\View\View;

/**
 * Las páginas públicas del aviso de privacidad y de los términos y condiciones, cada una en su dirección fija y sin
 * iniciar sesión (NSV-2026-001-v8.pdf:31:ANEXO B, Sección B.7; openspec/changes/textos-legales/). La ruta dice qué
 * documento es; su texto vigente lo da App\TextosLegales\DocumentosLegales.
 *
 *     GET /aviso-de-privacidad     el aviso de privacidad        (textos-legales.aviso-de-privacidad)
 *     GET /terminos                los términos y condiciones    (textos-legales.terminos)
 *
 * Las dos responden 200. Con el documento cargado, muestran su texto completo, sin cambios (textos-legales-E01). Sin él,
 * en producción dicen que todavía no está disponible, sin ningún texto de ejemplo (textos-legales-E02); fuera de
 * producción muestran un marcador de contenido de prueba, marcado como tal.
 *
 * Escenarios: textos-legales-E01 y textos-legales-E02 · Spec: openspec/changes/textos-legales/specs/textos-legales/spec.md
 * · Tarea 1.1
 */
class TextosLegalesController extends Controller
{
    public function __invoke(DocumentosLegales $documentos, string $documento): View
    {
        return view('textos-legales.documento', [
            // La página, para su descripción para buscadores (medicion-E07).
            'pagina' => $documento,
            'titulo' => $documentos->titulo($documento),
            'texto' => $documentos->vigente($documento),
            'enProduccion' => app()->isProduction(),
        ]);
    }
}
