<?php

namespace Tests\Feature\Medicion;

use DOMElement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Medicion\Soporte\PaginasPublicas;
use Tests\TestCase;

/**
 * La portada carga con rapidez en teléfonos: imágenes del tamaño en que se muestran y pedidas como conviene en teléfono,
 * y la alerta temprana de Lighthouse en la integración continua, en perfil de teléfono y con el umbral de 0.70 en
 * rendimiento (NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.2; NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.2, A42-4;
 * ADR-0011).
 *
 * La medición misma la hace Lighthouse CI con servidor/lighthouserc.json, en su trabajo de la integración continua; esta
 * prueba cuida que su configuración siga pidiendo lo que pide la spec. Que el contenedor de etiquetas se cargue en
 * diferido lo prueba tests/Browser/Medicion/Velocidad.spec.js. PageSpeed Insights sobre la dirección publicada es
 * medicion-E12 (tarea 3.3, bloqueada por DD-023 y DD-070).
 *
 * Escenario: medicion-E11 · Spec: openspec/changes/medicion-de-la-landing/specs/medicion/spec.md · Tarea 3.1
 */
class VelocidadTest extends TestCase
{
    use PaginasPublicas;
    use RefreshDatabase;

    /** Lo más que se muestra el logo en las páginas públicas, en píxeles (--ds-espaciado-8, en el encabezado). */
    private const LOGO_EN_PANTALLA = 64;

    /** Cuántos píxeles de imagen por píxel de pantalla bastan en un teléfono. */
    private const DENSIDAD_DE_TELEFONO = 3;

    #[TestDox('medicion-E11: el logo por omisión mide a lo más el triple de lo que se muestra, lo que pide la pantalla de un teléfono, y pesa poco')]
    public function test_medicion_e11_el_logo_por_omision_es_del_tamano_en_que_se_muestra(): void
    {
        $logo = public_path((string) config('landing.logo'));
        $this->assertFileExists($logo);

        [$ancho, $alto] = getimagesize($logo) ?: [0, 0];
        $this->assertGreaterThanOrEqual(self::LOGO_EN_PANTALLA * 2, $ancho, 'El logo se sigue viendo nítido en un teléfono.');
        $this->assertLessThanOrEqual(self::LOGO_EN_PANTALLA * self::DENSIDAD_DE_TELEFONO, $ancho);
        $this->assertLessThanOrEqual(self::LOGO_EN_PANTALLA * self::DENSIDAD_DE_TELEFONO, $alto);
        $this->assertLessThanOrEqual(10 * 1024, filesize($logo), 'El logo pesa a lo más 10 KB.');
    }

    #[TestDox('medicion-E11: las imágenes de EL CLIENTE se piden como conviene en teléfono: la del encabezado primero, la de descarga solo al acercarse a ella, y las dos sin detener la página')]
    public function test_medicion_e11_las_imagenes_se_piden_como_conviene_en_telefono(): void
    {
        config([
            'landing.encabezado.imagen' => 'marca/imagen-de-prueba-del-encabezado.webp',
            'landing.descarga.imagen' => 'marca/imagen-de-prueba-de-descarga.webp',
        ]);

        $html = $this->html($this->pedirPagina('/')->assertOk());

        $encabezado = $html->query('//img[@data-imagen-encabezado]')->item(0);
        $this->assertInstanceOf(DOMElement::class, $encabezado);
        $this->assertSame('high', $encabezado->getAttribute('fetchpriority'), 'La del encabezado es la más grande al abrir.');
        $this->assertFalse($encabezado->hasAttribute('loading'), 'La del encabezado no espera.');
        $this->assertSame('async', $encabezado->getAttribute('decoding'));

        $descarga = $html->query('//img[@data-imagen-descarga]')->item(0);
        $this->assertInstanceOf(DOMElement::class, $descarga);
        $this->assertSame('lazy', $descarga->getAttribute('loading'), 'La de descarga queda más abajo en el teléfono.');
        $this->assertSame('async', $descarga->getAttribute('decoding'));
    }

    #[TestDox('medicion-E11: Lighthouse CI mide la portada en perfil de teléfono y falla si el rendimiento baja de 0.70')]
    public function test_medicion_e11_lighthouse_ci_mide_la_portada_en_telefono_con_el_umbral_de_070(): void
    {
        $ruta = base_path('lighthouserc.json');
        $this->assertFileExists($ruta);
        $configuracion = json_decode((string) file_get_contents($ruta), true, flags: JSON_THROW_ON_ERROR);

        $recoleccion = $configuracion['ci']['collect'];
        $this->assertNotEmpty($recoleccion['url']);
        foreach ($recoleccion['url'] as $direccion) {
            $this->assertSame('/', parse_url($direccion, PHP_URL_PATH), 'Mide la portada.');
        }
        $this->assertSame('mobile', $recoleccion['settings']['formFactor'], 'En perfil de teléfono.');
        $this->assertArrayNotHasKey('preset', $recoleccion['settings'], 'Sin el perfil de escritorio.');
        $this->assertGreaterThanOrEqual(3, $recoleccion['numberOfRuns'], 'Con varias corridas, para no depender de una.');

        $this->assertSame(
            ['error', ['minScore' => 0.7, 'aggregationMethod' => 'median-run']],
            $configuracion['ci']['assert']['assertions']['categories:performance'],
            'La verificación falla si el rendimiento baja de 0.70.',
        );

        // No sube los informes a ningún servicio externo.
        $this->assertSame('filesystem', $configuracion['ci']['upload']['target']);
    }
}
