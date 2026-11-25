<?php

namespace App\Medicion;

use XMLWriter;

/**
 * El mapa del sitio de la landing, con todas sus páginas públicas, y el archivo de robots que lo declara en producción,
 * para que la consola de búsqueda de Google lo lea (NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2; ADR-0011;
 * medicion-E08). Fuera de producción, el archivo de robots le cierra el sitio a todo buscador, para que solo producción
 * se indexe (NSV-2026-001-v8.pdf:14:ANEXO A, Sección A.2; adenda del 2026-11-24 de ADR-0012; medicion-E15).
 *
 * Las direcciones son absolutas y salen de los nombres de las rutas, así que usan el dominio de la configuración
 * (APP_URL), no el de la consulta (base-E10; App\Http\Middleware\UsarDireccionConfigurada). Una página pública nueva
 * entra aquí, en PAGINAS.
 *
 * Escenarios: medicion-E08 y medicion-E15 · Spec: openspec/changes/medicion-de-la-landing/specs/medicion/spec.md ·
 * Tareas 2.1 y 2.3
 */
final class MapaDelSitio
{
    /**
     * Las páginas públicas, por el nombre de su ruta: la portada, los dos documentos legales y la página para pedir el
     * borrado de la cuenta (textos-legales-E06).
     */
    private const PAGINAS = [
        'portada',
        'textos-legales.aviso-de-privacidad',
        'textos-legales.terminos',
        'textos-legales.borrado-de-cuenta',
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
     * El archivo de robots. En producción no le cierra el sitio a ningún buscador y declara la dirección del mapa del
     * sitio (medicion-E08); en cualquier otro entorno se lo cierra a todos, para que solo producción se indexe
     * (medicion-E15).
     */
    public function robots(): string
    {
        if (! app()->isProduction()) {
            return implode("\n", ['User-agent: *', 'Disallow: /', '']);
        }

        return implode("\n", [
            'User-agent: *',
            'Disallow:',
            '',
            'Sitemap: '.route('medicion.mapa-del-sitio'),
            '',
        ]);
    }
}
