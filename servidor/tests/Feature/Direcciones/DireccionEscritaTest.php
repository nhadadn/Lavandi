<?php

namespace Tests\Feature\Direcciones;

use App\Direcciones\GeocodificacionNoDisponible;
use App\Direcciones\Geocodificador;
use App\Direcciones\Punto;
use App\Models\Direccion;
use App\Models\Municipio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Direcciones\Soporte\CatalogoDePrueba;
use Tests\Feature\Direcciones\Soporte\DireccionesDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * Guardar una dirección escrita: calle y número, colonia y el municipio del catálogo; la colonia es obligatoria, y el
 * servidor la convierte en un punto del mapa una sola vez, con el servicio de geocodificación detrás de una interfaz que
 * aquí se simula y con el municipio y el estado del catálogo en el texto. La plaza de la dirección es la de la zona que
 * contiene el punto, o ninguna (NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #8; ADR-0009, decisión 4;
 * openspec/changes/direcciones/design.md, decisiones 1, 3 y 10).
 *
 * POST /api/direcciones, con sesión y por el componente de autorización.
 *
 * Escenarios: direcciones-E01, direcciones-E02 y direcciones-E03 · Spec:
 * openspec/changes/direcciones/specs/direcciones/spec.md
 */
class DireccionEscritaTest extends TestCase
{
    use DireccionesDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    private const DIRECCION = '/api/direcciones';

    protected function setUp(): void
    {
        parent::setUp();

        // Ninguna prueba sale a la red: la geocodificación real no se llama nunca aquí.
        Http::preventStrayRequests();
    }

    #[TestDox('direcciones-E01: una dirección escrita se guarda con el punto que devolvió una sola consulta de geocodificación')]
    public function test_direcciones_e01_una_direccion_escrita_se_guarda_con_su_punto(): void
    {
        $plaza = $this->plazaDePrueba('Plaza de prueba 1');
        $this->zonaDePrueba($plaza, 'Zona de prueba Centro', $this->cuadrado(oeste: -120.0, sur: 10.0));
        $geocodificacion = $this->simularGeocodificacion(new Punto(10.25, -119.75));
        $cuenta = $this->cuentaDeUsuario();

        $respuesta = $this->comoCuenta($cuenta)->postJson(self::DIRECCION, $this->direccionEscrita([
            'numero_interior' => '4',
            'codigo_postal' => '00000',
            'referencias' => 'Portón de prueba',
        ]))->assertCreated();

        // Una sola consulta, con la calle y el número, la colonia, el código postal, y el municipio y el estado del
        // catálogo (design.md, decisión 10).
        $this->assertSame(
            ['Calle de prueba 123, Colonia de prueba, 00000 Municipio de prueba, Estado de prueba'],
            $geocodificacion->consultas,
        );
        $this->assertSame([CatalogoDePrueba::MUNICIPIO_DE_PRUEBA], $geocodificacion->municipios);

        $direccion = Direccion::sole();
        $this->assertSame($cuenta->getKey(), $direccion->id_usuarios);
        $this->assertSame(['latitud' => 10.25, 'longitud' => -119.75], $this->puntoGuardado($direccion->id_publico));
        // La plaza sale de la zona que contiene el punto.
        $this->assertSame($plaza->getKey(), $direccion->id_plazas);
        // El municipio, como llave al catálogo.
        $this->assertSame(CatalogoDePrueba::municipio()->getKey(), $direccion->id_municipios);
        $this->assertSame(
            ['Calle de prueba', '123', '4', 'Colonia de prueba', '00000', 'Portón de prueba'],
            [
                $direccion->calle, $direccion->numero_exterior, $direccion->numero_interior, $direccion->colonia,
                $direccion->codigo_postal, $direccion->referencias,
            ],
        );

        $respuesta->assertExactJson(['direccion' => [
            'id_publico' => $direccion->id_publico,
            'alias' => null,
            'es_favorita' => false,
            'calle' => 'Calle de prueba',
            'numero_exterior' => '123',
            'numero_interior' => '4',
            'colonia' => 'Colonia de prueba',
            'codigo_postal' => '00000',
            'municipio' => 'Municipio de prueba',
            'estado' => 'Estado de prueba',
            'referencias' => 'Portón de prueba',
            'latitud' => 10.25,
            'longitud' => -119.75,
        ]]);

        Http::assertNothingSent();
    }

    #[TestDox('direcciones-E01: un punto fuera de toda zona activa se guarda sin plaza')]
    public function test_direcciones_e01_un_punto_fuera_de_toda_zona_activa_se_guarda_sin_plaza(): void
    {
        $plaza = $this->plazaDePrueba('Plaza de prueba 1');
        $this->zonaDePrueba($plaza, 'Zona de prueba Centro', $this->cuadrado(oeste: -120.0, sur: 10.0));
        // Una zona inactiva no cuenta, aunque contenga el punto (plazas-E13).
        $this->zonaDePrueba($plaza, 'Zona de prueba Norte', $this->cuadrado(oeste: -120.0, sur: 11.0), activa: false);

        foreach ([new Punto(10.5, -110.5), new Punto(11.5, -119.5)] as $punto) {
            $this->simularGeocodificacion($punto);

            $this->comoCuenta($this->cuentaDeUsuario())
                ->postJson(self::DIRECCION, $this->direccionEscrita())
                ->assertCreated();
        }

        $this->assertSame([null, null], Direccion::orderBy('id_direcciones')->pluck('id_plazas')->all());
    }

    #[TestDox('direcciones-E02: sin colonia, el servidor rechaza la dirección con el error del campo y no consulta la geocodificación')]
    public function test_direcciones_e02_sin_colonia_el_servidor_rechaza_la_direccion_y_no_consulta(): void
    {
        $geocodificacion = $this->simularGeocodificacion();
        $cuenta = $this->cuentaDeUsuario();

        $sinColonia = $this->direccionEscrita();
        unset($sinColonia['colonia']);

        foreach ([$sinColonia, $this->direccionEscrita(['colonia' => '']), $this->direccionEscrita(['colonia' => '   '])] as $datos) {
            $this->comoCuenta($cuenta)->postJson(self::DIRECCION, $datos)
                ->assertUnprocessable()
                ->assertOnlyJsonValidationErrors(['colonia'])
                ->assertJsonPath('errors.colonia.0', 'Falta la colonia.');
        }

        $this->assertSame([], $geocodificacion->consultas);
        $this->assertSame(0, Direccion::count());
    }

    #[TestDox('direcciones-E01: sin calle, número o municipio, la dirección escrita no se guarda ni se consulta')]
    public function test_direcciones_e01_sin_calle_numero_o_municipio_la_direccion_escrita_no_se_guarda(): void
    {
        $geocodificacion = $this->simularGeocodificacion();
        $cuenta = $this->cuentaDeUsuario();

        foreach (['calle', 'numero_exterior', 'municipio'] as $campo) {
            $datos = $this->direccionEscrita();
            unset($datos[$campo]);

            $this->comoCuenta($cuenta)->postJson(self::DIRECCION, $datos)
                ->assertUnprocessable()
                ->assertOnlyJsonValidationErrors([$campo]);
        }

        // Sin decir cómo se capturó, tampoco.
        $datos = $this->direccionEscrita();
        unset($datos['origen']);
        $this->comoCuenta($cuenta)->postJson(self::DIRECCION, $datos)
            ->assertUnprocessable()
            ->assertOnlyJsonValidationErrors(['origen']);

        $this->assertSame([], $geocodificacion->consultas);
        $this->assertSame(0, Direccion::count());
    }

    #[TestDox('direcciones-E03: una dirección que el servicio no encuentra no se guarda, y el servidor responde que no la encontró')]
    public function test_direcciones_e03_una_direccion_que_el_servicio_no_encuentra_no_se_guarda(): void
    {
        $geocodificacion = $this->simularGeocodificacion(null);

        $this->comoCuenta($this->cuentaDeUsuario())
            ->postJson(self::DIRECCION, $this->direccionEscrita())
            ->assertUnprocessable()
            ->assertExactJson([
                'message' => 'No encontramos la dirección. Márcala en el mapa.',
                'motivo' => 'direccion_no_encontrada',
            ]);

        $this->assertCount(1, $geocodificacion->consultas);
        // No le inventa un punto: no hay dirección guardada.
        $this->assertSame(0, DB::table('direcciones')->count());
    }

    #[TestDox('direcciones-E03: si el servicio de geocodificación no responde, no se inventa un punto ni se guarda nada')]
    public function test_direcciones_e03_si_el_servicio_no_responde_no_se_inventa_un_punto(): void
    {
        $this->app->instance(Geocodificador::class, new class implements Geocodificador
        {
            public function geocodificar(string $texto, Municipio $municipio): ?Punto
            {
                throw GeocodificacionNoDisponible::porque('el servicio de prueba no responde');
            }
        });

        $this->comoCuenta($this->cuentaDeUsuario())
            ->postJson(self::DIRECCION, $this->direccionEscrita())
            ->assertServiceUnavailable()
            ->assertExactJson([
                'message' => 'No pudimos buscar la dirección. Márcala en el mapa o inténtalo más tarde.',
                'motivo' => 'geocodificacion_no_disponible',
            ]);

        $this->assertSame(0, DB::table('direcciones')->count());
    }

    #[TestDox('direcciones-E01: sin sesión no se guarda ninguna dirección ni se consulta la geocodificación')]
    public function test_direcciones_e01_sin_sesion_no_se_guarda_ninguna_direccion(): void
    {
        $geocodificacion = $this->simularGeocodificacion();

        $this->postJson(self::DIRECCION, $this->direccionEscrita())->assertUnauthorized();

        $this->assertSame([], $geocodificacion->consultas);
        $this->assertSame(0, Direccion::count());
    }
}
