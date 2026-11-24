<?php

namespace Tests\Feature\Landing;

use DOMElement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Landing\Soporte\Portada;
use Tests\TestCase;

/**
 * La portada pública: una sola página, sin sesión, con las 12 secciones del A.2 en su orden y los títulos que les da el
 * contrato (NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2; NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.2, A42-1).
 *
 * Revisa el HTML que sale del servidor. Que se vean en Chromium, Firefox y WebKit, en escritorio y en teléfono, lo prueba
 * tests/Browser/Landing/DoceSecciones.spec.js.
 *
 * Escenarios: landing-E01 y landing-E02 · Spec: openspec/changes/landing/specs/landing/spec.md · Tarea 1.1
 */
class PortadaTest extends TestCase
{
    use Portada;
    use RefreshDatabase;

    /** Los títulos de las secciones 2 a 11, tal como las nombra el A.2. */
    private const TITULOS = [
        'como-funciona' => '¿Cómo funciona?',
        'beneficios-usuarios' => 'Beneficios para usuarios',
        'descarga' => 'Descarga la app',
        'lavanderias' => 'Lavanderías partner',
        'beneficios-socios' => 'Beneficios para socios',
        'cobertura' => 'Cobertura',
        'testimonios' => 'Testimonios',
        'preguntas-frecuentes' => 'Preguntas frecuentes',
        'prensa' => 'Prensa / Reconocimientos',
        'contacto' => 'Contacto & Soporte',
    ];

    #[TestDox('landing-E01: la portada es pública: responde sin iniciar sesión, en español')]
    public function test_landing_e01_la_portada_es_publica(): void
    {
        $this->assertGuest();

        $this->pedirPortada()
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=utf-8')
            ->assertSee('<html lang="es">', false);
    }

    #[TestDox('landing-E01: las 12 secciones del A.2 están en la portada, una vez cada una y en su orden')]
    public function test_landing_e01_las_12_secciones_estan_en_su_orden(): void
    {
        $portada = $this->portada();

        $secciones = array_map(
            fn (DOMElement $seccion): string => $seccion->getAttribute('data-seccion'),
            iterator_to_array($portada->query('//*[@data-seccion]')),
        );

        $this->assertSame(self::SECCIONES, $secciones);
    }

    #[TestDox('landing-E01: cada sección de la 2 a la 11 lleva como título el nombre que le da el A.2')]
    public function test_landing_e01_cada_seccion_lleva_su_titulo_del_contrato(): void
    {
        $portada = $this->portada();

        foreach (self::TITULOS as $seccion => $titulo) {
            $this->assertSame([$titulo], $this->textos($portada, $seccion, './/h2'), "El título de {$seccion}.");
        }
    }

    #[TestDox('landing-E01: el encabezado es la cabecera de la página y el pie legal es su pie')]
    public function test_landing_e01_el_encabezado_y_el_pie_son_cabecera_y_pie(): void
    {
        $portada = $this->portada();

        $this->assertSame('header', $this->en($portada, 'encabezado')[0]->tagName);
        $this->assertSame('footer', $this->en($portada, 'pie-legal')[0]->tagName);
        $this->assertCount(1, $portada->query('//main'));
        $this->assertCount(1, $portada->query('//h1'));
    }

    #[TestDox('landing-E02: la portada se adapta al ancho de un teléfono: lleva la etiqueta de ventana del dispositivo')]
    public function test_landing_e02_la_portada_lleva_la_etiqueta_de_ventana(): void
    {
        $portada = $this->portada();

        $ventana = $portada->query('//meta[@name="viewport"]')->item(0);
        $this->assertInstanceOf(DOMElement::class, $ventana);
        $this->assertSame('width=device-width, initial-scale=1', $ventana->getAttribute('content'));
    }
}
