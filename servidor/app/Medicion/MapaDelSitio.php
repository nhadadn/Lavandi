<?php

namespace App\Medicion;

use XMLWriter;

/**
 * El mapa del sitio de la landing, con todas sus páginas públicas, y el archivo de robots que lo declara, para que la
 * consola de búsqueda de Google lo lea (NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2; ADR-0011; medicion-E08).
 *
 * Las direcciones son absolutas y salen de los nombres de las rutas, así que usan el dominio de la configuración
 * (APP_URL), no el de la consulta (base-E10; App\Http\Middleware\UsarDireccionConfigurada). Una página pública nueva
 * entra aquí, en PAGINAS.
 *
 * Escenario: medicion-E08 · Spec: openspec/changes/medicion-de-la-landing/specs/medicion/spec.md · Tarea 2.1
 */
final class MapaDelSitio
{
    /** Las páginas públicas, por el nombre de su ruta: la portada y los dos documentos legales. */
    private const PAGINAS = [
        'portada',
        'textos-legales.aviso-de-privacidad',
        'textos-legales.terminos',
    ];

    /** El espacio de nombres del protocolo de los mapas del sitio. */
    private const ESPACIO = 'http://www.sitemaps.org/schemas/sitemap/0.9';

    /**
     * La dirección absoluta de cada página pública.
     *
     * @return list<string>
     */
    public function direcciones(): array
    {
        return array_map(fn (string $ruta): string => route($ruta), self::PAGINAS);
    }

    /**
     * El mapa del sitio, en el XML del protocolo de sitemaps.org.
     */
    public function xml(): string
    {
        $xml = new XMLWriter;
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('urlset');
        $xml->writeAttribute('xmlns', self::ESPACIO);

        foreach ($this->direcciones() as $direccion) {
            $xml->startElement('url');
            $xml->writeElement('loc', $direccion);
            $xml->endElement();
        }

        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }

    /**
     * El archivo de robots: no le cierra el sitio a ningún buscador y declara la dirección del mapa del sitio.
     */
    public function robots(): string
    {
        return implode("\n", [
            'User-agent: *',
            'Disallow:',
            '',
            'Sitemap: '.route('medicion.mapa-del-sitio'),
            '',
        ]);
    }
}
