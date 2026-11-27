<?php

namespace Tests\Feature\Medicion;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Medicion\Soporte\PaginasPublicas;
use Tests\TestCase;

/**
 * La landing con el candado activo: con la configuración de producción, toda petición por http a una página pública
 * recibe una redirección permanente a la misma dirección por https, en el dominio de la configuración
 * (NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.2; NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.2, A42-3;
 * NSV-2026-001-v8.pdf:28:ANEXO B, Sección B.3).
 *
 * El mecanismo es el mismo del panel y de la API (publicacion-E04 y publicacion-E05): el middleware global
 * App\Http\Middleware\SoloHttps, que se enciende con config('app.solo_https'). El candado en la dirección publicada, con
 * su certificado, es medicion-E14 (tarea 3.3, bloqueada por DD-023 y DD-070).
 *
 * Escenario: medicion-E13 · Spec: openspec/changes/medicion-de-la-landing/specs/medicion/spec.md · Tarea 3.2
 */
class CandadoTest extends TestCase
{
    use PaginasPublicas;
    use RefreshDatabase;

    private const DOMINIO = 'dominio.prueba.test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->configuracionDeProduccion();
    }

    #[TestDox('medicion-E13: con la configuración de producción, quien pide la portada por http recibe una redirección permanente a la misma dirección por https')]
    public function test_medicion_e13_la_portada_por_http_redirige_a_https(): void
    {
        $this->pedirPagina('http://'.self::DOMINIO.'/')
            ->assertStatus(301)
            ->assertRedirect('https://'.self::DOMINIO.'/');
    }

    #[TestDox('medicion-E13: la redirección conserva la dirección completa, con su consulta, y va al dominio de la configuración aunque la petición llegue por otro')]
    public function test_medicion_e13_la_redireccion_va_al_dominio_de_la_configuracion(): void
    {
        $this->pedirPagina('http://'.self::DOMINIO.'/?origen=prueba')
            ->assertStatus(301)
            ->assertRedirect('https://'.self::DOMINIO.'/?origen=prueba');

        $this->pedirPagina('http://otra-direccion.test/aviso-de-privacidad')
            ->assertStatus(301)
            ->assertRedirect('https://'.self::DOMINIO.'/aviso-de-privacidad');
    }

    #[TestDox('medicion-E13: ninguna página pública se sirve por http: el aviso de privacidad, los términos, la página del borrado de la cuenta, el mapa del sitio y el archivo de robots también redirigen, sin su contenido')]
    public function test_medicion_e13_ninguna_pagina_publica_se_sirve_por_http(): void
    {
        foreach (['/', '/aviso-de-privacidad', '/terminos', '/borrado-de-cuenta', '/sitemap.xml', '/robots.txt'] as $direccion) {
            $respuesta = $this->pedirPagina('http://'.self::DOMINIO.$direccion)
                ->assertStatus(301)
                ->assertRedirect('https://'.self::DOMINIO.$direccion);

            $contenido = (string) $respuesta->getContent();
            $this->assertStringNotContainsString('data-seccion', $contenido, $direccion);
            $this->assertStringNotContainsString('Sitemap:', $contenido, $direccion);
            $this->assertStringNotContainsString('<urlset', $contenido, $direccion);
        }
    }

    #[TestDox('medicion-E13: por https, la portada responde normal')]
    public function test_medicion_e13_por_https_la_portada_responde(): void
    {
        $this->pedirPagina('https://'.self::DOMINIO.'/')->assertOk()->assertSee('data-seccion="encabezado"', false);
    }

    #[TestDox('medicion-E13: la configuración pide https en producción y en cualquier entorno que no sea el local ni las pruebas')]
    public function test_medicion_e13_la_configuracion_pide_https_fuera_de_local_y_de_las_pruebas(): void
    {
        $this->assertTrue($this->soloHttpsCon('production'));
        $this->assertTrue($this->soloHttpsCon('staging'));
        $this->assertTrue($this->soloHttpsCon('prueba'));
        $this->assertFalse($this->soloHttpsCon('local'));
        $this->assertFalse($this->soloHttpsCon('testing'));
    }

    #[TestDox('medicion-E13: en local, sin la configuración de producción, la portada se sirve por http como siempre')]
    public function test_medicion_e13_en_local_la_portada_se_sirve_por_http(): void
    {
        config(['app.solo_https' => false]);

        $this->pedirPagina('http://'.self::DOMINIO.'/')->assertOk();
    }

    /**
     * La configuración de producción: el entorno, solo https y la dirección del sitio con su dominio.
     */
    private function configuracionDeProduccion(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        config(['app.solo_https' => true, 'app.url' => 'https://'.self::DOMINIO]);
    }

    /**
     * Lo que vale app.solo_https al leer config/app.php con APP_ENV en el entorno que se pasa.
     */
    private function soloHttpsCon(string $entorno): bool
    {
        $antes = [$_SERVER['APP_ENV'] ?? null, $_ENV['APP_ENV'] ?? null, getenv('APP_ENV')];
        $_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = $entorno;
        putenv("APP_ENV={$entorno}");

        try {
            return (bool) (require config_path('app.php'))['solo_https'];
        } finally {
            [$servidor, $variables, $proceso] = $antes;
            $servidor === null ? $this->olvidar($_SERVER) : $_SERVER['APP_ENV'] = $servidor;
            $variables === null ? $this->olvidar($_ENV) : $_ENV['APP_ENV'] = $variables;
            putenv($proceso === false ? 'APP_ENV' : "APP_ENV={$proceso}");
        }
    }

    /**
     * @param  array<string, mixed>  $variables
     */
    private function olvidar(array &$variables): void
    {
        unset($variables['APP_ENV']);
    }
}
