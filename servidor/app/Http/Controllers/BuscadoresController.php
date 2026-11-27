<?php

namespace App\Http\Controllers;

use App\Medicion\MapaDelSitio;
use Illuminate\Http\Response;

/**
 * El mapa del sitio y el archivo de robots de la landing, públicos y sin sesión (NSV-2026-001-v8.pdf:15:ANEXO A, Sección
 * A.2; ADR-0011). Los dos los declara App\Autorizacion\Autorizacion::rutasSinSesion().
 *
 *     GET /sitemap.xml    el mapa del sitio, con las páginas públicas    (medicion.mapa-del-sitio)
 *     GET /robots.txt     el archivo de robots: en producción declara    (medicion.robots)
 *                         el mapa; fuera de ella, cierra el sitio
 *
 * Que solo producción se deje indexar es la adenda del 2026-11-24 de ADR-0012 (NSV-2026-001-v8.pdf:14:ANEXO A, Sección
 * A.2).
 *
 * Escenarios: medicion-E08 y medicion-E15 · Spec: openspec/changes/medicion-de-la-landing/specs/medicion/spec.md ·
 * Tareas 2.1 y 2.3
 */
class BuscadoresController extends Controller
{
    public function mapaDelSitio(MapaDelSitio $mapa): Response
    {
        return response($mapa->xml(), 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(MapaDelSitio $mapa): Response
    {
        return response($mapa->robots(), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
