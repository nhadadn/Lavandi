<?php

namespace Tests\Feature\Medicion\Soporte;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

/**
 * Lo que comparten las pruebas de medicion-de-la-landing: las páginas públicas que se miden, pedirlas sin sesión y leer
 * su HTML como documento.
 *
 * Las páginas públicas son la portada (openspec/changes/landing/) y las tres de textos-legales: el aviso de privacidad,
 * los términos y la página para pedir el borrado de la cuenta (textos-legales-E06). Los documentos legales no se cargan:
 * sin ellos, las páginas responden igual, con su marcador de prueba fuera de producción. Las pruebas no compilan los
 * estilos: withoutVite() deja fuera las etiquetas de Vite, que en la integración continua no tienen manifiesto.
 */
trait PaginasPublicas
{
    /** Las páginas públicas de la landing, por su nombre, con su dirección. */
    protected const PAGINAS = [
        'portada' => '/',
        'aviso-de-privacidad' => '/aviso-de-privacidad',
        'terminos' => '/terminos',
        'borrado-de-cuenta' => '/borrado-de-cuenta',
    ];

    /** Un identificador de contenedor inventado, con la forma de los de Google Tag Manager. No es de ninguna cuenta. */
    protected const CONTENEDOR_DE_PRUEBA = 'GTM-PRUEBA1';

    /**
     * La página, sin sesión y sin documentos legales cargados.
     */
    protected function pedirPagina(string $direccion): TestResponse
    {
        $this->withoutVite();
        $this->app['auth']->forgetGuards();
        Storage::fake((string) config('textos_legales.disco'));

        return $this->get($direccion);
    }

    /**
     * El HTML de la respuesta como documento, con XPath.
     */
    protected function html(TestResponse $respuesta): DOMXPath
    {
        $documento = new DOMDocument;
        libxml_use_internal_errors(true);
        $documento->loadHTML('<?xml encoding="utf-8"?>'.$respuesta->getContent());
        libxml_clear_errors();

        return new DOMXPath($documento);
    }
}
