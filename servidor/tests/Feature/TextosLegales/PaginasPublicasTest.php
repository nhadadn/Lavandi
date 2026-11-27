<?php

namespace Tests\Feature\TextosLegales;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\TextosLegales\Soporte\TextosLegalesDePrueba;
use Tests\TestCase;

/**
 * Las páginas públicas del aviso de privacidad y de los términos y condiciones: cada una en su dirección fija, sin
 * sesión, con el texto vigente tal como se cargó (NSV-2026-001-v8.pdf:31:ANEXO B, Sección B.7). En producción, sin el
 * documento de EL CLIENTE, la página no muestra ningún texto de ejemplo ni de prueba: dice que el documento todavía no
 * está disponible.
 *
 * El texto vigente es un archivo en el almacenamiento del entorno (openspec/changes/textos-legales/design.md,
 * "Cómo quedó implementado"; ADR-0006): las pruebas lo dejan en un disco falso.
 *
 * Escenarios: textos-legales-E01 y textos-legales-E02 · Spec: openspec/changes/textos-legales/specs/textos-legales/spec.md
 * · Tarea 1.1
 */
class PaginasPublicasTest extends TestCase
{
    use RefreshDatabase;
    use TextosLegalesDePrueba;

    private const AVISO = "AVISO DE PRIVACIDAD DE PRUEBA\n\nDocumento de prueba para textos-legales-E01. No es el aviso de EL CLIENTE.\n\n"
        ."I. Primera sección de prueba.\nSegunda línea de la sección, con acentos: árbol, pingüino, ñandú.\n\n"
        .'II. Segunda sección de prueba, con   espacios dobles y signos: ¿sí? ¡sí! 100 % «comillas» & más.';

    private const TERMINOS = "TÉRMINOS Y CONDICIONES DE PRUEBA\n\nDocumento de prueba para textos-legales-E01.\n\n"
        ."1. Primera cláusula de prueba.\n2. Segunda cláusula de prueba.";

    protected function setUp(): void
    {
        parent::setUp();

        $this->almacenamientoVacio();
    }

    #[TestDox('textos-legales-E01: alguien sin sesión abre la dirección del aviso de privacidad y la de los términos, y ve el texto completo de cada documento cargado, sin cambios')]
    public function test_textos_legales_e01_el_documento_cargado_se_publica_sin_cambios(): void
    {
        $this->cargarEnElAlmacenamiento('aviso-de-privacidad', self::AVISO);
        $this->cargarEnElAlmacenamiento('terminos', self::TERMINOS);

        foreach (['aviso-de-privacidad' => self::AVISO, 'terminos' => self::TERMINOS] as $documento => $texto) {
            $this->assertGuest();

            $respuesta = $this->pedirDocumento($documento)
                ->assertOk()
                ->assertHeader('Content-Type', 'text/html; charset=utf-8')
                ->assertSee('<html lang="es">', false);

            $this->assertSame($texto, $this->textoMostrado($respuesta), "El texto de {$documento}, sin cambios.");
            $this->assertCount(0, $this->html($respuesta)->query('//main//*[@data-de-prueba]'), "Sin marcador en {$documento}.");
        }
    }

    #[TestDox('textos-legales-E01: cada página lleva el título de su documento y muestra solo su propio texto')]
    public function test_textos_legales_e01_cada_pagina_muestra_solo_su_documento(): void
    {
        $this->cargarEnElAlmacenamiento('aviso-de-privacidad', self::AVISO);
        $this->cargarEnElAlmacenamiento('terminos', self::TERMINOS);

        $aviso = $this->pedirDocumento('aviso-de-privacidad')->assertOk();
        $this->assertSame('Aviso de privacidad', trim($this->html($aviso)->query('//main//h1')->item(0)->textContent));
        $aviso->assertDontSee('TÉRMINOS Y CONDICIONES DE PRUEBA');

        $terminos = $this->pedirDocumento('terminos')->assertOk();
        $this->assertSame('Términos y condiciones', trim($this->html($terminos)->query('//main//h1')->item(0)->textContent));
        $terminos->assertDontSee('AVISO DE PRIVACIDAD DE PRUEBA');
    }

    #[TestDox('textos-legales-E01: un documento con marcas de HTML se muestra como texto, tal cual, y no se interpreta')]
    public function test_textos_legales_e01_el_html_del_documento_se_muestra_como_texto(): void
    {
        $texto = "Documento de prueba.\n<script>alert('prueba')</script>\n<b>No es negritas</b> & <a href=\"https://ejemplo.test\">no es enlace</a>";
        $this->cargarEnElAlmacenamiento('aviso-de-privacidad', $texto);

        $respuesta = $this->pedirDocumento('aviso-de-privacidad')->assertOk();

        $this->assertSame($texto, $this->textoMostrado($respuesta));
        $documento = $this->html($respuesta);
        $this->assertCount(0, $documento->query('//*[@data-texto-legal]//script | //*[@data-texto-legal]//b | //*[@data-texto-legal]//a'));
    }

    #[TestDox('textos-legales-E02: con la configuración de producción y sin el documento cargado, ninguna de las dos páginas muestra texto de ejemplo ni de prueba, y las dos dicen que el documento todavía no está disponible')]
    public function test_textos_legales_e02_en_produccion_sin_documento_no_se_publica_un_texto_de_ejemplo(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        foreach (array_keys(self::DIRECCIONES) as $documento) {
            $respuesta = $this->pedirDocumento($documento)->assertOk();
            $html = $this->html($respuesta);

            $this->assertSame(
                'Este documento todavía no está disponible.',
                trim((string) $html->query('//*[@data-texto-no-disponible]')->item(0)?->textContent),
                "La página de {$documento} dice que no está disponible.",
            );
            $this->assertNull($this->textoMostrado($respuesta));
            $this->assertCount(0, $html->query('//*[@data-de-prueba]'), "Ningún marcador de prueba en {$documento}.");

            $visible = mb_strtolower((string) $html->query('//body')->item(0)->textContent);
            foreach (['de prueba', 'ejemplo', 'lorem'] as $palabra) {
                $this->assertStringNotContainsString($palabra, $visible, "La página de {$documento} no dice \"{$palabra}\".");
            }
        }
    }

    #[TestDox('textos-legales-E02: en producción, un archivo vacío o solo con espacios cuenta como documento no cargado')]
    public function test_textos_legales_e02_en_produccion_un_archivo_vacio_no_se_publica(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        foreach (['', "  \n\t  \n"] as $vacio) {
            $this->cargarEnElAlmacenamiento('terminos', $vacio);

            $respuesta = $this->pedirDocumento('terminos')->assertOk();

            $this->assertNull($this->textoMostrado($respuesta));
            $this->assertCount(1, $this->html($respuesta)->query('//*[@data-texto-no-disponible]'));
        }
    }

    #[TestDox('textos-legales-E02: fuera de producción, sin el documento cargado, la página muestra un marcador de contenido de prueba, marcado como tal, como la portada')]
    public function test_textos_legales_e02_fuera_de_produccion_sin_documento_se_ve_un_marcador_de_prueba(): void
    {
        foreach (array_keys(self::DIRECCIONES) as $documento) {
            $respuesta = $this->pedirDocumento($documento)->assertOk();
            $marcadores = $this->html($respuesta)->query('//main//*[@data-de-prueba]');

            $this->assertCount(1, $marcadores, "Un marcador de prueba en {$documento}.");
            $this->assertStringStartsWith('Contenido de prueba:', trim($marcadores->item(0)->textContent));
            $this->assertNull($this->textoMostrado($respuesta));
        }
    }

    #[TestDox('textos-legales-E01: en producción, con el documento cargado, la página lo publica igual')]
    public function test_textos_legales_e01_en_produccion_el_documento_cargado_se_publica(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        $this->cargarEnElAlmacenamiento('aviso-de-privacidad', self::AVISO);

        $respuesta = $this->pedirDocumento('aviso-de-privacidad')->assertOk();

        $this->assertSame(self::AVISO, $this->textoMostrado($respuesta));
        $this->assertCount(0, $this->html($respuesta)->query('//*[@data-texto-no-disponible]'));
    }
}
