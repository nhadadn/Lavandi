<?php

namespace Tests\Feature\Confirmacion;

use App\Models\Direccion;
use App\Models\Lavanderia;
use App\Models\ParametroPlaza;
use App\Models\Pedido;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * Al aceptar el resumen, el servidor vuelve a validar la ventana y las direcciones con las mismas reglas con que se
 * ofrecieron, y si alguna dejó de valer lo rechaza con su motivo, sin crear el pedido (NSV-2026-001-v8.pdf:21:ANEXO A,
 * Sección A.4.3; ADR-0003).
 *
 * La ventana se revalida con App\Agenda\Agenda::validar() (agenda-E09) y las direcciones con
 * App\Direcciones\RecogidaYEntrega, la lógica de POST /api/direcciones/recogida-y-entrega (direcciones-E11 y E12). El
 * error de la ventana va en "ventana" y el de cada dirección en su campo, para que la app lleve a elegir solo eso de
 * nuevo, con los demás datos intactos.
 *
 * La plaza guardada en la dirección se resolvió al guardarla y puede quedar vieja si las zonas cambian. Al copiarla al
 * pedido se vuelve a resolver contra la zona de hoy.
 *
 * Escenario: confirmacion-E08 · Spec: openspec/changes/resumen-y-confirmacion/specs/confirmacion/spec.md · Tarea 1.4
 */
class RevalidarAlConfirmarTest extends TestCase
{
    use AgendaDePrueba;
    use LavanderiasDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();
    }

    #[TestDox('confirmacion-E08: el resumen abierto con la ventana 10:00–11:00 se rechaza, sin crear el pedido, si se acepta cuando esa ventana ya empezó')]
    public function test_confirmacion_e08_la_ventana_que_ya_empezo_se_rechaza_sin_crear_el_pedido(): void
    {
        $cuenta = $this->cuentaDeUsuario();
        $resumen = $this->resumen($this->direccionDe($cuenta), datos: ['ventana' => [
            'inicio' => '2026-12-03T10:00:00-06:00',
            'fin' => '2026-12-03T11:00:00-06:00',
        ]]);

        // A las 09:30, cuando se abrió el resumen, la agenda sí ofrecía 10:00–11:00.
        $this->assertContains('10:00–11:00', $this->horasDe($this->pedirVentanas($this->lavanderia, self::JUEVES_DE_PRUEBA, $cuenta)));

        $this->elJuevesALas('10:05:00');

        $this->aceptarResumen($cuenta, $resumen)
            ->assertUnprocessable()
            ->assertExactJson([
                'message' => 'La ventana ya empezó.',
                'errors' => ['ventana' => ['La ventana ya empezó.']],
            ]);

        $this->assertSame(0, Pedido::count());
        $this->assertSame(0, DB::table('pedido_eventos')->count());
    }

    #[TestDox('confirmacion-E08: una ventana que la agenda no ofrece se rechaza con el motivo de la agenda, sin crear el pedido')]
    public function test_confirmacion_e08_una_ventana_que_la_agenda_no_ofrece_se_rechaza_con_su_motivo(): void
    {
        $cuenta = $this->cuentaDeUsuario();
        $casa = $this->direccionDe($cuenta);

        $rechazos = [
            'La lavandería no ofrece esa ventana en su horario.' => ['2026-12-03T14:00:00-06:00', '2026-12-03T15:00:00-06:00'],
            'La ventana debe durar exactamente una hora.' => ['2026-12-03T11:00:00-06:00', '2026-12-03T13:00:00-06:00'],
            'La ventana está después de los días que se pueden agendar.' => ['2026-12-10T11:00:00-06:00', '2026-12-10T12:00:00-06:00'],
            'La ventana va con su inicio y su fin en ISO 8601, con su desfase, como los da la agenda.' => ['mañana', 'pasado'],
        ];

        foreach ($rechazos as $motivo => [$inicio, $fin]) {
            $this->aceptarResumen($cuenta, $this->resumen($casa, datos: ['ventana' => ['inicio' => $inicio, 'fin' => $fin]]))
                ->assertUnprocessable()
                ->assertJsonPath('errors.ventana', [$motivo]);
        }

        $this->assertSame(0, Pedido::count());
    }

    #[TestDox('confirmacion-E08: si a la plaza le falta su agenda, el resumen no se acepta y no se crea el pedido')]
    public function test_confirmacion_e08_sin_la_agenda_de_la_plaza_no_se_crea_el_pedido(): void
    {
        $cuenta = $this->cuentaDeUsuario();
        ParametroPlaza::query()->where('id_plazas', $this->plaza->getKey())->delete();

        $this->aceptarResumen($cuenta, $this->resumen($this->direccionDe($cuenta)))
            ->assertStatus(503)
            ->assertExactJson(['message' => 'La agenda de la plaza no está configurada.']);

        $this->assertSame(0, Pedido::count());
    }

    #[TestDox('confirmacion-E08: si la zona de la recogida dejó de dar servicio, el resumen se rechaza con su motivo, sin crear el pedido')]
    public function test_confirmacion_e08_si_la_zona_de_la_recogida_dejo_de_dar_servicio_se_rechaza(): void
    {
        $cuenta = $this->cuentaDeUsuario();
        $casa = $this->direccionDe($cuenta);
        $resumen = $this->resumen($casa);

        // Cuando se eligió, servía.
        $this->validarRecogidaYEntrega($cuenta, $casa)->assertOk();

        $this->zona->update(['activa' => false]);

        $this->aceptarResumen($cuenta, $resumen)
            ->assertUnprocessable()
            ->assertExactJson([
                'message' => 'La dirección está fuera de la zona de servicio.',
                'errors' => ['recogida' => ['La dirección está fuera de la zona de servicio.']],
                'motivos' => ['recogida' => 'fuera_de_la_zona_de_servicio'],
            ]);

        $this->assertSame(0, Pedido::count());
    }

    #[TestDox('confirmacion-E08: una entrega que cae en otra plaza se rechaza con su motivo, igual que en recogida-y-entrega, sin crear el pedido')]
    public function test_confirmacion_e08_una_entrega_en_otra_plaza_se_rechaza_con_su_motivo(): void
    {
        $cuenta = $this->cuentaDeUsuario();
        $otraPlaza = $this->plazaDePrueba('Plaza de prueba 2');
        $this->zonaDePrueba($otraPlaza, 'Norte', $this->cuadrado(-120.0, 20.0));
        $casa = $this->direccionDe($cuenta);
        $fuera = $this->direccionDe($cuenta, [
            'id_plazas' => $otraPlaza->getKey(),
            'ubicacion' => ['latitud' => 20.5, 'longitud' => -119.5],
        ]);

        $esperado = [
            'message' => 'Esta lavandería no da servicio en esa dirección.',
            'errors' => ['entrega' => ['Esta lavandería no da servicio en esa dirección.']],
            'motivos' => ['entrega' => 'fuera_de_la_plaza_de_la_lavanderia'],
        ];

        // La misma respuesta que da la validación de las direcciones.
        $this->validarRecogidaYEntrega($cuenta, $casa, $fuera)->assertUnprocessable()->assertExactJson($esperado);
        $this->aceptarResumen($cuenta, $this->resumen($casa, $fuera))->assertUnprocessable()->assertExactJson($esperado);

        $this->assertSame(0, Pedido::count());
    }

    #[TestDox('confirmacion-E08: una dirección guardada fuera de toda zona, que una zona nueva ya cubre, se acepta y toma la plaza de hoy')]
    public function test_confirmacion_e08_la_plaza_vieja_de_la_direccion_se_vuelve_a_resolver(): void
    {
        $cuenta = $this->cuentaDeUsuario();
        // Al guardarla no caía en ninguna zona: quedó sin plaza.
        $casa = $this->direccionDe($cuenta, ['id_plazas' => null, 'ubicacion' => ['latitud' => 12.5, 'longitud' => -119.5]]);

        // Después se carga una zona de la plaza que la cubre.
        $this->zonaDePrueba($this->plaza, 'Ampliación', $this->cuadrado(-120.0, 12.0));
        $this->validarRecogidaYEntrega($cuenta, $casa)->assertOk();

        $this->aceptarResumen($cuenta, $this->resumen($casa))->assertCreated();

        $this->assertSame($this->plaza->getKey(), $casa->fresh()->id_plazas);
        $this->assertSame(
            [$this->plaza->getKey(), $this->plaza->getKey()],
            DB::table('pedido_direcciones')->pluck('id_plazas')->all(),
        );
    }

    #[TestDox('confirmacion-E15: una dirección que ya se usó en un pedido de su plaza anterior no se puede llevar a la plaza de hoy: se rechaza para que se guarde de nuevo')]
    public function test_confirmacion_e15_una_direccion_usada_en_su_plaza_anterior_se_rechaza(): void
    {
        $cuenta = $this->cuentaDeUsuario();
        $casa = $this->direccionDe($cuenta);
        $this->aceptarResumen($cuenta, $this->resumen($casa))->assertCreated();

        // Las zonas cambian: la de esta plaza deja de dar servicio y el punto queda en una zona de otra plaza, con su
        // propia lavandería.
        $this->zona->update(['activa' => false]);
        $otraPlaza = $this->plazaConAgenda('Plaza de prueba 2', self::ZONA_HORARIA, diasHaciaAdelante: 3);
        $this->zonaDePrueba($otraPlaza, 'Centro', $this->cuadrado(-120.0, 10.0));
        $otraLavanderia = $this->lavanderiaConHorario(
            $otraPlaza,
            array_map(fn (int $dia): array => [$dia, '09:00', '13:00'], range(self::LUNES, self::DOMINGO)),
        );
        $this->servicioDePrueba($otraLavanderia, 'estandar', self::PRECIO_POR_KILO);
        $this->validarRecogidaYEntrega($cuenta, $casa, lavanderia: $otraLavanderia)->assertOk();

        $this->aceptarResumen($cuenta, $this->resumen($casa, datos: ['lavanderia' => $otraLavanderia->id_publico]))
            ->assertUnprocessable()
            ->assertExactJson([
                'message' => 'Esta dirección cambió de zona de servicio. Guárdala de nuevo para usarla.',
                'errors' => ['recogida' => ['Esta dirección cambió de zona de servicio. Guárdala de nuevo para usarla.']],
                'motivos' => ['recogida' => 'plaza_de_la_direccion_desactualizada'],
            ]);

        // El pedido anterior y su copia siguen como estaban; no nació otro.
        $this->assertSame(1, Pedido::count());
        $this->assertSame($this->plaza->getKey(), $casa->fresh()->id_plazas);
    }

    /**
     * La validación de las direcciones que hace la app antes del resumen (POST /api/direcciones/recogida-y-entrega).
     */
    private function validarRecogidaYEntrega(
        Usuario $cuenta,
        Direccion $recogida,
        ?Direccion $entrega = null,
        ?Lavanderia $lavanderia = null,
    ): TestResponse {
        $this->app['auth']->forgetGuards();

        return $this->withToken($cuenta->createToken('app')->plainTextToken)->postJson('/api/direcciones/recogida-y-entrega', [
            'lavanderia' => ($lavanderia ?? $this->lavanderia)->id_publico,
            'recogida' => $recogida->id_publico,
            'entrega' => $entrega?->id_publico,
        ]);
    }
}
