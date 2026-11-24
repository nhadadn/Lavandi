<?php

namespace Tests\Feature\Landing\Soporte;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Testing\TestResponse;

/**
 * Lo que comparten las pruebas de la portada (openspec/changes/landing/): pedirla sin sesión y leer su HTML como
 * documento, para buscar por sección y por atributo en vez de por texto suelto.
 *
 * Las pruebas no compilan los estilos: withoutVite() deja fuera las etiquetas de Vite, que en la integración continua no
 * tienen manifiesto.
 */
trait Portada
{
    /** Las 12 secciones del A.2, en su orden (NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2), como las marca data-seccion. */
    protected const SECCIONES = [
        'encabezado',
        'como-funciona',
        'beneficios-usuarios',
        'descarga',
        'lavanderias',
        'beneficios-socios',
        'cobertura',
        'testimonios',
        'preguntas-frecuentes',
        'prensa',
        'contacto',
        'pie-legal',
    ];

    protected function pedirPortada(): TestResponse
    {
        $this->withoutVite();

        return $this->get('/');
    }

    /**
     * El HTML de la portada como documento, con XPath. La portada debe responder 200.
     */
    protected function portada(): DOMXPath
    {
        $respuesta = $this->pedirPortada()->assertOk();

        return $this->documento((string) $respuesta->getContent());
    }

    protected function documento(string $html): DOMXPath
    {
        $documento = new DOMDocument;
        libxml_use_internal_errors(true);
        $documento->loadHTML('<?xml encoding="utf-8"?>'.$html);
        libxml_clear_errors();

        return new DOMXPath($documento);
    }

    /**
     * Los elementos que cumplen la expresión dentro de una sección de la portada.
     *
     * @return list<DOMElement>
     */
    protected function en(DOMXPath $portada, string $seccion, string $expresion = '.'): array
    {
        $contexto = $portada->query(sprintf('//*[@data-seccion="%s"]', $seccion))->item(0);
        $this->assertInstanceOf(DOMElement::class, $contexto, "La sección {$seccion} existe.");

        $encontrados = [];
        foreach ($portada->query($expresion, $contexto) as $nodo) {
            if ($nodo instanceof DOMElement) {
                $encontrados[] = $nodo;
            }
        }

        return $encontrados;
    }

    /**
     * El texto de un elemento, con los espacios en blanco juntados.
     */
    protected function texto(DOMElement $elemento): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $elemento->textContent));
    }

    /**
     * Los textos de los elementos que cumplen la expresión dentro de una sección.
     *
     * @return list<string>
     */
    protected function textos(DOMXPath $portada, string $seccion, string $expresion): array
    {
        return array_map(fn (DOMElement $elemento): string => $this->texto($elemento), $this->en($portada, $seccion, $expresion));
    }
}
