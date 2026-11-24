<?php

namespace Tests\Feature\Medicion;

use App\Autorizacion\Autorizacion;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Medicion\Soporte\PaginasPublicas;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * La landing preparada para buscadores: título y descripción propios en cada página pública, el mapa del sitio con
 * direcciones absolutas en el dominio de la configuración, declarado en el archivo de robots, y la información
 * estructurada del negocio y su zona de servicio en la portada (NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2; ADR-0011).
 *
 * Los textos de las descripciones son de EL CLIENTE (DD-117): aquí son de prueba y lo dicen. Las plazas son las ficticias
 * de PlazasDePrueba, no las del territorio inicial (DD-093, DD-094). Que Search Console lea el mapa del sitio es
 * medicion-E09 (tarea 2.2, bloqueada por DD-023 y DD-118).
 *
 * Escenarios: medicion-E07, medicion-E08 y medicion-E10 · Spec: openspec/changes/medicion-de-la-landing/specs/medicion/
 * spec.md · Tarea 2.1
 */
class BuscadoresTest extends TestCase
{
    use PaginasPublicas;
    use PlazasDePrueba;
    use RefreshDatabase;

    private const NOMBRE_DE_PRUEBA = 'Producto de prueba';

    private const DOMINIO_DE_PRUEBA = 'https://dominio.prueba.test';

    /** Una descripción de prueba por página, como las configuraría EL CLIENTE. */
    private const DESCRIPCIONES = [
        'portada' => 'Descripción de prueba de la portada.',
        'aviso-de-privacidad' => 'Descripción de prueba del aviso de privacidad.',
        'terminos' => 'Descripción de prueba de los términos y condiciones.',
    ];

    /** El espacio de nombres de los mapas del sitio (sitemaps.org). */
    private const ESPACIO_DEL_MAPA = 'http://www.sitemaps.org/schemas/sitemap/0.9';

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.name' => self::NOMBRE_DE_PRUEBA, 'app.url' => self::DOMINIO_DE_PRUEBA]);
    }

    #[TestDox('medicion-E07: con las descripciones configuradas, la portada, el aviso de privacidad y los términos tienen cada una un título y una descripción que no están vacíos, y ninguna repite los de otra')]
    public function test_medicion_e07_cada_pagina_tiene_su_titulo_y_su_descripcion(): void
    {
        foreach (self::DESCRIPCIONES as $pagina => $descripcion) {
            config(["landing.descripciones.{$pagina}" => $descripcion]);
        }

        $vistas = $this->tituloYDescripcionDeCadaPagina();

        foreach (self::DESCRIPCIONES as $pagina => $descripcion) {
            $this->assertSame($descripcion, $vistas[$pagina]['descripcion'], "La descripción de {$pagina}, la configurada.");
        }

        $this->assertSinVaciosNiRepetidos($vistas);
    }

    #[TestDox('medicion-E07: el título de cada página lleva el nombre de la configuración, y el de los documentos legales, además, su nombre')]
    public function test_medicion_e07_el_titulo_lleva_el_nombre_de_la_configuracion(): void
    {
        $vistas = $this->tituloYDescripcionDeCadaPagina();

        $this->assertSame(self::NOMBRE_DE_PRUEBA, $vistas['portada']['titulo']);
        $this->assertSame('Aviso de privacidad · '.self::NOMBRE_DE_PRUEBA, $vistas['aviso-de-privacidad']['titulo']);
        $this->assertSame('Términos y condiciones · '.self::NOMBRE_DE_PRUEBA, $vistas['terminos']['titulo']);
    }

    #[TestDox('medicion-E07: sin las descripciones de EL CLIENTE, fuera de producción, cada página lleva una descripción de contenido de prueba, marcada como tal, propia de esa página')]
    public function test_medicion_e07_sin_descripciones_fuera_de_produccion_hay_marcadores_de_prueba(): void
    {
        $vistas = $this->tituloYDescripcionDeCadaPagina();

        foreach ($vistas as $pagina => $vista) {
            $this->assertStringStartsWith('Contenido de prueba: ', (string) $vista['descripcion'], $pagina);
        }

        $this->assertSinVaciosNiRepetidos($vistas);
    }

    #[TestDox('medicion-E07: en producción, sin las descripciones de EL CLIENTE, ninguna página inventa una ni lleva contenido de prueba en su cabecera')]
    public function test_medicion_e07_en_produccion_sin_descripciones_no_se_inventa_ninguna(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        foreach (self::PAGINAS as $pagina => $direccion) {
            $respuesta = $this->pedirPagina($direccion)->assertOk();

            $this->assertCount(0, $this->html($respuesta)->query('//head/meta[@name="description"]'), $pagina);
            $this->assertStringNotContainsString('Contenido de prueba', $this->cabecera($respuesta), $pagina);
        }
    }

    #[TestDox('medicion-E08: el mapa del sitio responde un XML con la portada, el aviso de privacidad y los términos, cada uno con su dirección absoluta en el dominio de la configuración')]
    public function test_medicion_e08_el_mapa_del_sitio_trae_las_paginas_publicas_en_el_dominio_de_la_configuracion(): void
    {
        $this->assertGuest();

        // La consulta llega por otra dirección, como con un túnel o un proxy: las direcciones siguen siendo las configuradas.
        $respuesta = $this->get('http://otra-direccion.test/sitemap.xml')->assertOk();
        $this->assertStringStartsWith('application/xml', (string) $respuesta->headers->get('Content-Type'));

        $mapa = new DOMDocument;
        $this->assertTrue($mapa->loadXML((string) $respuesta->getContent()), 'El mapa del sitio es XML válido.');
        $this->assertSame('urlset', $mapa->documentElement?->localName);
        $this->assertSame(self::ESPACIO_DEL_MAPA, $mapa->documentElement?->namespaceURI);

        $xpath = new DOMXPath($mapa);
        $xpath->registerNamespace('m', self::ESPACIO_DEL_MAPA);
        $direcciones = array_map(fn ($loc): string => trim($loc->textContent), iterator_to_array($xpath->query('//m:url/m:loc')));

        $this->assertSame([
            self::DOMINIO_DE_PRUEBA,
            self::DOMINIO_DE_PRUEBA.'/aviso-de-privacidad',
            self::DOMINIO_DE_PRUEBA.'/terminos',
        ], $direcciones);
    }

    #[TestDox('medicion-E08: el archivo de robots declara la dirección absoluta del mapa del sitio, en el dominio de la configuración, y no le cierra el sitio a los buscadores')]
    public function test_medicion_e08_el_archivo_de_robots_declara_el_mapa_del_sitio(): void
    {
        $respuesta = $this->get('http://otra-direccion.test/robots.txt')->assertOk();
        $this->assertStringStartsWith('text/plain', (string) $respuesta->headers->get('Content-Type'));

        $lineas = array_map('trim', explode("\n", (string) $respuesta->getContent()));

        $this->assertContains('Sitemap: '.self::DOMINIO_DE_PRUEBA.'/sitemap.xml', $lineas);
        $this->assertContains('User-agent: *', $lineas);
        $this->assertNotContains('Disallow: /', $lineas);

        // El archivo fijo de public/ lo serviría el servidor web antes que la ruta, y no sabe el dominio.
        $this->assertFileDoesNotExist(public_path('robots.txt'));
    }

    #[TestDox('medicion-E08: el mapa del sitio y el archivo de robots son públicos: no piden sesión y lo declaran en el componente de autorización')]
    public function test_medicion_e08_el_mapa_del_sitio_y_el_de_robots_son_publicos(): void
    {
        $sinSesion = app(Autorizacion::class)->rutasSinSesion();

        $this->assertArrayHasKey('GET sitemap.xml', $sinSesion);
        $this->assertArrayHasKey('GET robots.txt', $sinSesion);
    }

    #[TestDox('medicion-E10: la portada trae un bloque de información estructurada válido con el tipo de servicio, el nombre de la configuración, la dirección del sitio y, como zona de servicio, las plazas activas con sus zonas activas')]
    public function test_medicion_e10_la_portada_dice_de_que_trata_el_negocio_y_donde_opera(): void
    {
        $this->sembrarPlazas();

        $bloque = $this->informacionEstructurada($this->pedirPagina('/')->assertOk());

        $this->assertSame('https://schema.org', $bloque['@context']);
        $this->assertSame('Service', $bloque['@type']);
        $this->assertSame('Lavandería a domicilio', $bloque['serviceType']);
        $this->assertSame(self::NOMBRE_DE_PRUEBA, $bloque['name']);
        $this->assertSame(self::DOMINIO_DE_PRUEBA, $bloque['url']);
        $this->assertSame(
            ['@type' => 'Organization', 'name' => self::NOMBRE_DE_PRUEBA, 'url' => self::DOMINIO_DE_PRUEBA],
            $bloque['provider'],
        );
        $this->assertSame([
            [
                '@type' => 'Place',
                'name' => 'Plaza de prueba A',
                'containsPlace' => [
                    ['@type' => 'Place', 'name' => 'Zona de prueba Centro'],
                    ['@type' => 'Place', 'name' => 'Zona de prueba Norte'],
                ],
            ],
            ['@type' => 'Place', 'name' => 'Plaza de prueba B'],
        ], $bloque['areaServed']);
    }

    #[TestDox('medicion-E10: el bloque no incluye ningún dato que no esté configurado: ni teléfono, ni domicilio, ni correo, ni coordenadas, ni ninguna otra propiedad')]
    public function test_medicion_e10_el_bloque_no_incluye_datos_que_no_esten_configurados(): void
    {
        $this->sembrarPlazas();

        $bloque = $this->informacionEstructurada($this->pedirPagina('/')->assertOk());

        $this->assertSame(['@context', '@type', 'name', 'serviceType', 'url', 'provider', 'areaServed'], array_keys($bloque));
        $this->assertSame([], array_values(array_diff(
            $this->clavesDe($bloque),
            ['@context', '@type', 'name', 'serviceType', 'url', 'provider', 'areaServed', 'containsPlace'],
        )));
    }

    #[TestDox('medicion-E10: solo la portada lleva el bloque, una vez, y sin plazas activas lo lleva sin zona de servicio')]
    public function test_medicion_e10_solo_la_portada_lleva_el_bloque(): void
    {
        $this->plazaDePrueba('Plaza de prueba inactiva', activa: false);

        $bloque = $this->informacionEstructurada($this->pedirPagina('/')->assertOk());
        $this->assertArrayNotHasKey('areaServed', $bloque);

        foreach (['/aviso-de-privacidad', '/terminos'] as $direccion) {
            $respuesta = $this->pedirPagina($direccion)->assertOk();
            $this->assertCount(0, $this->html($respuesta)->query('//script[@type="application/ld+json"]'), $direccion);
        }
    }

    #[TestDox('medicion-E10: un nombre de plaza con marcas de HTML no rompe el bloque ni se vuelve código en la página')]
    public function test_medicion_e10_un_nombre_con_marcas_no_rompe_el_bloque(): void
    {
        $this->plazaDePrueba('Plaza </script><script>alert(1)</script>');

        $respuesta = $this->pedirPagina('/')->assertOk();

        $this->assertSame('Plaza </script><script>alert(1)</script>', $this->informacionEstructurada($respuesta)['areaServed'][0]['name']);
        $this->assertStringNotContainsString('<script>alert(1)</script>', (string) $respuesta->getContent());
    }

    /**
     * Dos plazas activas, una con zonas activas y una inactiva, y una plaza inactiva con una zona activa.
     */
    private function sembrarPlazas(): void
    {
        $a = $this->plazaDePrueba('Plaza de prueba A');
        $this->zonaDePrueba($a, 'Zona de prueba Norte', $this->cuadrado(-120, 11));
        $this->zonaDePrueba($a, 'Zona de prueba Centro', $this->cuadrado(-120, 10));
        $this->zonaDePrueba($a, 'Zona de prueba inactiva', $this->cuadrado(-119, 10), activa: false);

        $this->plazaDePrueba('Plaza de prueba B');

        $inactiva = $this->plazaDePrueba('Plaza de prueba inactiva', activa: false);
        $this->zonaDePrueba($inactiva, 'Zona de la plaza inactiva', $this->cuadrado(-119, 11));
    }

    /**
     * El título y la descripción de cada página pública; la descripción en null si la página no la trae.
     *
     * @return array<string, array{titulo: string, descripcion: ?string}>
     */
    private function tituloYDescripcionDeCadaPagina(): array
    {
        $vistas = [];

        foreach (self::PAGINAS as $pagina => $direccion) {
            $html = $this->html($this->pedirPagina($direccion)->assertOk());

            $titulos = $html->query('//head/title');
            $descripciones = $html->query('//head/meta[@name="description"]');
            $this->assertCount(1, $titulos, "{$pagina}: un solo título.");
            $this->assertLessThanOrEqual(1, $descripciones->length, "{$pagina}: a lo más una descripción.");

            $vistas[$pagina] = [
                'titulo' => trim((string) $titulos->item(0)?->textContent),
                'descripcion' => $descripciones->length === 1 ? $descripciones->item(0)->getAttribute('content') : null,
            ];
        }

        return $vistas;
    }

    /**
     * Que ningún título ni ninguna descripción esté vacío ni se repita en otra página.
     *
     * @param  array<string, array{titulo: string, descripcion: ?string}>  $vistas
     */
    private function assertSinVaciosNiRepetidos(array $vistas): void
    {
        foreach (['titulo', 'descripcion'] as $campo) {
            $valores = array_column($vistas, $campo);

            foreach ($valores as $valor) {
                $this->assertNotSame('', trim((string) $valor), "Ningún {$campo} vacío.");
            }

            $this->assertSame($valores, array_values(array_unique($valores)), "Ningún {$campo} se repite.");
        }
    }

    /**
     * La cabecera de la página, como texto.
     */
    private function cabecera(TestResponse $respuesta): string
    {
        $html = (string) $respuesta->getContent();

        return substr($html, 0, (int) strpos($html, '</head>'));
    }

    /**
     * El bloque de información estructurada de la página, que tiene que ser uno solo y JSON válido.
     *
     * @return array<string, mixed>
     */
    private function informacionEstructurada(TestResponse $respuesta): array
    {
        $bloques = $this->html($respuesta)->query('//script[@type="application/ld+json"]');
        $this->assertCount(1, $bloques, 'Un solo bloque de información estructurada.');

        return json_decode((string) $bloques->item(0)?->textContent, true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * Todas las claves del bloque, a cualquier profundidad.
     *
     * @param  array<array-key, mixed>  $datos
     * @return list<string>
     */
    private function clavesDe(array $datos): array
    {
        $claves = [];

        foreach ($datos as $clave => $valor) {
            if (is_string($clave)) {
                $claves[] = $clave;
            }

            if (is_array($valor)) {
                $claves = [...$claves, ...$this->clavesDe($valor)];
            }
        }

        return array_values(array_unique($claves));
    }
}
