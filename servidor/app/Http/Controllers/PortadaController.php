<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

/**
 * La portada pública de la landing, con las 12 secciones del A.2 en su orden (NSV-2026-001-v8.pdf:15:ANEXO A, Sección
 * A.2; ADR-0011). Se abre sin iniciar sesión.
 *
 * Escenarios: landing-E01 y landing-E02 · Spec: openspec/changes/landing/specs/landing/spec.md · Tarea 1.1
 */
class PortadaController extends Controller
{
    public function __invoke(): View
    {
        // La página, para su descripción para buscadores (medicion-E07).
        return view('portada.index', ['pagina' => 'portada']);
    }
}
