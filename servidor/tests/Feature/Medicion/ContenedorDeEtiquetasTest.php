<?php

namespace Tests\Feature\Medicion;

use DOMElement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\Finder\Finder;
use Tests\Feature\Medicion\Soporte\PaginasPublicas;
use Tests\TestCase;

/**
 * Cada página pública de la landing carga un solo contenedor de Google Tag Manager, por el que entran Google Analytics 4
 * y Meta Pixel, con el identificador de la configuración de cada entorno: en producción, el de la cuenta de EL CLIENTE
 * (NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2; NSV-2026-001-v8.pdf:34:ANEXO B, Sección B.11; ADR-0011). Sin
 * identificador, como en local, la página no carga ningún código de medición de terceros y funciona igual.
 *
 * Revisa el HTML que sale del servidor. Que el contenedor de prueba mande la visita y las acciones hacia GA4 y Meta Pixel
 * lo prueba tests/Browser/Medicion/Eventos.spec.js (medicion-E03 a medicion-E05).
 *
 * Escenarios: medicion-E01 y medicion-E02 · Spec: openspec/changes/medicion-de-la-landing/specs/medicion/spec.md · Tarea
 * 1.1
 */
class ContenedorDeEtiquetasTest extends TestCase
{
    use PaginasPublicas;
    use RefreshDatabase;

    /** Lo que delata código de medición de terceros en una página: sus servidores y sus funciones. */
    private const RASTROS_DE_MEDICION = [
        'googletagmanager',
        'google-analytics',
        'gtag(',
        'dataLayer',
        'facebook',
        'fbevents',
        'fbq(',
    ];

    /**
     * Formas de los identificadores de medición, para que ninguno quede escrito a mano: el contenedor de GTM, la
     * medición de GA4 y la inicialización de Meta Pixel con su número.
     */
    private const FORMAS_DE_IDENTIFICADOR = [
        '/\bGTM-[A-Z0-9]{4,}\b/',
        '/\bG-[A-Z0-9]{6,}\b/',
        "/fbq\\(\\s*['\"]init['\"]\\s*,\\s*['\"]?\\d{6,}/",
    ];

    /** Lo que se revisa: el código y la configuración de servidor/, sin las pruebas, lo de terceros ni lo generado. */
    private const CARPETAS_DEL_CODIGO = ['app', 'config', 'database', 'resources', 'routes'];

    #[TestDox('medicion-E01: con un identificador configurado, la portada, el aviso de privacidad y los términos cargan ese contenedor una sola vez, en la cabecera')]
    public function test_medicion_e01_cada_pagina_carga_el_contenedor_una_sola_vez(): void
    {
        config(['medicion.gtm' => self::CONTENEDOR_DE_PRUEBA]);

        foreach (self::PAGINAS as $pagina => $direccion) {
            $respuesta = $this->pedirPagina($direccion)->assertOk();
            $html = (string) $respuesta->getContent();

            $cargadores = $this->html($respuesta)->query('//script[@data-medicion]');
            $this->assertCount(1, $cargadores, "{$pagina}: un solo cargador del contenedor.");
            $this->assertSame('head', $cargadores->item(0)->parentNode->nodeName, "{$pagina}: el cargador va en la cabecera.");

            $this->assertSame(1, substr_count($html, 'googletagmanager.com/gtm.js'), "{$pagina}: el contenedor se pide una vez.");
            $this->assertSame(1, substr_count($html, self::CONTENEDOR_DE_PRUEBA), "{$pagina}: el identificador aparece una vez.");
            $this->assertStringContainsString(self::CONTENEDOR_DE_PRUEBA, $cargadores->item(0)->textContent, $pagina);
        }
    }

    #[TestDox('medicion-E01: el identificador sale de la configuración del entorno: con otro, la página carga el otro')]
    public function test_medicion_e01_el_identificador_sale_de_la_configuracion(): void
    {
        config(['medicion.gtm' => 'GTM-OTRO22']);

        $html = (string) $this->pedirPagina('/')->assertOk()->getContent();

        $this->assertStringContainsString('GTM-OTRO22', $html);
        $this->assertStringNotContainsString(self::CONTENEDOR_DE_PRUEBA, $html);

        $configuracion = (string) file_get_contents(config_path('medicion.php'));
        $this->assertStringContainsString("env('MEDICION_GTM')", $configuracion);
        $this->assertSame('', $this->variableDelEjemplo('MEDICION_GTM'), 'MEDICION_GTM va vacía en .env.example.');
    }

    #[TestDox('medicion-E01: ningún identificador de medición está escrito en el código: ni el del contenedor de GTM, ni el de GA4, ni el de Meta Pixel')]
    public function test_medicion_e01_ningun_identificador_de_medicion_esta_escrito_en_el_codigo(): void
    {
        $archivos = Finder::create()->files()->in(array_map(base_path(...), self::CARPETAS_DEL_CODIGO));
        $raiz = Finder::create()->files()->in(public_path())->depth(0)->ignoreDotFiles(false);
        $this->assertGreaterThan(0, $archivos->count());

        $hallazgos = [];

        foreach ([...$archivos, ...$raiz] as $archivo) {
            foreach (self::FORMAS_DE_IDENTIFICADOR as $forma) {
                if (preg_match($forma, $archivo->getContents(), $coincidencia)) {
                    $hallazgos[] = "{$archivo->getRelativePathname()}: {$coincidencia[0]}";
                }
            }
        }

        $this->assertSame([], $hallazgos);
    }

    /**
     * @return array<string, array{?string}>
     */
    public static function sinIdentificador(): array
    {
        return [
            'sin la variable' => [null],
            'con la variable vacía' => [''],
            'con solo espacios' => ['   '],
        ];
    }

    #[DataProvider('sinIdentificador')]
    #[TestDox('medicion-E02: sin identificador de contenedor, como en local, ninguna página pública carga código de medición de terceros')]
    public function test_medicion_e02_sin_identificador_no_hay_medicion(?string $identificador): void
    {
        config(['medicion.gtm' => $identificador]);

        foreach (self::PAGINAS as $pagina => $direccion) {
            $this->assertSinMedicion($this->pedirPagina($direccion)->assertOk(), $pagina);
        }
    }

    #[TestDox('medicion-E02: un identificador que no tiene la forma de un contenedor de GTM cuenta como ninguno: no entra a la página')]
    public function test_medicion_e02_un_identificador_mal_formado_cuenta_como_ninguno(): void
    {
        foreach (['GTM-<script>', "GTM-PRUEBA1'); alert('x", 'UA-12345-1', 'G-PRUEBA1234', 'gtm-prueba1'] as $identificador) {
            config(['medicion.gtm' => $identificador]);

            $this->assertSinMedicion($this->pedirPagina('/')->assertOk(), $identificador);
        }
    }

    #[TestDox('medicion-E02: sin identificador, la portada funciona igual: responde, trae sus 12 secciones y el mismo contenido que con el contenedor')]
    public function test_medicion_e02_sin_identificador_la_portada_funciona_igual(): void
    {
        config(['medicion.gtm' => self::CONTENEDOR_DE_PRUEBA]);
        $conMedicion = $this->pedirPagina('/')->assertOk();

        config(['medicion.gtm' => null]);
        $sinMedicion = $this->pedirPagina('/')->assertOk();

        $this->assertSame($this->secciones($conMedicion), $this->secciones($sinMedicion));
        $this->assertCount(12, $this->secciones($sinMedicion));
        $this->assertSame($this->cuerpo($conMedicion), $this->cuerpo($sinMedicion));
    }

    /**
     * Que la página no traiga código de medición de terceros: ni el cargador, ni rastros de GTM, GA4 o Meta Pixel, ni
     * ningún script o marco de otro servidor.
     */
    private function assertSinMedicion(TestResponse $respuesta, string $caso): void
    {
        $html = (string) $respuesta->getContent();
        $documento = $this->html($respuesta);

        $this->assertCount(0, $documento->query('//script[@data-medicion]'), "{$caso}: sin cargador.");

        foreach (self::RASTROS_DE_MEDICION as $rastro) {
            $this->assertStringNotContainsStringIgnoringCase($rastro, $html, "{$caso}: sin {$rastro}.");
        }

        foreach ($documento->query('//script[@src] | //iframe[@src] | //img[@src]') as $elemento) {
            $this->assertInstanceOf(DOMElement::class, $elemento);
            $direccion = $elemento->getAttribute('src');
            $servidor = parse_url($direccion, PHP_URL_HOST);
            $this->assertTrue(
                $servidor === null || $servidor === parse_url((string) config('app.url'), PHP_URL_HOST),
                "{$caso}: {$direccion} es de otro servidor.",
            );
        }
    }

    /**
     * @return list<string>
     */
    private function secciones(TestResponse $respuesta): array
    {
        return array_map(
            fn (DOMElement $seccion): string => $seccion->getAttribute('data-seccion'),
            iterator_to_array($this->html($respuesta)->query('//*[@data-seccion]')),
        );
    }

    /**
     * El cuerpo de la página, sin el token CSRF de los formularios, que cambia con cada sesión.
     */
    private function cuerpo(TestResponse $respuesta): string
    {
        $html = (string) $respuesta->getContent();
        $cuerpo = substr($html, (int) strpos($html, '<body'));

        return (string) preg_replace('/name="_token" value="[^"]*"/', 'name="_token"', $cuerpo);
    }

    /**
     * El valor de una variable de .env.example, sin comillas; null si no está.
     */
    private function variableDelEjemplo(string $nombre): ?string
    {
        foreach (file(base_path('.env.example'), FILE_IGNORE_NEW_LINES) as $linea) {
            if (preg_match('/^\s*'.preg_quote($nombre, '/').'\s*=(.*)$/', $linea, $partes)) {
                return trim(trim($partes[1]), '"\'');
            }
        }

        return null;
    }
}
