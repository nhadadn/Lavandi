<?php

namespace Tests\Feature\Direcciones;

use App\Models\Lavanderia;
use App\Models\Plaza;
use App\Models\Usuario;
use App\Models\Zona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Direcciones\Soporte\DireccionesDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * La recogida y la entrega del pedido: el servidor acepta solo direcciones cuyo punto cae dentro de una zona de
 * servicio activa de la plaza de la lavandería elegida, y dice el motivo cuando rechaza una
 * (NSV-2026-001-v8.pdf:4:Cláusula I; :16:ANEXO A, Sección A.3, Módulo A #8; :30:ANEXO B, Sección B.5; ADR-0004).
 *
 * El servidor solo las valida: la copia en el pedido la hace resumen-y-confirmacion (design.md, "Lo que no escribe").
 * POST /api/direcciones/recogida-y-entrega, con sesión y por el componente de autorización.
 *
 * Las plazas y las zonas son ficticias, en el océano Pacífico: la plaza de prueba A con su zona en el cuadrado de un
 * grado con esquina suroeste en (-120, 10), y la plaza de prueba B con la suya en (-118, 10).
 *
 * Escenarios: direcciones-E09, direcciones-E10, direcciones-E11 y direcciones-E12, y el uso en el pedido de
 * direcciones-E07 y direcciones-E08 · Spec: openspec/changes/direcciones/specs/direcciones/spec.md
 */
class ZonaDeServicioTest extends TestCase
{
    use DireccionesDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    private const RECOGIDA_Y_ENTREGA = '/api/direcciones/recogida-y-entrega';

    private const FUERA_DE_ZONA = [
        'motivo' => 'fuera_de_la_zona_de_servicio',
        'mensaje' => 'La dirección está fuera de la zona de servicio.',
    ];

    private const OTRA_PLAZA = [
        'motivo' => 'fuera_de_la_plaza_de_la_lavanderia',
        'mensaje' => 'Esta lavandería no da servicio en esa dirección.',
    ];

    private Plaza $plazaA;

    private Zona $zonaA;

    private Lavanderia $lavanderia;

    private Usuario $cuenta;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        $this->plazaA = $this->plazaDePrueba('Plaza de prueba A');
        $this->zonaA = $this->zonaDePrueba($this->plazaA, 'Zona de prueba A', $this->cuadrado(oeste: -120.0, sur: 10.0));
        $plazaB = $this->plazaDePrueba('Plaza de prueba B');
        $this->zonaDePrueba($plazaB, 'Zona de prueba B', $this->cuadrado(oeste: -118.0, sur: 10.0));

        $this->lavanderia = Lavanderia::factory()->en($this->plazaA, latitud: 10.5, longitud: -119.5)->create();
        $this->cuenta = $this->cuentaDeUsuario();
    }

    #[TestDox('direcciones-E09: con la entrega en la misma dirección, "Casa" es la recogida y la entrega')]
    public function test_direcciones_e09_con_la_entrega_en_la_misma_direccion_casa_es_la_recogida_y_la_entrega(): void
    {
        $casa = $this->guardarDelMapa(10.5, -119.5, ['es_favorita' => true, 'alias' => 'Casa']);

        $respuesta = $this->elegir(['recogida' => $casa['id_publico']])->assertOk();

        // Las dos llegan completas, con su punto guardado, para el resumen del pedido.
        $respuesta->assertExactJson(['recogida' => $casa, 'entrega' => $casa]);
    }

    #[TestDox('direcciones-E10: con la entrega en otra dirección, "Casa" es la recogida y "Oficina" la entrega')]
    public function test_direcciones_e10_con_la_entrega_en_otra_direccion_casa_es_la_recogida_y_oficina_la_entrega(): void
    {
        $casa = $this->guardarDelMapa(10.5, -119.5, ['es_favorita' => true, 'alias' => 'Casa']);
        $oficina = $this->guardarDelMapa(10.2, -119.2, ['es_favorita' => true, 'alias' => 'Oficina']);

        $this->elegir(['recogida' => $casa['id_publico'], 'entrega' => $oficina['id_publico']])
            ->assertOk()
            ->assertExactJson(['recogida' => $casa, 'entrega' => $oficina]);

        // La entrega igual a la recogida, dicha de forma explícita, también vale.
        $this->elegir(['recogida' => $casa['id_publico'], 'entrega' => $casa['id_publico']])
            ->assertOk()
            ->assertExactJson(['recogida' => $casa, 'entrega' => $casa]);
    }

    #[TestDox('direcciones-E07 y direcciones-E08: la favorita "Casa" y una dirección que no es favorita se usan con su punto guardado, sin geocodificar')]
    public function test_direcciones_e07_y_e08_la_favorita_y_una_que_no_es_favorita_se_usan_con_su_punto_guardado(): void
    {
        $geocodificacion = $this->simularGeocodificacion();
        $casa = $this->comoCuenta($this->cuenta)
            ->postJson('/api/direcciones', $this->direccionEscrita(['es_favorita' => true, 'alias' => 'Casa']))
            ->assertCreated()
            ->json('direccion');
        $deUnaVez = $this->guardarDelMapa(10.2, -119.2);
        $this->assertCount(1, $geocodificacion->consultas);

        $respuesta = $this->elegir(['recogida' => $casa['id_publico'], 'entrega' => $deUnaVez['id_publico']])
            ->assertOk();

        $this->assertSame([10.5, -119.5], [$respuesta->json('recogida.latitud'), $respuesta->json('recogida.longitud')]);
        $respuesta->assertJsonPath('entrega.id_publico', $deUnaVez['id_publico']);
        $respuesta->assertJsonPath('entrega.es_favorita', false);

        // Elegirlas no consultó la geocodificación.
        $this->assertCount(1, $geocodificacion->consultas);
        Http::assertNothingSent();
    }

    #[TestDox('direcciones-E11: una dirección fuera de toda zona de servicio se rechaza con el motivo "fuera de la zona de servicio", y se puede elegir otra')]
    public function test_direcciones_e11_una_direccion_fuera_de_toda_zona_se_rechaza_con_su_motivo(): void
    {
        $lejana = $this->guardarDelMapa(10.5, -110.5);
        $casa = $this->guardarDelMapa(10.5, -119.5, ['es_favorita' => true, 'alias' => 'Casa']);

        $this->assertRechazo($this->elegir(['recogida' => $lejana['id_publico']]), ['recogida' => self::FUERA_DE_ZONA]);
        $this->assertRechazo(
            $this->elegir(['recogida' => $casa['id_publico'], 'entrega' => $lejana['id_publico']]),
            ['entrega' => self::FUERA_DE_ZONA],
        );

        // Elige otra, y pasa.
        $this->elegir(['recogida' => $casa['id_publico']])->assertOk();
    }

    #[TestDox('direcciones-E11: una zona que se desactivó deja de dar servicio, aunque la dirección se guardara cuando estaba activa')]
    public function test_direcciones_e11_una_zona_que_se_desactivo_deja_de_dar_servicio(): void
    {
        $casa = $this->guardarDelMapa(10.5, -119.5);
        $this->elegir(['recogida' => $casa['id_publico']])->assertOk();

        $this->zonaA->update(['activa' => false]);

        $this->assertRechazo($this->elegir(['recogida' => $casa['id_publico']]), ['recogida' => self::FUERA_DE_ZONA]);
    }

    #[TestDox('direcciones-E12: una dirección en una zona de la plaza B se rechaza para un pedido con una lavandería de la plaza A, con su motivo')]
    public function test_direcciones_e12_una_direccion_de_otra_plaza_se_rechaza_con_su_motivo(): void
    {
        $enB = $this->guardarDelMapa(10.5, -117.5);
        $casa = $this->guardarDelMapa(10.5, -119.5);
        $lejana = $this->guardarDelMapa(10.5, -110.5);

        $this->assertRechazo($this->elegir(['recogida' => $enB['id_publico']]), ['recogida' => self::OTRA_PLAZA]);

        // Las dos rechazadas, cada una con su motivo.
        $this->assertRechazo(
            $this->elegir(['recogida' => $lejana['id_publico'], 'entrega' => $enB['id_publico']]),
            ['recogida' => self::FUERA_DE_ZONA, 'entrega' => self::OTRA_PLAZA],
        );

        // La misma dirección de la plaza B sí sirve con una lavandería de la plaza B.
        $deB = Lavanderia::factory()->en(Plaza::where('nombre', 'Plaza de prueba B')->sole(), 10.5, -117.5)->create();
        $this->elegir(['recogida' => $enB['id_publico']], $deB)->assertOk();

        // Y la de la plaza A, no.
        $this->assertRechazo($this->elegir(['recogida' => $casa['id_publico']], $deB), ['recogida' => self::OTRA_PLAZA]);
    }

    #[TestDox('direcciones-E12: una lavandería que el usuario no ve responde como si no existiera')]
    public function test_direcciones_e12_una_lavanderia_que_el_usuario_no_ve_responde_como_si_no_existiera(): void
    {
        $casa = $this->guardarDelMapa(10.5, -119.5);
        $suspendida = Lavanderia::factory()->en($this->plazaA)->suspendida()->create();

        foreach ([$suspendida->id_publico, '01jd8x6m3v2c9q4w5e7r8t0y1z', 'no-es-un-ulid'] as $lavanderia) {
            $this->comoCuenta($this->cuenta)
                ->postJson(self::RECOGIDA_Y_ENTREGA, ['lavanderia' => $lavanderia, 'recogida' => $casa['id_publico']])
                ->assertNotFound()
                ->assertExactJson(['message' => 'No existe la lavandería.']);
        }
    }

    #[TestDox('direcciones-E09: sin lavandería o sin recogida no hay elección')]
    public function test_direcciones_e09_sin_lavanderia_o_sin_recogida_no_hay_eleccion(): void
    {
        $this->comoCuenta($this->cuenta)->postJson(self::RECOGIDA_Y_ENTREGA, [])
            ->assertUnprocessable()
            ->assertOnlyJsonValidationErrors(['lavanderia', 'recogida']);
    }

    /**
     * Guarda por la API una dirección marcada en el mapa, de la cuenta de la prueba, y la devuelve como la da la API.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function guardarDelMapa(float $latitud, float $longitud, array $datos = []): array
    {
        return $this->comoCuenta($this->cuenta)->postJson('/api/direcciones', [
            'origen' => 'mapa',
            'latitud' => $latitud,
            'longitud' => $longitud,
            'colonia' => 'Colonia de prueba',
            ...$datos,
        ])->assertCreated()->json('direccion');
    }

    /**
     * @param  array<string, mixed>  $direcciones
     */
    private function elegir(array $direcciones, ?Lavanderia $lavanderia = null): TestResponse
    {
        return $this->comoCuenta($this->cuenta)->postJson(self::RECOGIDA_Y_ENTREGA, [
            'lavanderia' => ($lavanderia ?? $this->lavanderia)->id_publico,
            ...$direcciones,
        ]);
    }

    /**
     * La respuesta rechaza las direcciones con su motivo y su mensaje, y no trae ninguna dirección.
     *
     * @param  array<string, array{motivo: string, mensaje: string}>  $rechazos
     */
    private function assertRechazo(TestResponse $respuesta, array $rechazos): void
    {
        $respuesta->assertUnprocessable()->assertExactJson([
            'message' => reset($rechazos)['mensaje'],
            'errors' => array_map(fn (array $rechazo): array => [$rechazo['mensaje']], $rechazos),
            'motivos' => array_map(fn (array $rechazo): string => $rechazo['motivo'], $rechazos),
        ]);
    }
}
