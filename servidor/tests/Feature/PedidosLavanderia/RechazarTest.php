<?php

namespace Tests\Feature\PedidosLavanderia;

use App\Models\EstadoPedido;
use App\Models\Pedido;
use App\Models\Usuario;
use App\Pedidos\EstadoDelPedidoCambiado;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
 * La lavandería rechaza con motivo un pedido confirmado que espera su respuesta: "Aceptar o rechazar con motivo"
 * (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo B #2), con POST /api/lavanderia/pedidos/{id_publico}/rechazar.
 *
 * Rechazar pasa el pedido a rechazado y agrega su evento, con el motivo, la hora y la cuenta de la lavandería, en la
 * misma transacción (design.md, "Tablas y migración"). Sin motivo no hay rechazo. Lo que pasa después con el pedido, el
 * cobro y lo que ve el usuario espera DD-016, DD-012 y DD-103 (pedidos-lavanderia-E14, tarea 3.4).
 *
 * Escenarios: pedidos-lavanderia-E12, pedidos-lavanderia-E13 y, del pedido ya rechazado, pedidos-lavanderia-E08 · Spec:
 * openspec/changes/pedidos-de-la-lavanderia/specs/pedidos-lavanderia/spec.md · Tarea 1.3
 */
class RechazarTest extends TestCase
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

        $this->cuenta = $this->cuentaDeUsuario();
        $this->pedido = $this->pedidoConfirmadoDe($this->cuenta);
        $this->lavanderiaDelPedido = $this->cuentaDeLaLavanderia($this->pedido);
    }

    #[TestDox('pedidos-lavanderia-E12: el catálogo de estados tiene rechazado, fuera de los cinco del seguimiento y sin paso que lo nombre al usuario')]
    public function test_pedidos_lavanderia_e12_el_catalogo_tiene_el_estado_rechazado(): void
    {
        $rechazado = EstadoPedido::query()->where('codigo', EstadoPedido::RECHAZADO)->with('paso')->sole();

        $this->assertSame('Rechazado', $rechazado->nombre_usuario);
        $this->assertFalse($rechazado->visible_usuario);
        // Ningún paso del seguimiento comparte su orden: el pedido rechazado queda fuera de los cinco (ADR-0021).
        $this->assertNull($rechazado->paso);
    }

    #[TestDox('pedidos-lavanderia-E12: la lavandería rechaza con motivo; el servidor guarda el pedido como rechazado, con el motivo, la hora y la cuenta de la lavandería')]
    public function test_pedidos_lavanderia_e12_la_lavanderia_rechaza_con_motivo(): void
    {
        $this->elJuevesALas('09:50:00');

        $respuesta = $this->rechazarPedido(
            $this->lavanderiaDelPedido,
            $this->pedido->id_publico,
            '  No tenemos lugar para más ropa hoy.  ',
        )->assertOk();

        $respuesta->assertJsonPath('pedido.id_publico', $this->pedido->id_publico)
            ->assertJsonPath('pedido.estado', ['codigo' => EstadoPedido::RECHAZADO, 'nombre' => 'Rechazado'])
            ->assertJsonPath('pedido.por_responder', false);

        $this->assertSame(EstadoPedido::RECHAZADO, $this->pedido->fresh()->estado->codigo);

        $evento = $this->pedido->eventos()->with('estado')->orderByDesc('id_pedido_eventos')->first();
        $this->assertSame(EstadoPedido::RECHAZADO, $evento->estado->codigo);
        // El motivo se guarda sin los espacios de las orillas.
        $this->assertSame('No tenemos lugar para más ropa hoy.', $evento->motivo);
        $this->assertSame($this->lavanderiaDelPedido->getKey(), $evento->id_usuarios);
        $this->assertSame($this->pedido->id_plazas, $evento->id_plazas);
        $this->assertTrue(
            $evento->ocurrido_en->eq(CarbonImmutable::parse(self::JUEVES_DE_PRUEBA.' 09:50:00', self::ZONA_HORARIA)),
            'La hora del rechazo es la del evento.',
        );
    }

    #[TestDox('pedidos-lavanderia-E12: rechazar publica el cambio de estado (EstadoDelPedidoCambiado) con el pedido y su evento nuevo')]
    public function test_pedidos_lavanderia_e12_rechazar_publica_el_cambio_de_estado(): void
    {
        Event::fake([EstadoDelPedidoCambiado::class]);

        $this->rechazarPedido($this->lavanderiaDelPedido, $this->pedido->id_publico, 'Sin lugar.')->assertOk();

        Event::assertDispatchedTimes(EstadoDelPedidoCambiado::class, 1);
        Event::assertDispatched(
            EstadoDelPedidoCambiado::class,
            fn (EstadoDelPedidoCambiado $cambio): bool => $cambio->pedido->is($this->pedido)
                && $cambio->evento->estado->codigo === EstadoPedido::RECHAZADO
                && $cambio->evento->motivo === 'Sin lugar.',
        );
    }

    #[TestDox('pedidos-lavanderia-E13: sin motivo, con el motivo vacío, de puros espacios o que no es texto, el servidor rechaza la petición y el pedido no cambia')]
    public function test_pedidos_lavanderia_e13_sin_motivo_no_hay_rechazo(): void
    {
        $eventos = $this->eventosDe($this->pedido);

        foreach ([null, '', '   ', ['no', 'es', 'texto']] as $motivo) {
            $this->rechazarPedido($this->lavanderiaDelPedido, $this->pedido->id_publico, $motivo)
                ->assertUnprocessable()
                ->assertJsonPath('errors.motivo.0', 'Escribe el motivo del rechazo.')
                ->assertJsonMissingPath('pedido');
        }

        $this->assertPedidoSinCambio($this->pedido, EstadoPedido::CONFIRMADO, $eventos);
    }

    #[TestDox('pedidos-lavanderia-E13: un motivo más largo que el máximo de la configuración se rechaza, y uno del largo máximo se acepta')]
    public function test_pedidos_lavanderia_e13_el_motivo_tiene_un_largo_maximo_configurable(): void
    {
        config(['pedidos.motivo_de_rechazo_maximo' => 20]);
        $eventos = $this->eventosDe($this->pedido);

        $this->rechazarPedido($this->lavanderiaDelPedido, $this->pedido->id_publico, str_repeat('ñ', 21))
            ->assertUnprocessable()
            ->assertJsonPath('errors.motivo.0', 'El motivo no puede pasar de 20 caracteres.');
        $this->assertPedidoSinCambio($this->pedido, EstadoPedido::CONFIRMADO, $eventos);

        $this->rechazarPedido($this->lavanderiaDelPedido, $this->pedido->id_publico, str_repeat('ñ', 20))->assertOk();
        $this->assertSame(EstadoPedido::RECHAZADO, $this->pedido->fresh()->estado->codigo);
    }

    #[TestDox('pedidos-lavanderia-E08: un pedido que la lavandería ya rechazó no se acepta ni se rechaza otra vez, y no cambia')]
    public function test_pedidos_lavanderia_e08_un_pedido_ya_rechazado_no_se_responde_otra_vez(): void
    {
        $this->rechazarPedido($this->lavanderiaDelPedido, $this->pedido->id_publico, 'Sin lugar.')->assertOk();
        $eventos = $this->eventosDe($this->pedido);

        $this->aceptarPedido($this->lavanderiaDelPedido, $this->pedido->id_publico)
            ->assertConflict()
            ->assertJsonPath('message', 'El pedido ya fue respondido.')
            ->assertJsonPath('pedido.estado.codigo', EstadoPedido::RECHAZADO);
        $this->rechazarPedido($this->lavanderiaDelPedido, $this->pedido->id_publico, 'Otra vez.')
            ->assertConflict()
            ->assertJsonPath('message', 'El pedido ya fue respondido.');

        $this->assertPedidoSinCambio($this->pedido, EstadoPedido::RECHAZADO, $eventos);
    }

    #[TestDox('pedidos-lavanderia-E08: un pedido que la lavandería ya aceptó no se rechaza, y no cambia')]
    public function test_pedidos_lavanderia_e08_un_pedido_ya_aceptado_no_se_rechaza(): void
    {
        $this->aceptarPedido($this->lavanderiaDelPedido, $this->pedido->id_publico)->assertOk();
        $eventos = $this->eventosDe($this->pedido);

        $this->rechazarPedido($this->lavanderiaDelPedido, $this->pedido->id_publico, 'Me arrepentí.')
            ->assertConflict()
            ->assertJsonPath('message', 'El pedido ya fue respondido.')
            ->assertJsonPath('pedido.estado.codigo', EstadoPedido::ACEPTADO);

        $this->assertPedidoSinCambio($this->pedido, EstadoPedido::ACEPTADO, $eventos);
    }

    #[TestDox('pedidos-lavanderia-E12: una cuenta de usuario no rechaza pedidos: la ruta es del Módulo B y responde 403 sin datos del pedido')]
    public function test_pedidos_lavanderia_e12_una_cuenta_de_usuario_no_rechaza(): void
    {
        $eventos = $this->eventosDe($this->pedido);

        $this->rechazarPedido($this->cuenta, $this->pedido->id_publico, 'Sin lugar.')
            ->assertForbidden()
            ->assertJsonMissingPath('pedido');

        $this->assertPedidoSinCambio($this->pedido, EstadoPedido::CONFIRMADO, $eventos);
    }
}
