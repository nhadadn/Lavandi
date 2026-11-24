<?php

namespace Tests\Feature\Landing;

use DOMElement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Landing\Soporte\Portada;
use Tests\TestCase;

/**
 * El encabezado de la portada: logo, nombre, tagline, imagen con el mockup de la app y el botón principal de descarga,
 * que lleva a la sección "Descarga la app" (NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2, sección 1; design.md, lectura
 * 1). El nombre y el logo salen de la configuración, nunca de la vista (CLAUDE.md §5).
 *
 * El tagline y la imagen son de EL CLIENTE (DD-117): sin ellos, el encabezado muestra su marcador de contenido de
 * prueba. Los valores de aquí son inventados.
 *
 * Escenario: landing-E04 · Spec: openspec/changes/landing/specs/landing/spec.md · Tarea 2.1
 */
class EncabezadoTest extends TestCase
{
    use Portada;
    use RefreshDatabase;

    private function conContenidoDePrueba(): void
    {
        config([
            'app.name' => 'Marca de prueba',
            'app.url' => 'https://sitio.ejemplo.test',
            'landing.logo' => 'marca/logo-de-prueba.png',
            'landing.encabezado.tagline' => 'Un tagline de prueba',
            'landing.encabezado.imagen' => 'marca/mockup-de-prueba.png',
            'landing.encabezado.imagen_texto' => 'Mockup de prueba de la app',
        ]);
        url()->forceRootUrl('https://sitio.ejemplo.test');
    }

    #[TestDox('landing-E04: el encabezado muestra el logo y el nombre del producto, los dos de la configuración')]
    public function test_landing_e04_el_logo_y_el_nombre_salen_de_la_configuracion(): void
    {
        $this->conContenidoDePrueba();
        $portada = $this->portada();

        $logo = $this->en($portada, 'encabezado', './/img[@data-logo]');
        $this->assertCount(1, $logo);
        $this->assertSame('https://sitio.ejemplo.test/marca/logo-de-prueba.png', $logo[0]->getAttribute('src'));
        $this->assertSame(['Marca de prueba'], $this->textos($portada, 'encabezado', './/h1'));

        config(['app.name' => 'Otra marca de prueba', 'landing.logo' => 'marca/otro-logo-de-prueba.svg']);
        $portada = $this->portada();

        $this->assertSame(['Otra marca de prueba'], $this->textos($portada, 'encabezado', './/h1'));
        $this->assertSame(
            'https://sitio.ejemplo.test/marca/otro-logo-de-prueba.svg',
            $this->en($portada, 'encabezado', './/img[@data-logo]')[0]->getAttribute('src'),
        );
    }

    #[TestDox('landing-E04: con el tagline y la imagen cargados como contenido, el encabezado los muestra')]
    public function test_landing_e04_el_tagline_y_la_imagen_cargados_se_muestran(): void
    {
        $this->conContenidoDePrueba();
        $portada = $this->portada();

        $this->assertSame(['Un tagline de prueba'], $this->textos($portada, 'encabezado', './/*[@data-tagline]'));

        $imagen = $this->en($portada, 'encabezado', './/img[@data-imagen-encabezado]');
        $this->assertCount(1, $imagen);
        $this->assertSame('https://sitio.ejemplo.test/marca/mockup-de-prueba.png', $imagen[0]->getAttribute('src'));
        $this->assertSame('Mockup de prueba de la app', $imagen[0]->getAttribute('alt'));

        $this->assertSame([], $this->en($portada, 'encabezado', './/*[@data-de-prueba]'), 'Con el contenido cargado no queda ningún marcador.');
    }

    #[TestDox('landing-E04: el botón principal de descarga lleva a la sección "Descarga la app"')]
    public function test_landing_e04_el_boton_principal_lleva_a_descarga_la_app(): void
    {
        $this->conContenidoDePrueba();
        $portada = $this->portada();

        $botones = $this->en($portada, 'encabezado', './/a[@data-boton-principal]');
        $this->assertCount(1, $botones);
        $this->assertSame('#descarga', $botones[0]->getAttribute('href'));
        $this->assertSame('Descarga la app', $this->texto($botones[0]));

        $destino = $portada->query('//*[@id="descarga"]')->item(0);
        $this->assertInstanceOf(DOMElement::class, $destino);
        $this->assertSame('descarga', $destino->getAttribute('data-seccion'));
    }

    #[TestDox('landing-E04: sin el tagline ni la imagen de EL CLIENTE, el encabezado muestra sus marcadores de contenido de prueba y ninguna imagen rota')]
    public function test_landing_e04_sin_contenido_muestra_marcadores_de_prueba(): void
    {
        config(['landing.encabezado.tagline' => null, 'landing.encabezado.imagen' => null]);
        $portada = $this->portada();

        $this->assertCount(2, $this->en($portada, 'encabezado', './/*[@data-de-prueba]'));
        $this->assertSame([], $this->en($portada, 'encabezado', './/img[@data-imagen-encabezado]'));
        $this->assertSame([], $this->en($portada, 'encabezado', './/img[not(@src) or @src=""]'));
        $this->assertCount(1, $this->en($portada, 'encabezado', './/a[@data-boton-principal]'));
    }

    #[TestDox('landing-E04: el logo por omisión es el archivo de nombre neutro de public/, y existe')]
    public function test_landing_e04_el_logo_por_omision_existe(): void
    {
        $logo = config('landing.logo');

        $this->assertIsString($logo);
        $this->assertFileExists(public_path($logo));
    }
}
