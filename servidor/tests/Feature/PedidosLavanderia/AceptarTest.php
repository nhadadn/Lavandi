<?php

namespace Tests\Feature\PedidosLavanderia;

use App\Models\EstadoPedido;
use App\Models\Pedido;
use App\Models\Usuario;
use App\Pedidos\EstadoDelPedidoCambiado;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Estados\Soporte\EstadosDePrueba;
use Tests\Feature\PedidosLavanderia\Soporte\PedidosDeLaLavanderiaDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * La lavandería acepta un pedido confirmado que espera su respuesta: "Aceptar o rechazar con motivo"
 * (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo B #2), con POST /api/lavanderia/pedidos/{id_publico}/aceptar.
 *
 * Aceptar pasa el pedido al estado aceptado y agrega su evento, con la hora y la cuenta de la lavandería, en la misma
 * transacción (ADR-0016, segunda excepción; design.md, "Tablas y migración"). El usuario lo sigue viendo en Confirmado
 * (adenda propuesta a ADR-0021 en el design.md de pedidos-de-la-lavanderia). El pedido se confirma como lo confirmará
 * el cobro (C-188).
 *
 * Escenarios: pedidos-lavanderia-E07 y pedidos-lavanderia-E08 · Spec:
 * openspec/changes/pedidos-de-la-lavanderia/specs/pedidos-lavanderia/spec.md · Tarea 1.2
 */
class AceptarTest extends TestCase
{
    use AgendaDePrueba;
    use EstadosDePrueba;
    use LavanderiasDePrueba;
    use PedidosDeLaLavanderiaDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    private Usuario $cuenta;

    private Pedido $pedido;

    private Usuario $lavanderiaDelPedido;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();

        // Se confirma a las 09:30, la hora de prepararPedido().
        $this->cuenta = $this->cuentaDeUsuario();
        $this->pedido = $this->pedidoConfirmadoDe($this->cuenta);
        $this->lavanderiaDelPedido = $this->cuentaDeLaLavanderia($this->pedido);
    }

    #[TestDox('pedidos-lavanderia-E07: el catálogo de estados tiene aceptado, que el usuario ve como Confirmado, en su mismo orden y fuera de los cinco del seguimiento')]
    public function test_pedidos_lavanderia_e07_el_catalogo_tiene_el_estado_aceptado(): void
    {
        $aceptado = EstadoPedido::query()->where('codigo', EstadoPedido::ACEPTADO)->sole();
        $confirmado = EstadoPedido::query()->where('codigo', EstadoPedido::CONFIRMADO)->sole();

        $this->assertSame('Confirmado', $aceptado->nombre_usuario);
        $this->assertSame($confirmado->orden, $aceptado->orden);
        $this->assertFalse($aceptado->visible_usuario);
    }

    #[TestDox('pedidos-lavanderia-E07: la lavandería acepta un pedido confirmado que espera su respuesta; el servidor lo guarda como aceptado, con la hora y la cuenta de la lavandería, y ya no ofrece Aceptar ni Rechazar')]
    public function test_pedidos_lavanderia_e07_la_lavanderia_acepta_un_pedido(): void
    {
        $this->elJuevesALas('09:45:00');

        $respuesta = $this->aceptarPedido($this->lavanderiaDelPedido, $this->pedido->id_publico)->assertOk();

        $respuesta->assertJsonPath('pedido.id_publico', $this->pedido->id_publico)
            ->assertJsonPath('pedido.folio', $this->pedido->folio)
            ->assertJsonPath('pedido.estado', ['codigo' => EstadoPedido::ACEPTADO, 'nombre' => 'Aceptado'])
            ->assertJsonPath('pedido.por_responder', false);

        $this->assertSame(EstadoPedido::ACEPTADO, $this->pedido->fresh()->estado->codigo);

        $evento = $this->pedido->eventos()->with('estado')->orderByDesc('id_pedido_eventos')->first();
        $this->assertSame(EstadoPedido::ACEPTADO, $evento->estado->codigo);
        $this->assertSame($this->lavanderiaDelPedido->getKey(), $evento->id_usuarios);
        $this->assertSame($this->pedido->id_plazas, $evento->id_plazas);
        $this->assertNull($evento->motivo);
        $this->assertTrue(
            $evento->ocurrido_en->eq(CarbonImmutable::parse(self::JUEVES_DE_PRUEBA.' 09:45:00', self::ZONA_HORARIA)),
            'La hora de la aceptación es la del evento.',
        );
    }

    #[TestDox('pedidos-lavanderia-E07: aceptar publica el cambio de estado (EstadoDelPedidoCambiado) con el pedido y su evento nuevo')]
    public function test_pedidos_lavanderia_e07_aceptar_publica_el_cambio_de_estado(): void
    {
        Event::fake([EstadoDelPedidoCambiado::class]);

        $this->aceptarPedido($this->lavanderiaDelPedido, $this->pedido->id_publico)->assertOk();

        Event::assertDispatchedTimes(EstadoDelPedidoCambiado::class, 1);
        Event::assertDispatched(
            EstadoDelPedidoCambiado::class,
            fn (EstadoDelPedidoCambiado $cambio): bool => $cambio->pedido->is($this->pedido)
                && $cambio->evento->exists
                && $cambio->evento->id_estados_pedido === $cambio->pedido->id_estados_pedido
                && $cambio->evento->estado->codigo === EstadoPedido::ACEPTADO,
        );
    }

    #[TestDox('pedidos-lavanderia-E07: el estado actual y su evento van en la misma transacción: si el evento no se guarda, el pedido sigue esperando su respuesta y no se publica nada')]
    public function test_pedidos_lavanderia_e07_si_el_evento_no_se_guarda_el_estado_tampoco(): void
    {
        Event::fake([EstadoDelPedidoCambiado::class]);
        $eventos = $this->eventosDe($this->pedido);
        // La base rechaza, solo en esta prueba, todo evento nuevo del historial.
        DB::unprepared(
            "create function rechazar_evento_de_prueba() returns trigger language plpgsql as \$\$
                begin raise exception 'Evento de prueba rechazado'; end \$\$;
             create trigger rechazar_evento_de_prueba before insert on pedido_eventos
                for each row execute function rechazar_evento_de_prueba();",
        );

        $this->aceptarPedido($this->lavanderiaDelPedido, $this->pedido->id_publico)->assertServerError();

        $this->assertPedidoSinCambio($this->pedido, EstadoPedido::CONFIRMADO, $eventos);
        Event::assertNotDispatched(EstadoDelPedidoCambiado::class);
    }

    #[TestDox('pedidos-lavanderia-E07: aceptado, el usuario sigue viendo su pedido en Confirmado, con la hora de la confirmación, y los cinco estados del seguimiento')]
    public function test_pedidos_lavanderia_e07_el_usuario_sigue_viendo_confirmado(): void
    {
        $this->elJuevesALas('09:45:00');
        $this->aceptarPedido($this->lavanderiaDelPedido, $this->pedido->id_publico)->assertOk();

        $respuesta = $this->consultarPedido($this->cuenta, $this->pedido->id_publico)->assertOk();

        $respuesta->assertJsonPath('pedido.estado', [
            'codigo' => EstadoPedido::CONFIRMADO,
            'nombre' => 'Confirmado',
            'visible_usuario' => true,
        ]);
        $this->assertSame(
            [
                'confirmado' => self::JUEVES_DE_PRUEBA.'T09:30:00-06:00',
                'recolectado' => null,
                'en_proceso' => null,
                'listo' => null,
                'entregado' => null,
            ],
            array_column($respuesta->json('pedido.seguimiento'), 'ocurrido_en', 'codigo'),
        );
    }

    #[TestDox('pedidos-lavanderia-E08: un pedido que la lavandería ya aceptó no se acepta otra vez: el servidor rechaza la acción y el pedido no cambia')]
    public function test_pedidos_lavanderia_e08_un_pedido_ya_aceptado_no_se_acepta_otra_vez(): void
    {
        $this->aceptarPedido($this->lavanderiaDelPedido, $this->pedido->id_publico)->assertOk();
        $eventos = $this->eventosDe($this->pedido);

        $this->aceptarPedido($this->lavanderiaDelPedido, $this->pedido->id_publico)
            ->assertConflict()
            ->assertJsonPath('message', 'El pedido ya fue respondido.')
            ->assertJsonPath('pedido.estado.codigo', EstadoPedido::ACEPTADO);

        $this->assertPedidoSinCambio($this->pedido, EstadoPedido::ACEPTADO, $eventos);
    }

    #[TestDox('pedidos-lavanderia-E07: una cuenta de usuario no acepta pedidos: la ruta es del Módulo B y responde 403 sin datos del pedido')]
    public function test_pedidos_lavanderia_e07_una_cuenta_de_usuario_no_acepta(): void
    {
        $eventos = $this->eventosDe($this->pedido);

        $this->aceptarPedido($this->cuenta, $this->pedido->id_publico)
            ->assertForbidden()
            ->assertJsonPath('message', 'Esta cuenta no tiene acceso.')
            ->assertJsonMissingPath('pedido');

        $this->assertPedidoSinCambio($this->pedido, EstadoPedido::CONFIRMADO, $eventos);
    }
}
