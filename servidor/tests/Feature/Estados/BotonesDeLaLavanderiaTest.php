<?php

namespace Tests\Feature\Estados;

use App\Models\EstadoPedido;
use App\Models\Pedido;
use App\Models\PedidoEvento;
use App\Models\Usuario;
use App\Pedidos\EstadoDelPedidoCambiado;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Estados\Soporte\EstadosDePrueba;
use Tests\Feature\PedidosLavanderia\Soporte\PedidosDeLaLavanderiaDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * Los botones de la lavandería sobre un pedido que aceptó: "Recolectado → En proceso → Listo → En proceso de entrega"
 * (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo B #3), con POST /api/lavanderia/pedidos/{id_publico}/estado.
 *
 * Solo se puede marcar el paso siguiente: la api rechaza un salto o un regreso, y el pedido no cambia (ADR-0021,
 * decisión 4). Cada botón agrega su evento a pedido_eventos, con su hora y la cuenta de la lavandería, y cambia
 * pedidos.id_estados_pedido en la misma transacción (ADR-0016, segunda excepción); ya guardado, publica
 * EstadoDelPedidoCambiado. "En proceso de entrega" es el estado en_entrega, que el usuario ve como Listo (ADR-0021).
 *
 * Escenarios: estados-E03, estados-E04, estados-E05 y estados-E14 · Spec:
 * openspec/changes/estados-del-pedido/specs/estados/spec.md · Tarea 2.1
 */
class BotonesDeLaLavanderiaTest extends TestCase
{
    use AgendaDePrueba;
    use EstadosDePrueba;
    use LavanderiasDePrueba;
    use PedidosDeLaLavanderiaDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    /** Los cuatro botones de la lavandería, en el orden del contrato (Módulo B #3): el estado al que llevan y su nombre. */
    private const BOTONES = [
        EstadoPedido::RECOLECTADO => 'Recolectado',
        EstadoPedido::EN_PROCESO => 'En proceso',
        EstadoPedido::LISTO => 'Listo',
        EstadoPedido::EN_ENTREGA => 'En proceso de entrega',
    ];

    private Usuario $cuenta;

    private Pedido $pedido;

    private Usuario $lavanderiaDelPedido;

    /** La respuesta de aceptar el pedido, en setUp(). */
    private TestResponse $aceptacion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();

        // Se confirma a las 09:30 y la lavandería lo acepta a las 09:40.
        $this->cuenta = $this->cuentaDeUsuario();
        $this->pedido = $this->pedidoConfirmadoDe($this->cuenta);
        $this->lavanderiaDelPedido = $this->cuentaDeLaLavanderia($this->pedido);
        $this->elJuevesALas('09:40:00');
        $this->aceptacion = $this->aceptarPedido($this->lavanderiaDelPedido, $this->pedido->id_publico)->assertOk();
    }

    #[TestDox('estados-E04: el catálogo de estados tiene en_entrega, el de "En proceso de entrega", que el usuario ve como Listo, en su mismo orden y fuera de los cinco del seguimiento')]
    public function test_estados_e04_el_catalogo_tiene_el_estado_en_entrega(): void
    {
        $enEntrega = EstadoPedido::query()->where('codigo', EstadoPedido::EN_ENTREGA)->with('paso')->sole();

        $this->assertSame('Listo', $enEntrega->nombre_usuario);
        $this->assertFalse($enEntrega->visible_usuario);
        $this->assertSame(EstadoPedido::LISTO, $enEntrega->paso?->codigo);
    }

    #[TestDox('estados-E03: el pedido aceptado ofrece Recolectado como paso siguiente; la lavandería lo marca y la api lo guarda en Recolectado')]
    public function test_estados_e03_la_lavanderia_marca_recolectado(): void
    {
        $this->aceptacion->assertJsonPath(
            'pedido.siguiente',
            ['estado' => EstadoPedido::RECOLECTADO, 'boton' => 'Recolectado'],
        );

        $this->marcarPedido($this->lavanderiaDelPedido, $this->pedido->id_publico, EstadoPedido::RECOLECTADO)
            ->assertOk()
            ->assertJsonPath('pedido.estado', ['codigo' => EstadoPedido::RECOLECTADO, 'nombre' => 'Recolectado'])
            ->assertJsonPath('pedido.siguiente', ['estado' => EstadoPedido::EN_PROCESO, 'boton' => 'En proceso']);

        $this->assertSame(EstadoPedido::RECOLECTADO, $this->pedido->fresh()->estado->codigo);
    }

    #[TestDox('estados-E04: desde Recolectado, la lavandería marca uno tras otro En proceso, Listo y En proceso de entrega; la api guarda cada cambio en ese orden y el pedido queda en En proceso de entrega, sin paso siguiente')]
    public function test_estados_e04_la_lavanderia_avanza_en_el_orden_del_contrato(): void
    {
        $this->marcarPedido($this->lavanderiaDelPedido, $this->pedido->id_publico, EstadoPedido::RECOLECTADO)->assertOk();
        $botones = array_slice(self::BOTONES, 1, null, true);
        $siguientes = [EstadoPedido::LISTO, EstadoPedido::EN_ENTREGA, null];

        foreach (array_keys($botones) as $i => $estado) {
            $respuesta = $this->marcarPedido($this->lavanderiaDelPedido, $this->pedido->id_publico, $estado)->assertOk();

            $respuesta->assertJsonPath('pedido.estado', ['codigo' => $estado, 'nombre' => $botones[$estado]]);
            $siguiente = $siguientes[$i];
            $respuesta->assertJsonPath(
                'pedido.siguiente',
                $siguiente === null ? null : ['estado' => $siguiente, 'boton' => self::BOTONES[$siguiente]],
            );
            $this->assertSame($estado, $this->pedido->fresh()->estado->codigo);
        }

        $this->assertSame(
            [EstadoPedido::ACEPTADO, EstadoPedido::RECOLECTADO, EstadoPedido::EN_PROCESO, EstadoPedido::LISTO, EstadoPedido::EN_ENTREGA],
            array_slice($this->codigosDelHistorial(), 2),
        );
    }

    #[TestDox('estados-E04: en En proceso de entrega, el usuario sigue viendo uno de los cinco: Listo, con la hora en que llegó a Listo (ADR-0021)')]
    public function test_estados_e04_en_proceso_de_entrega_el_usuario_ve_listo(): void
    {
        $this->llevarPorLaApi(EstadoPedido::RECOLECTADO, EstadoPedido::EN_PROCESO);
        $this->elJuevesALas('12:00:00');
        $this->llevarPorLaApi(EstadoPedido::LISTO);
        $this->elJuevesALas('12:30:00');
        $this->llevarPorLaApi(EstadoPedido::EN_ENTREGA);

        $respuesta = $this->consultarPedido($this->cuenta, $this->pedido->id_publico)->assertOk();

        $respuesta->assertJsonPath('pedido.estado', [
            'codigo' => EstadoPedido::LISTO,
            'nombre' => 'Listo',
            'visible_usuario' => true,
        ]);
        $this->assertSame(
            ['confirmado', 'recolectado', 'en_proceso', 'listo', 'entregado'],
            array_column($respuesta->json('pedido.seguimiento'), 'codigo'),
        );
        $respuesta->assertJsonPath('pedido.seguimiento.3.ocurrido_en', self::JUEVES_DE_PRUEBA.'T12:00:00-06:00');
        $respuesta->assertJsonPath('pedido.seguimiento.4.ocurrido_en', null);
    }

    #[TestDox('estados-E05: en Recolectado, marcar Listo sin pasar por En proceso, saltar a En proceso de entrega o repetir Recolectado se rechaza, y el pedido sigue en Recolectado sin eventos nuevos')]
    public function test_estados_e05_la_api_rechaza_un_salto(): void
    {
        $this->marcarPedido($this->lavanderiaDelPedido, $this->pedido->id_publico, EstadoPedido::RECOLECTADO)->assertOk();
        $eventos = $this->eventosDe($this->pedido);

        foreach ([EstadoPedido::LISTO, EstadoPedido::EN_ENTREGA, EstadoPedido::RECOLECTADO] as $estado) {
            $this->marcarPedido($this->lavanderiaDelPedido, $this->pedido->id_publico, $estado)
                ->assertConflict()
                ->assertJsonPath('message', 'Ese no es el paso siguiente del pedido.')
                ->assertJsonPath('pedido.estado.codigo', EstadoPedido::RECOLECTADO);
        }

        $this->assertPedidoSinCambio($this->pedido, EstadoPedido::RECOLECTADO, $eventos);
    }

    #[TestDox('estados-E05: regresar el pedido a Confirmado, o a un botón anterior, se rechaza y el pedido no cambia')]
    public function test_estados_e05_la_api_rechaza_un_regreso(): void
    {
        $this->llevarPorLaApi(EstadoPedido::RECOLECTADO, EstadoPedido::EN_PROCESO);
        $eventos = $this->eventosDe($this->pedido);

        // Confirmado no es un botón de la lavandería: la petición no es válida.
        $this->marcarPedido($this->lavanderiaDelPedido, $this->pedido->id_publico, EstadoPedido::CONFIRMADO)
            ->assertUnprocessable()
            ->assertJsonPath('errors.estado.0', 'Ese no es un botón de la lavandería.')
            ->assertJsonMissingPath('pedido');
        // Recolectado sí lo es, pero ya pasó.
        $this->marcarPedido($this->lavanderiaDelPedido, $this->pedido->id_publico, EstadoPedido::RECOLECTADO)
            ->assertConflict()
            ->assertJsonPath('message', 'Ese no es el paso siguiente del pedido.');

        $this->assertPedidoSinCambio($this->pedido, EstadoPedido::EN_PROCESO, $eventos);
    }

    #[TestDox('estados-E05: solo los cuatro botones de la lavandería se marcan: sin estado, con uno que no es texto, con Entregado, aceptado, rechazado o uno que no existe, la api responde 422 y el pedido no cambia')]
    public function test_estados_e05_solo_los_cuatro_botones_se_marcan(): void
    {
        $eventos = $this->eventosDe($this->pedido);

        foreach ([null, ['recolectado'], EstadoPedido::ENTREGADO, EstadoPedido::ACEPTADO, EstadoPedido::RECHAZADO, 'volando'] as $estado) {
            $this->marcarPedido($this->lavanderiaDelPedido, $this->pedido->id_publico, $estado)
                ->assertUnprocessable()
                ->assertJsonPath('errors.estado.0', 'Ese no es un botón de la lavandería.');
        }

        $this->assertPedidoSinCambio($this->pedido, EstadoPedido::ACEPTADO, $eventos);
    }

    #[TestDox('estados-E14: cada botón deja un evento nuevo, después de los anteriores del pedido, con su estado, su hora y la cuenta de la lavandería; su estado anterior es el del evento previo')]
    public function test_estados_e14_cada_boton_queda_en_el_historial(): void
    {
        $horas = [EstadoPedido::RECOLECTADO => '10:05:00', EstadoPedido::EN_PROCESO => '10:40:00'];

        foreach ($horas as $estado => $hora) {
            $this->elJuevesALas($hora);
            $this->marcarPedido($this->lavanderiaDelPedido, $this->pedido->id_publico, $estado)->assertOk();
        }

        $historial = $this->pedido->eventos()->with('estado')->orderBy('ocurrido_en')->orderBy('id_pedido_eventos')->get();
        $this->assertSame(
            [EstadoPedido::POR_PAGAR, EstadoPedido::CONFIRMADO, EstadoPedido::ACEPTADO, EstadoPedido::RECOLECTADO, EstadoPedido::EN_PROCESO],
            $historial->map(fn (PedidoEvento $evento): string => $evento->estado->codigo)->all(),
        );

        [, , $aceptado, $recolectado, $enProceso] = $historial->all();
        foreach ([[$recolectado, $aceptado], [$enProceso, $recolectado]] as [$evento, $previo]) {
            $hora = $horas[$evento->estado->codigo];
            $this->assertSame($this->lavanderiaDelPedido->getKey(), $evento->id_usuarios);
            $this->assertSame($this->pedido->id_plazas, $evento->id_plazas);
            $this->assertNull($evento->motivo);
            $this->assertTrue(
                $evento->ocurrido_en->eq(CarbonImmutable::parse(self::JUEVES_DE_PRUEBA.' '.$hora, self::ZONA_HORARIA)),
                "El evento {$evento->estado->codigo} debió ocurrir a las {$hora}.",
            );
            $this->assertTrue($previo->ocurrido_en->lte($evento->ocurrido_en));
            $this->assertLessThan($evento->id_pedido_eventos, $previo->id_pedido_eventos);
        }

        // El estado actual del pedido es el del último evento, guardado junto con él.
        $this->assertSame($enProceso->id_estados_pedido, $this->pedido->fresh()->id_estados_pedido);
    }

    #[TestDox('estados-E14: cada botón publica el cambio de estado (EstadoDelPedidoCambiado) con el pedido y su evento nuevo, uno por botón')]
    public function test_estados_e14_cada_boton_publica_el_cambio_de_estado(): void
    {
        Event::fake([EstadoDelPedidoCambiado::class]);

        $this->llevarPorLaApi(...array_keys(self::BOTONES));

        Event::assertDispatchedTimes(EstadoDelPedidoCambiado::class, count(self::BOTONES));
        foreach (array_keys(self::BOTONES) as $estado) {
            Event::assertDispatched(
                EstadoDelPedidoCambiado::class,
                fn (EstadoDelPedidoCambiado $cambio): bool => $cambio->pedido->is($this->pedido)
                    && $cambio->evento->exists
                    && $cambio->evento->estado->codigo === $estado,
            );
        }
    }

    #[TestDox('estados-E03: una cuenta de usuario no marca los botones de la lavandería: responde 403 y el pedido no cambia')]
    public function test_estados_e03_una_cuenta_de_usuario_no_marca_los_botones(): void
    {
        $eventos = $this->eventosDe($this->pedido);

        $this->marcarPedido($this->cuenta, $this->pedido->id_publico, EstadoPedido::RECOLECTADO)
            ->assertForbidden()
            ->assertJsonMissingPath('pedido');

        $this->assertPedidoSinCambio($this->pedido, EstadoPedido::ACEPTADO, $eventos);
    }

    /**
     * La lavandería marca cada botón por la API, en ese orden.
     */
    private function llevarPorLaApi(string ...$estados): void
    {
        foreach ($estados as $estado) {
            $this->marcarPedido($this->lavanderiaDelPedido, $this->pedido->id_publico, $estado)->assertOk();
        }
    }

    /**
     * Los códigos del historial del pedido, en orden.
     *
     * @return list<string>
     */
    private function codigosDelHistorial(): array
    {
        return $this->pedido->eventos()->with('estado')->orderBy('id_pedido_eventos')->get()
            ->map(fn (PedidoEvento $evento): string => $evento->estado->codigo)
            ->all();
    }
}
