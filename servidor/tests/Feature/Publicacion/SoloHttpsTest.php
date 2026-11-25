<?php

namespace Tests\Feature\Publicacion;

use App\Models\EventoPasarela;
use App\Models\Rol;
use App\Models\Usuario;
use Database\Factories\UsuarioFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as Ruta;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Autorizacion\Soporte\TablaDeRutas;
use Tests\TestCase;

/**
 * Con la configuración de producción, el servidor atiende solo por https: el panel redirige a la misma dirección por
 * https y la API no procesa ninguna petición por http ni devuelve ningún dato (NSV-2026-001-v8.pdf:20:ANEXO A, Sección
 * A.3, "Toda la información que viaja entre el teléfono del usuario y el servidor va codificada"; SEG-1).
 *
 * El mecanismo es el middleware global App\Http\Middleware\SoloHttps, el mismo de la landing (medicion-E13). La API no
 * redirige: una app que siguiera la redirección volvería a mandar por http lo que ya mandó, y por eso se rechaza con 403 y
 * solo su mensaje, sin nada a dónde ir. Las rutas salen de la tabla del enrutador: una ruta nueva entra sola.
 *
 * Escenarios: publicacion-E04 y publicacion-E05 · Spec: openspec/changes/publicacion/specs/publicacion/spec.md · Tarea
 * 2.1
 */
class SoloHttpsTest extends TestCase
{
    use RefreshDatabase;
    use TablaDeRutas;

    private const DOMINIO = 'dominio.prueba.test';

    /** Lo único que responde la API a una petición por http. */
    private const RECHAZO = ['message' => 'La API solo atiende por https.'];

    protected function setUp(): void
    {
        parent::setUp();

        // La configuración de producción.
        $this->app->detectEnvironment(fn (): string => 'production');
        config(['app.solo_https' => true, 'app.url' => 'https://'.self::DOMINIO, 'app.debug' => false]);
    }

    #[TestDox('publicacion-E04: quien pide el panel por http recibe una redirección permanente a la misma dirección por https')]
    public function test_publicacion_e04_el_panel_por_http_redirige_a_https(): void
    {
        foreach (['/admin', '/admin/login', '/admin/lavanderias', '/admin/login?desde=prueba'] as $direccion) {
            $this->get('http://'.self::DOMINIO.$direccion)
                ->assertStatus(301)
                ->assertRedirect('https://'.self::DOMINIO.$direccion);
        }
    }

    #[TestDox('publicacion-E04: ninguna página se sirve por http: cada ruta que no es de la API redirige a https, sin abrir sesión ni procesar nada')]
    public function test_publicacion_e04_ninguna_pagina_se_sirve_por_http(): void
    {
        $rutas = array_diff_key($this->rutasRegistradas(), $this->rutasDeLaApi());
        $this->assertGreaterThan(20, count($rutas), 'La tabla trae las rutas del panel, de la landing y de Livewire.');

        $fallas = [];

        foreach ($rutas as $nombre => $ruta) {
            $direccion = $this->direccionDe($ruta);
            $respuesta = $this->call($this->metodoDe($nombre), 'http://'.self::DOMINIO.$direccion);

            if ($respuesta->getStatusCode() !== 301 || $respuesta->headers->get('Location') !== 'https://'.self::DOMINIO.$direccion) {
                $fallas[] = "{$nombre}: {$respuesta->getStatusCode()} {$respuesta->headers->get('Location')}";
            }

            if ($respuesta->headers->getCookies() !== []) {
                $fallas[] = "{$nombre}: abrió una sesión o puso una cookie por http";
            }
        }

        $this->assertSame([], $fallas);
    }

    #[TestDox('publicacion-E04: por https, el panel se sirve normal')]
    public function test_publicacion_e04_por_https_el_panel_se_sirve(): void
    {
        $this->get('https://'.self::DOMINIO.'/admin/login')->assertOk();
    }

    #[TestDox('publicacion-E05: el inicio de sesión de la app por http no se procesa: no abre sesión, no da token y no redirige a ningún lado')]
    public function test_publicacion_e05_el_inicio_de_sesion_por_http_no_se_procesa(): void
    {
        $cuenta = Usuario::factory()->conRol(Rol::USUARIO)->create(['correo' => 'persona.de.prueba@ejemplo.test']);

        $respuesta = $this->postJson('http://'.self::DOMINIO.'/api/sesion', [
            'correo' => $cuenta->correo,
            'password' => UsuarioFactory::CONTRASENA,
        ]);

        $this->assertRechazo($respuesta);
        $this->assertSame(0, PersonalAccessToken::query()->count(), 'No se creó ningún token.');
    }

    #[TestDox('publicacion-E05: la API por http no devuelve ningún dato, aunque la petición traiga un token válido')]
    public function test_publicacion_e05_la_api_por_http_no_devuelve_datos(): void
    {
        $cuenta = Usuario::factory()->conRol(Rol::USUARIO)->create(['correo' => 'persona.de.prueba@ejemplo.test']);
        $token = $cuenta->createToken('app')->plainTextToken;

        $respuesta = $this->withToken($token)->getJson('http://'.self::DOMINIO.'/api/cuenta');

        $this->assertRechazo($respuesta);
        $this->assertStringNotContainsString('persona.de.prueba', (string) $respuesta->getContent());

        // Por https, la misma petición sí responde: el rechazo es por http, no por la cuenta.
        $this->withToken($token)->getJson('https://'.self::DOMINIO.'/api/cuenta')
            ->assertOk()
            ->assertJsonPath('usuario.correo', 'persona.de.prueba@ejemplo.test');
    }

    #[TestDox('publicacion-E05: el aviso de la pasarela por http no se procesa ni se guarda')]
    public function test_publicacion_e05_el_aviso_de_la_pasarela_por_http_no_se_procesa(): void
    {
        $respuesta = $this->postJson('http://'.self::DOMINIO.'/api/pasarela/avisos', ['id' => 'evento-de-prueba', 'type' => 'order.paid']);

        $this->assertRechazo($respuesta);
        $this->assertSame(0, EventoPasarela::query()->count());
    }

    #[TestDox('publicacion-E05: ninguna ruta de la API atiende por http: cada una responde 403 con solo su mensaje, sin redirigir')]
    public function test_publicacion_e05_ninguna_ruta_de_la_api_atiende_por_http(): void
    {
        $rutas = $this->rutasDeLaApi();
        $this->assertGreaterThan(20, count($rutas), 'La tabla trae las rutas de la API.');

        $fallas = [];

        foreach ($rutas as $nombre => $ruta) {
            $respuesta = $this->json($this->metodoDe($nombre), 'http://'.self::DOMINIO.$this->direccionDe($ruta));

            if ($respuesta->getStatusCode() !== 403 || $respuesta->json() !== self::RECHAZO || $respuesta->headers->has('Location')) {
                $fallas[] = "{$nombre}: {$respuesta->getStatusCode()} {$respuesta->getContent()}";
            }
        }

        $this->assertSame([], $fallas);
    }

    /**
     * Que la API rechazó la petición por http: 403, solo su mensaje y nada a dónde ir.
     */
    private function assertRechazo(TestResponse $respuesta): void
    {
        $respuesta->assertStatus(403)->assertExactJson(self::RECHAZO);
        $this->assertFalse($respuesta->headers->has('Location'), 'La API no redirige.');
    }

    /**
     * La dirección de la ruta, con un valor cualquiera en cada parámetro.
     */
    private function direccionDe(Ruta $ruta): string
    {
        return '/'.ltrim((string) preg_replace_callback(
            '/\{(\w+)\??\}/',
            fn (): string => (string) Str::ulid(),
            $ruta->uri(),
        ), '/');
    }
}
