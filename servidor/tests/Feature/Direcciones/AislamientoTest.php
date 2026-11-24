<?php

namespace Tests\Feature\Direcciones;

use App\Autorizacion\Autorizacion;
use App\Models\Direccion;
use App\Models\Lavanderia;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Direcciones\Soporte\DireccionesDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * Cada cuenta ve y usa solo sus direcciones: la de otra cuenta, pedida por su id_publico en la lista, en el detalle o
 * en la recogida y la entrega de un pedido, responde igual que una que no existe y no devuelve ningún dato de ella
 * (NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3, Separación por tipo de usuario; ADR-0019, decisión 3; c3-api.md, la
 * prueba anti-fuga).
 *
 * La regla vive en el componente único de autorización, App\Autorizacion\Autorizacion (CLAUDE.md §9.5).
 *
 * Escenarios: direcciones-E14 · Spec: openspec/changes/direcciones/specs/direcciones/spec.md
 */
class AislamientoTest extends TestCase
{
    use DireccionesDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    /** Un ULID bien formado, en el alfabeto de Crockford, que no es de ninguna dirección. */
    private const ULID_SIN_DIRECCION = '01jd8x6m3v2c9q4w5e7r8t0y1z';

    private const NO_EXISTE = ['message' => 'No existe la dirección.'];

    private Usuario $ana;

    private Usuario $beto;

    /** @var array<string, mixed> La favorita "Casa" de Ana, como la da la API. */
    private array $casaDeAna;

    /** @var array<string, mixed> La favorita "Casa de Beto", como la da la API. */
    private array $casaDeBeto;

    /** @var array<string, mixed> Una dirección de Beto que no es favorita. */
    private array $oficinaDeBeto;

    private Lavanderia $lavanderia;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        $plaza = $this->plazaDePrueba('Plaza de prueba 1');
        $this->zonaDePrueba($plaza, 'Zona de prueba Centro', $this->cuadrado(oeste: -120.0, sur: 10.0));
        $this->lavanderia = Lavanderia::factory()->en($plaza)->create();

        $this->ana = $this->cuentaDeUsuario();
        $this->beto = $this->cuentaDeUsuario();

        $this->casaDeAna = $this->guardar($this->ana, [
            'es_favorita' => true,
            'alias' => 'Casa',
            'colonia' => 'Colonia de Ana',
            'referencias' => 'Referencias de Ana',
        ]);
        $this->casaDeBeto = $this->guardar($this->beto, [
            'es_favorita' => true,
            'alias' => 'Casa de Beto',
            'colonia' => 'Colonia de Beto',
            'referencias' => 'Referencias de Beto',
        ]);
        $this->oficinaDeBeto = $this->guardar($this->beto, ['colonia' => 'Colonia de la oficina de Beto']);
    }

    #[TestDox('direcciones-E14: la lista de favoritas de una cuenta no trae ninguna dirección de otra')]
    public function test_direcciones_e14_la_lista_de_una_cuenta_no_trae_ninguna_direccion_de_otra(): void
    {
        $respuesta = $this->comoCuenta($this->ana)->getJson('/api/direcciones/favoritas')->assertOk();

        $respuesta->assertExactJson(['direcciones' => [$this->casaDeAna]]);
        $this->assertSinDatosDeBeto($respuesta);
    }

    #[TestDox('direcciones-E14: el detalle de una dirección de otra cuenta responde igual que una que no existe')]
    public function test_direcciones_e14_el_detalle_de_una_direccion_de_otra_cuenta_responde_como_si_no_existiera(): void
    {
        foreach ([$this->casaDeBeto, $this->oficinaDeBeto] as $deBeto) {
            $respuesta = $this->comoCuenta($this->ana)->getJson('/api/direcciones/'.$deBeto['id_publico']);

            $respuesta->assertNotFound()->assertExactJson(self::NO_EXISTE);
            $this->assertSinDatosDeBeto($respuesta);
        }

        $this->comoCuenta($this->ana)->getJson('/api/direcciones/'.self::ULID_SIN_DIRECCION)
            ->assertNotFound()
            ->assertExactJson(self::NO_EXISTE);

        // Su dueño sí la ve, y Ana ve la suya, también con el ULID en mayúsculas.
        $this->comoCuenta($this->beto)->getJson('/api/direcciones/'.$this->oficinaDeBeto['id_publico'])
            ->assertOk()
            ->assertExactJson(['direccion' => $this->oficinaDeBeto]);
        $this->comoCuenta($this->ana)->getJson('/api/direcciones/'.Str::upper($this->casaDeAna['id_publico']))
            ->assertOk()
            ->assertExactJson(['direccion' => $this->casaDeAna]);
    }

    #[TestDox('direcciones-E14: una dirección de otra cuenta no se usa como recogida ni como entrega, y responde igual que una que no existe')]
    public function test_direcciones_e14_una_direccion_de_otra_cuenta_no_se_usa_en_un_pedido(): void
    {
        $intentos = [
            ['recogida' => $this->casaDeBeto['id_publico']],
            ['recogida' => $this->casaDeAna['id_publico'], 'entrega' => $this->oficinaDeBeto['id_publico']],
            ['recogida' => self::ULID_SIN_DIRECCION],
            ['recogida' => $this->casaDeAna['id_publico'], 'entrega' => self::ULID_SIN_DIRECCION],
        ];

        foreach ($intentos as $direcciones) {
            $respuesta = $this->comoCuenta($this->ana)->postJson('/api/direcciones/recogida-y-entrega', [
                'lavanderia' => $this->lavanderia->id_publico,
                ...$direcciones,
            ]);

            $respuesta->assertNotFound()->assertExactJson(self::NO_EXISTE);
            $this->assertSinDatosDeBeto($respuesta);
        }

        // Con las suyas, Ana sí elige.
        $this->comoCuenta($this->ana)->postJson('/api/direcciones/recogida-y-entrega', [
            'lavanderia' => $this->lavanderia->id_publico,
            'recogida' => $this->casaDeAna['id_publico'],
        ])->assertOk();
    }

    #[TestDox('direcciones-E14: una dirección borrada responde igual que una que no existe, también para su dueño')]
    public function test_direcciones_e14_una_direccion_borrada_responde_como_si_no_existiera(): void
    {
        Direccion::where('id_publico', $this->casaDeAna['id_publico'])->sole()->delete();

        $this->comoCuenta($this->ana)->getJson('/api/direcciones/'.$this->casaDeAna['id_publico'])
            ->assertNotFound()
            ->assertExactJson(self::NO_EXISTE);
        $this->comoCuenta($this->ana)->getJson('/api/direcciones/favoritas')
            ->assertOk()
            ->assertExactJson(['direcciones' => []]);
    }

    #[TestDox('direcciones-E14: el componente de autorización da a cada cuenta solo sus direcciones')]
    public function test_direcciones_e14_el_componente_de_autorizacion_da_a_cada_cuenta_solo_sus_direcciones(): void
    {
        $autorizacion = app(Autorizacion::class);

        $this->assertSame(
            [$this->casaDeAna['id_publico']],
            $autorizacion->direccionesVisibles($this->ana)->pluck('id_publico')->all(),
        );
        $this->assertEqualsCanonicalizing(
            [$this->casaDeBeto['id_publico'], $this->oficinaDeBeto['id_publico']],
            $autorizacion->direccionesVisibles($this->beto)->pluck('id_publico')->all(),
        );
    }

    /**
     * Guarda por la API una dirección marcada en el mapa de la cuenta, dentro de la zona, y la devuelve como la da la
     * API.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function guardar(Usuario $cuenta, array $datos): array
    {
        return $this->comoCuenta($cuenta)->postJson('/api/direcciones', [
            'origen' => 'mapa',
            'latitud' => 10.5,
            'longitud' => -119.5,
            ...$datos,
        ])->assertCreated()->json('direccion');
    }

    /**
     * La respuesta no trae ningún dato de las direcciones de Beto.
     */
    private function assertSinDatosDeBeto(TestResponse $respuesta): void
    {
        foreach ([$this->casaDeBeto, $this->oficinaDeBeto] as $deBeto) {
            foreach (['id_publico', 'alias', 'colonia', 'referencias'] as $campo) {
                if ($deBeto[$campo] !== null) {
                    $this->assertStringNotContainsString((string) $deBeto[$campo], $respuesta->getContent(), $campo);
                }
            }
        }
    }
}
