<?php

namespace Tests\Feature\Landing;

use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Landing\Soporte\Portada;
use Tests\Feature\TextosLegales\Soporte\TextosLegalesDePrueba;
use Tests\TestCase;

/**
 * Sección 12, "Footer legal": toda página pública de la landing lleva un pie con los enlaces al aviso de privacidad y a
 * los términos, en las direcciones que publica textos-legales, y el aviso de derechos de autor con el titular y el año
 * de la configuración (NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2; NSV-2026-001-v8.pdf:31:ANEXO B, Sección B.7).
 *
 * Las páginas públicas de la landing son la portada y las tres de textos-legales: el aviso de privacidad, los términos y
 * la página para pedir el borrado de la cuenta (textos-legales-E06). Quién aparece como titular es DD-120: mientras no se
 * configure, fuera de producción el pie lleva un marcador de contenido de prueba, y en producción no lleva ninguno, para
 * que las páginas de los documentos no muestren texto de prueba (textos-legales-E02).
 *
 * Escenarios: landing-E24 y landing-E25 · Spec: openspec/changes/landing/specs/landing/spec.md · Tarea 6.1
 */
class PieLegalTest extends TestCase
{
    use Portada;
    use RefreshDatabase;
    use TextosLegalesDePrueba;

    /** Las páginas públicas de la landing: la portada y las tres de textos-legales. */
    private const PAGINAS = ['/', '/aviso-de-privacidad', '/terminos', '/borrado-de-cuenta'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->almacenamientoVacio();
    }

    #[TestDox('landing-E24: en cada página pública, el pie enlaza al aviso de privacidad y a los términos, en las direcciones de textos-legales')]
    public function test_landing_e24_el_pie_de_cada_pagina_publica_enlaza_a_los_dos_documentos(): void
    {
        foreach (self::PAGINAS as $pagina) {
            $pie = $this->pieDe($pagina);

            $enlaces = [];
            foreach ($pie->query('//footer[@data-seccion="pie-legal"]//a') as $enlace) {
                $this->assertInstanceOf(DOMElement::class, $enlace);
                $enlaces[trim($enlace->textContent)] = $enlace->getAttribute('href');
            }

            $this->assertSame([
                'Aviso de privacidad' => route('textos-legales.aviso-de-privacidad'),
                'Términos y condiciones' => route('textos-legales.terminos'),
            ], $enlaces, "Los enlaces del pie de {$pagina}.");
        }

        $this->assertStringEndsWith('/aviso-de-privacidad', route('textos-legales.aviso-de-privacidad'));
        $this->assertStringEndsWith('/terminos', route('textos-legales.terminos'));
    }

    #[TestDox('landing-E24: los dos enlaces del pie llevan a páginas que se abren sin sesión')]
    public function test_landing_e24_los_enlaces_del_pie_llevan_a_paginas_que_se_abren(): void
    {
        $enlaces = $this->pieDe('/')->query('//footer[@data-seccion="pie-legal"]//a');
        $this->assertCount(2, $enlaces);

        foreach ($enlaces as $enlace) {
            $this->assertInstanceOf(DOMElement::class, $enlace);
            $this->app['auth']->forgetGuards();
            $this->get((string) parse_url($enlace->getAttribute('href'), PHP_URL_PATH))->assertOk();
        }
    }

    #[TestDox('landing-E25: con un titular y un año configurados, el pie de cada página pública muestra el aviso de derechos de autor con ese titular y ese año')]
    public function test_landing_e25_el_pie_muestra_el_aviso_de_derechos_de_autor_de_la_configuracion(): void
    {
        config(['landing.derechos.titular' => 'Titular de prueba', 'landing.derechos.anio' => '2026']);

        foreach (self::PAGINAS as $pagina) {
            $pie = $this->pieDe($pagina);
            $derechos = $pie->query('//footer[@data-seccion="pie-legal"]//*[@data-derechos]');

            $this->assertCount(1, $derechos, "El aviso de derechos de autor en {$pagina}.");
            $this->assertSame('© 2026 Titular de prueba. Todos los derechos reservados.', trim($derechos->item(0)->textContent));
            $this->assertCount(0, $pie->query('//footer[@data-seccion="pie-legal"]//*[@data-de-prueba]'), "Sin marcador en {$pagina}.");
        }
    }

    #[TestDox('landing-E25: con otro titular y otro año en la configuración, el pie muestra esos, sin nada escrito en la vista')]
    public function test_landing_e25_el_titular_y_el_ano_salen_de_la_configuracion(): void
    {
        config(['landing.derechos.titular' => 'Otro titular de prueba', 'landing.derechos.anio' => '2027']);

        $derechos = $this->pieDe('/')->query('//footer[@data-seccion="pie-legal"]//*[@data-derechos]');

        $this->assertSame('© 2027 Otro titular de prueba. Todos los derechos reservados.', trim($derechos->item(0)->textContent));
    }

    #[TestDox('landing-E25: sin titular o sin año, fuera de producción el pie lleva un marcador de contenido de prueba en su lugar, y en producción no lleva ninguno')]
    public function test_landing_e25_sin_titular_o_sin_ano_el_pie_no_inventa_el_aviso(): void
    {
        foreach ([[null, '2026'], ['Titular de prueba', null], ['   ', '2026'], [null, null]] as [$titular, $anio]) {
            config(['landing.derechos.titular' => $titular, 'landing.derechos.anio' => $anio]);

            $pie = $this->pieDe('/');
            $this->assertCount(0, $pie->query('//footer[@data-seccion="pie-legal"]//*[@data-derechos]'));
            $this->assertCount(1, $pie->query('//footer[@data-seccion="pie-legal"]//*[@data-de-prueba]'));
        }

        $this->app->detectEnvironment(fn (): string => 'production');

        foreach (['/aviso-de-privacidad', '/terminos', '/borrado-de-cuenta'] as $pagina) {
            $pie = $this->pieDe($pagina);
            $this->assertCount(0, $pie->query('//footer[@data-seccion="pie-legal"]//*[@data-derechos]'));
            $this->assertCount(0, $pie->query('//*[@data-de-prueba]'), "Sin marcador de prueba en {$pagina}.");
            $this->assertCount(2, $pie->query('//footer[@data-seccion="pie-legal"]//a'), "Los enlaces siguen en {$pagina}.");
        }
    }

    #[TestDox('landing-E24: la portada lleva el pie legal una sola vez, como su última sección')]
    public function test_landing_e24_la_portada_lleva_el_pie_una_vez_al_final(): void
    {
        $portada = $this->portada();

        $this->assertCount(1, $portada->query('//footer[@data-seccion="pie-legal"]'));
        $secciones = $portada->query('//*[@data-seccion]');
        $this->assertSame('pie-legal', $secciones->item($secciones->length - 1)->getAttribute('data-seccion'));
    }

    /**
     * El HTML de una página pública, sin sesión. Debe responder 200.
     */
    private function pieDe(string $pagina): DOMXPath
    {
        $this->withoutVite();
        $this->app['auth']->forgetGuards();

        return $this->documento((string) $this->get($pagina)->assertOk()->getContent());
    }
}
