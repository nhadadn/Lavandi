<?php

namespace Tests\Feature\Direcciones;

use App\Direcciones\Punto;
use App\Models\Direccion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Direcciones\Soporte\DireccionesDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * La dirección marcada en el mapa, que se guarda con el punto del pin sin geocodificar, y las favoritas con nombre,
 * que se eligen de su lista sin volver a consultar el mapa (NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #8;
 * ADR-0009; presupuesto-de-mapas.md, decisiones 3 y 4).
 *
 * POST /api/direcciones y GET /api/direcciones/favoritas, con sesión y por el componente de autorización.
 *
 * Escenarios: direcciones-E04, direcciones-E06, direcciones-E07 y direcciones-E08 · Spec:
 * openspec/changes/direcciones/specs/direcciones/spec.md
 */
class MapaYFavoritasTest extends TestCase
{
    use DireccionesDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    private const DIRECCIONES = '/api/direcciones';

    private const FAVORITAS = '/api/direcciones/favoritas';

    protected function setUp(): void
    {
        parent::setUp();

        // Ninguna prueba sale a la red: la geocodificación real no se llama nunca aquí.
        Http::preventStrayRequests();
    }

    #[TestDox('direcciones-E04: la dirección marcada en el mapa se guarda con el punto del pin, sin consultar la geocodificación')]
    public function test_direcciones_e04_la_direccion_marcada_en_el_mapa_se_guarda_con_el_punto_del_pin(): void
    {
        $plaza = $this->plazaDePrueba('Plaza de prueba 1');
        $this->zonaDePrueba($plaza, 'Zona de prueba Centro', $this->cuadrado(oeste: -120.0, sur: 10.0));
        $geocodificacion = $this->simularGeocodificacion();
        $cuenta = $this->cuentaDeUsuario();

        // Solo el pin, la colonia y las señas: sin calle ni número, que sin geocodificación inversa nadie sugiere.
        $respuesta = $this->comoCuenta($cuenta)->postJson(self::DIRECCIONES, [
            'origen' => 'mapa',
            'latitud' => 10.123456789,
            'longitud' => -119.987654321,
            'colonia' => 'Colonia de prueba',
            'referencias' => 'Portón de prueba',
        ])->assertCreated();

        $this->assertSame([], $geocodificacion->consultas);

        $direccion = Direccion::sole();
        $this->assertSame($cuenta->getKey(), $direccion->id_usuarios);
        $this->assertEqualsWithDelta(10.123456789, $this->puntoGuardado($direccion->id_publico)['latitud'], 1e-9);
        $this->assertEqualsWithDelta(-119.987654321, $this->puntoGuardado($direccion->id_publico)['longitud'], 1e-9);
        $this->assertSame($plaza->getKey(), $direccion->id_plazas);

        $respuesta->assertJsonPath('direccion.id_publico', $direccion->id_publico);
        $respuesta->assertJsonPath('direccion.colonia', 'Colonia de prueba');
        $respuesta->assertJsonPath('direccion.referencias', 'Portón de prueba');
        $respuesta->assertJsonPath('direccion.calle', null);
        $this->assertEqualsWithDelta(10.123456789, $respuesta->json('direccion.latitud'), 1e-9);
        $this->assertEqualsWithDelta(-119.987654321, $respuesta->json('direccion.longitud'), 1e-9);

        Http::assertNothingSent();
    }

    #[TestDox('direcciones-E04: la del mapa pide su pin y su colonia, y la escrita no lleva pin')]
    public function test_direcciones_e04_la_del_mapa_pide_su_pin_y_su_colonia_y_la_escrita_no_lleva_pin(): void
    {
        $geocodificacion = $this->simularGeocodificacion();
        $cuenta = $this->cuentaDeUsuario();
        $delMapa = ['origen' => 'mapa', 'latitud' => 10.5, 'longitud' => -119.5, 'colonia' => 'Colonia de prueba'];

        $casos = [
            'colonia' => array_diff_key($delMapa, ['colonia' => true]),
            'latitud' => array_diff_key($delMapa, ['latitud' => true]),
            'longitud' => array_diff_key($delMapa, ['longitud' => true]),
        ];

        foreach ($casos as $campo => $datos) {
            $this->comoCuenta($cuenta)->postJson(self::DIRECCIONES, $datos)
                ->assertUnprocessable()
                ->assertOnlyJsonValidationErrors([$campo]);
        }

        // Fuera de rango.
        $this->comoCuenta($cuenta)->postJson(self::DIRECCIONES, [...$delMapa, 'latitud' => 91, 'longitud' => -181])
            ->assertUnprocessable()
            ->assertOnlyJsonValidationErrors(['latitud', 'longitud']);

        // La escrita no trae pin: su punto sale de la geocodificación.
        $this->comoCuenta($cuenta)->postJson(self::DIRECCIONES, $this->direccionEscrita(['latitud' => 10.5, 'longitud' => -119.5]))
            ->assertUnprocessable()
            ->assertOnlyJsonValidationErrors(['latitud', 'longitud']);

        $this->assertSame([], $geocodificacion->consultas);
        $this->assertSame(0, Direccion::count());
    }

    #[TestDox('direcciones-E06: una dirección guardada como favorita con el nombre "Casa" aparece en la lista de favoritas')]
    public function test_direcciones_e06_una_direccion_guardada_como_favorita_aparece_en_la_lista(): void
    {
        $this->simularGeocodificacion(new Punto(10.25, -119.75));
        $cuenta = $this->cuentaDeUsuario();

        $guardada = $this->comoCuenta($cuenta)->postJson(
            self::DIRECCIONES,
            $this->direccionEscrita(['es_favorita' => true, 'alias' => 'Casa']),
        )->assertCreated();

        $guardada->assertJsonPath('direccion.alias', 'Casa');
        $guardada->assertJsonPath('direccion.es_favorita', true);

        $this->comoCuenta($cuenta)->getJson(self::FAVORITAS)
            ->assertOk()
            ->assertExactJson(['direcciones' => [$guardada->json('direccion')]]);
    }

    #[TestDox('direcciones-E06: una favorita sin nombre no se guarda')]
    public function test_direcciones_e06_una_favorita_sin_nombre_no_se_guarda(): void
    {
        $geocodificacion = $this->simularGeocodificacion();
        $cuenta = $this->cuentaDeUsuario();

        foreach ([[], ['alias' => ''], ['alias' => '   ']] as $sinNombre) {
            $this->comoCuenta($cuenta)
                ->postJson(self::DIRECCIONES, $this->direccionEscrita(['es_favorita' => true, ...$sinNombre]))
                ->assertUnprocessable()
                ->assertOnlyJsonValidationErrors(['alias'])
                ->assertJsonPath('errors.alias.0', 'Falta el nombre de la favorita.');
        }

        $this->assertSame([], $geocodificacion->consultas);
        $this->assertSame(0, Direccion::count());
    }

    #[TestDox('direcciones-E06: la lista de favoritas es solo de la cuenta, en el orden en que se guardaron')]
    public function test_direcciones_e06_la_lista_de_favoritas_es_solo_de_la_cuenta(): void
    {
        $this->simularGeocodificacion();
        $ana = $this->cuentaDeUsuario();
        $beto = $this->cuentaDeUsuario();

        foreach (['Casa', 'Oficina'] as $alias) {
            $this->comoCuenta($ana)
                ->postJson(self::DIRECCIONES, $this->direccionEscrita(['es_favorita' => true, 'alias' => $alias]))
                ->assertCreated();
        }
        $this->comoCuenta($beto)
            ->postJson(self::DIRECCIONES, $this->direccionEscrita(['es_favorita' => true, 'alias' => 'Casa de Beto']))
            ->assertCreated();

        $this->assertSame(
            ['Casa', 'Oficina'],
            array_column($this->comoCuenta($ana)->getJson(self::FAVORITAS)->assertOk()->json('direcciones'), 'alias'),
        );
        $this->assertSame(
            ['Casa de Beto'],
            array_column($this->comoCuenta($beto)->getJson(self::FAVORITAS)->assertOk()->json('direcciones'), 'alias'),
        );

        // Una cuenta sin favoritas recibe la lista vacía.
        $this->comoCuenta($this->cuentaDeUsuario())->getJson(self::FAVORITAS)
            ->assertOk()
            ->assertExactJson(['direcciones' => []]);
    }

    #[TestDox('direcciones-E07: elegir la favorita "Casa" de la lista da su punto guardado, sin ninguna consulta de geocodificación')]
    public function test_direcciones_e07_elegir_una_favorita_da_su_punto_guardado_sin_consultar(): void
    {
        $geocodificacion = $this->simularGeocodificacion(new Punto(10.25, -119.75));
        $cuenta = $this->cuentaDeUsuario();
        $this->comoCuenta($cuenta)
            ->postJson(self::DIRECCIONES, $this->direccionEscrita(['es_favorita' => true, 'alias' => 'Casa']))
            ->assertCreated();
        // Una sola consulta en su vida: al guardarla.
        $this->assertCount(1, $geocodificacion->consultas);

        foreach ([1, 2, 3] as $pedido) {
            $casa = $this->comoCuenta($cuenta)->getJson(self::FAVORITAS)->assertOk()->json('direcciones.0');

            $this->assertSame('Casa', $casa['alias'], "pedido {$pedido}");
            $this->assertSame([10.25, -119.75], [$casa['latitud'], $casa['longitud']], "pedido {$pedido}");
        }

        $this->assertCount(1, $geocodificacion->consultas);
        Http::assertNothingSent();
    }

    #[TestDox('direcciones-E08: una dirección que no es favorita se guarda, pero no entra a la lista de favoritas')]
    public function test_direcciones_e08_una_direccion_que_no_es_favorita_no_entra_a_la_lista(): void
    {
        $this->simularGeocodificacion();
        $cuenta = $this->cuentaDeUsuario();

        $deUnaVez = $this->comoCuenta($cuenta)->postJson(self::DIRECCIONES, $this->direccionEscrita())->assertCreated();
        $conFalso = $this->comoCuenta($cuenta)
            ->postJson(self::DIRECCIONES, $this->direccionEscrita(['es_favorita' => false]))
            ->assertCreated();

        $deUnaVez->assertJsonPath('direccion.es_favorita', false);
        $conFalso->assertJsonPath('direccion.es_favorita', false);
        $this->assertSame(2, Direccion::where('id_usuarios', $cuenta->getKey())->count());

        $this->comoCuenta($cuenta)->getJson(self::FAVORITAS)
            ->assertOk()
            ->assertExactJson(['direcciones' => []]);
    }

    #[TestDox('direcciones-E06: sin sesión no hay lista de favoritas')]
    public function test_direcciones_e06_sin_sesion_no_hay_lista_de_favoritas(): void
    {
        $this->getJson(self::FAVORITAS)->assertUnauthorized()->assertJsonMissingPath('direcciones');
    }
}
