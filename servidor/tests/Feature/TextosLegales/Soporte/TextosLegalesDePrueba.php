<?php

namespace Tests\Feature\TextosLegales\Soporte;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

/**
 * Lo que comparten las pruebas de textos-legales: el almacenamiento falso de los documentos, la carga de un documento de
 * prueba en él, la petición sin sesión de cada página y su HTML como documento.
 *
 * Los documentos son de prueba y lo dicen: el aviso de privacidad y los términos los entrega EL CLIENTE
 * (NSV-2026-001-v8.pdf:31:ANEXO B, Sección B.7; DD-080). Las pruebas no compilan los estilos: withoutVite() deja fuera
 * las etiquetas de Vite, que en la integración continua no tienen manifiesto.
 */
trait TextosLegalesDePrueba
{
    /** Las direcciones públicas de los dos documentos (openspec/changes/textos-legales/design.md, pregunta 5). */
    protected const DIRECCIONES = [
        'aviso-de-privacidad' => '/aviso-de-privacidad',
        'terminos' => '/terminos',
    ];

    /**
     * El disco de los documentos, falso y vacío: ninguno está cargado.
     */
    protected function almacenamientoVacio(): void
    {
        Storage::fake((string) config('textos_legales.disco'));
    }

    /**
     * Deja el texto como la versión vigente del documento, directo en el almacenamiento.
     */
    protected function cargarEnElAlmacenamiento(string $documento, string $texto): void
    {
        Storage::disk((string) config('textos_legales.disco'))
            ->put((string) config("textos_legales.documentos.{$documento}.archivo"), $texto);
    }

    /**
     * La página del documento, sin sesión.
     */
    protected function pedirDocumento(string $documento): TestResponse
    {
        $this->withoutVite();
        $this->app['auth']->forgetGuards();

        return $this->get(self::DIRECCIONES[$documento]);
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

    /**
     * El texto del documento como lo muestra la página: el contenido del elemento marcado con data-texto-legal.
     */
    protected function textoMostrado(TestResponse $respuesta): ?string
    {
        $nodo = $this->html($respuesta)->query('//*[@data-texto-legal]')->item(0);

        return $nodo?->textContent;
    }
}
