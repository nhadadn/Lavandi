<?php

namespace App\Http\Controllers;

use App\Medicion\MapaDelSitio;
use Illuminate\Http\Response;

/**
 * El mapa del sitio y el archivo de robots de la landing, públicos y sin sesión (NSV-2026-001-v8.pdf:15:ANEXO A, Sección
 * A.2; ADR-0011). Los dos los declara App\Autorizacion\Autorizacion::rutasSinSesion().
 *
 *     GET /sitemap.xml    el mapa del sitio, con las páginas públicas    (medicion.mapa-del-sitio)
 *     GET /robots.txt     el archivo de robots, que declara el mapa      (medicion.robots)
 *
 * Escenario: medicion-E08 · Spec: openspec/changes/medicion-de-la-landing/specs/medicion/spec.md · Tarea 2.1
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
