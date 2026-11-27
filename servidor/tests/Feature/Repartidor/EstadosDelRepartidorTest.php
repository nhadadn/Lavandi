<?php

namespace Tests\Feature\Repartidor;

use App\Models\EstadoPedido;
use App\Models\Pedido;
use App\Models\PedidoEvento;
use App\Models\Repartidor;
use App\Pedidos\EstadoDelPedidoCambiado;
use App\Repartidores\RespuestaDelRepartidor;
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
use Tests\Feature\Repartidor\Soporte\RepartidorDePrueba;
use Tests\TestCase;

/**
 * Los botones del repartidor, POST /api/repartidor/pedidos/{id_publico}/estado: "Recolectado → En tránsito → Entregado",
 * en ese orden, sin saltos, sin retrocesos y solo en el pedido que él aceptó (NSV-2026-001-v8.pdf:18:ANEXO A, Sección
 * A.3, Módulo C #3; :20, Separación por tipo de usuario).
 *
 * Mueve el estado operativo de ADR-0021 con la misma operación que los botones de la lavandería
 * (App\Pedidos\CambioDeEstado), con la tabla del repartidor de la adenda del 2026-11-24 de ADR-0021:
 *
 * - Recolectado, desde aceptado: la lavandería ya aceptó el pedido (Módulo B #2);
 * - En tránsito, desde listo: es el mismo paso operativo que "En proceso de entrega" de la lavandería, en_entrega;
 * - Entregado, desde en_entrega.
 *
 * Entre Recolectado y En tránsito, el pedido pasa por los botones de la lavandería, En proceso y Listo (Módulo B #3).
 * Cómo ve el usuario cada estado del repartidor espera DD-022 y DD-140 (repartidor-E22).
 *
 * Escenarios: repartidor-E19, repartidor-E20 y repartidor-E21; y los botones que se habilitan al aceptar (repartidor-E15)
 * · Spec: openspec/changes/repartidor/specs/repartidor/spec.md · Tarea 1.5
 */
class EstadosDelRepartidorTest extends TestCase
{
    use AgendaDePrueba;
    use EstadosDePrueba;
    use LavanderiasDePrueba;
    use PedidosDeLaLavanderiaDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;
    use RepartidorDePrueba;

    private Repartidor $repartidor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();
        $this->repartidor = $this->repartidorActivo();
    }

    #[TestDox('repartidor-E15 y E19: al aceptar el pedido que la lavandería aceptó, se le habilita el botón Recolectado; antes de aceptar no tiene ninguno')]
    public function test_repartidor_e15_y_e19_al_aceptar_se_habilitan_los_botones(): void
    {
        $pedido = $this->pedidoAceptadoDe($this->cuentaDeUsuario());
        $this->ofrecerA($pedido, $this->repartidor);

        $this->pedidoComoRepartidor($this->repartidor, $pedido->id_publico)->assertOk()->assertJsonPath('pedido.siguiente', null);

        $this->comoRepartidor($this->repartidor)->postJson("/api/repartidor/pedidos/{$pedido->id_publico}/aceptar")
            ->assertOk()
            ->assertJsonPath('pedido.siguiente', ['estado' => EstadoPedido::RECOLECTADO, 'boton' => 'Recolectado']);
    }

    #[TestDox('repartidor-E19: el repartidor marca Recolectado, luego En tránsito y luego Entregado; el pedido pasa por los tres en ese orden, con los botones de la lavandería entre el primero y el segundo, y cada cambio queda con su hora y su cuenta')]
    public function test_repartidor_e19_avanza_el_pedido_en_orden(): void
    {
        $pedido = $this->pedidoQueAcepto();
        $cuenta = $this->repartidor->cuenta;
        // Desde aquí: la aceptación de la lavandería, antes, también publica su cambio.
        Event::fake([EstadoDelPedidoCambiado::class]);

        $this->alas('11:05');
        $this->marcar($pedido, EstadoPedido::RECOLECTADO)
            ->assertOk()
            ->assertJsonPath('pedido.estado', ['codigo' => EstadoPedido::RECOLECTADO, 'nombre' => 'Recolectado'])
            // Lo que sigue es de la lavandería: En proceso y Listo (Módulo B #3).
            ->assertJsonPath('pedido.siguiente', null);

        $this->alas('11:40');
        $this->marcarComoLaLavanderia($pedido, EstadoPedido::EN_PROCESO);
        $this->alas('15:00');
        $this->marcarComoLaLavanderia($pedido, EstadoPedido::LISTO);
        $this->pedidoComoRepartidor($this->repartidor, $pedido->id_publico)
            ->assertJsonPath('pedido.siguiente', ['estado' => EstadoPedido::EN_ENTREGA, 'boton' => 'En tránsito']);

        $this->alas('16:10');
        $this->marcar($pedido, EstadoPedido::EN_ENTREGA)
            ->assertOk()
            ->assertJsonPath('pedido.estado', ['codigo' => EstadoPedido::EN_ENTREGA, 'nombre' => 'En tránsito'])
            ->assertJsonPath('pedido.siguiente', ['estado' => EstadoPedido::ENTREGADO, 'boton' => 'Entregado']);

        $this->alas('16:45');
        $this->marcar($pedido, EstadoPedido::ENTREGADO)
            ->assertOk()
            ->assertJsonPath('pedido.estado', ['codigo' => EstadoPedido::ENTREGADO, 'nombre' => 'Entregado'])
            ->assertJsonPath('pedido.siguiente', null);

        $this->assertSame(EstadoPedido::ENTREGADO, $pedido->fresh()->estado->codigo);

        // El historial: los tres del repartidor, en orden, cada uno con su hora y su cuenta.
        $eventos = $pedido->eventos()->with('estado')->orderBy('id_pedido_eventos')->get()
            ->filter(fn (PedidoEvento $evento): bool => in_array($evento->estado->codigo, [
                EstadoPedido::RECOLECTADO, EstadoPedido::EN_PROCESO, EstadoPedido::LISTO, EstadoPedido::EN_ENTREGA, EstadoPedido::ENTREGADO,
            ], true))
            ->map(fn (PedidoEvento $evento): array => [
                $evento->estado->codigo,
                $evento->ocurrido_en->setTimezone(self::ZONA_HORARIA)->format('H:i'),
                $evento->id_usuarios === $cuenta->getKey(),
            ])->values()->all();

        $this->assertSame([
            [EstadoPedido::RECOLECTADO, '11:05', true],
            [EstadoPedido::EN_PROCESO, '11:40', false],
            [EstadoPedido::LISTO, '15:00', false],
            [EstadoPedido::EN_ENTREGA, '16:10', true],
            [EstadoPedido::ENTREGADO, '16:45', true],
        ], $eventos);

        // Cada botón publica el cambio, como los de la lavandería (avisos).
        Event::assertDispatched(EstadoDelPedidoCambiado::class, 5);

        // Ya entregado, sale de su lista de pedidos en curso.
        $this->listaDelRepartidor($this->repartidor)->assertOk()->assertExactJson(['pedidos' => []]);
    }

    #[TestDox('repartidor-E19: si la lavandería ya marcó En proceso de entrega, el mismo paso que En tránsito, el siguiente botón del repartidor es Entregado')]
    public function test_repartidor_e19_en_proceso_de_entrega_es_el_mismo_paso_que_en_transito(): void
    {
        $pedido = $this->pedidoQueAcepto();
        foreach ([EstadoPedido::RECOLECTADO, EstadoPedido::EN_PROCESO, EstadoPedido::LISTO, EstadoPedido::EN_ENTREGA] as $boton) {
            $this->marcarComoLaLavanderia($pedido, $boton);
        }

        $this->pedidoComoRepartidor($this->repartidor, $pedido->id_publico)
            ->assertJsonPath('pedido.estado.nombre', 'En tránsito')
            ->assertJsonPath('pedido.siguiente', ['estado' => EstadoPedido::ENTREGADO, 'boton' => 'Entregado']);
        $this->marcar($pedido, EstadoPedido::EN_ENTREGA)->assertConflict();
        $this->marcar($pedido, EstadoPedido::ENTREGADO)->assertOk()->assertJsonPath('pedido.estado.codigo', EstadoPedido::ENTREGADO);
    }

    #[TestDox('repartidor-E20: en un pedido aceptado en Recolectado, marcar Entregado sin pasar por En tránsito, En tránsito antes de Listo o Recolectado otra vez se rechaza con 409, y el pedido conserva su estado')]
    public function test_repartidor_e20_no_se_salta(): void
    {
        $pedido = $this->pedidoQueAcepto();
        $this->marcar($pedido, EstadoPedido::RECOLECTADO)->assertOk();
        $eventos = $this->eventosDe($pedido);

        foreach ([EstadoPedido::ENTREGADO, EstadoPedido::EN_ENTREGA, EstadoPedido::RECOLECTADO] as $boton) {
            $this->marcar($pedido, $boton)
                ->assertConflict()
                ->assertJsonPath('message', 'Ese no es el paso siguiente del pedido.')
                ->assertJsonPath('pedido.estado.codigo', EstadoPedido::RECOLECTADO);
        }

        $this->assertPedidoSinCambio($pedido, EstadoPedido::RECOLECTADO, $eventos);
    }

    #[TestDox('repartidor-E20: un pedido no se regresa: desde En tránsito, Recolectado se rechaza con 409; y un estado que no es botón del repartidor, como confirmado, aceptado o los de la lavandería, se rechaza con 422')]
    public function test_repartidor_e20_no_se_retrocede(): void
    {
        $pedido = $this->pedidoQueAcepto();
        foreach ([EstadoPedido::RECOLECTADO, EstadoPedido::EN_PROCESO, EstadoPedido::LISTO] as $boton) {
            $this->marcarComoLaLavanderia($pedido, $boton);
        }
        $this->marcar($pedido, EstadoPedido::EN_ENTREGA)->assertOk();
        $eventos = $this->eventosDe($pedido);

        $this->marcar($pedido, EstadoPedido::RECOLECTADO)
            ->assertConflict()
            ->assertJsonPath('message', 'Ese no es el paso siguiente del pedido.');

        foreach ([EstadoPedido::CONFIRMADO, EstadoPedido::ACEPTADO, EstadoPedido::EN_PROCESO, EstadoPedido::LISTO, EstadoPedido::RECHAZADO, 'en_transito', '', null] as $estado) {
            $this->marcar($pedido, $estado)
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['estado' => 'Ese no es un botón del repartidor.']);
        }

        $this->assertPedidoSinCambio($pedido, EstadoPedido::EN_ENTREGA, $eventos);
    }

    #[TestDox('repartidor-E20: en un pedido asignado pero sin aceptar, Recolectado se rechaza con 409 y el pedido conserva su estado; tampoco se mueve un pedido que la lavandería todavía no acepta')]
    public function test_repartidor_e20_no_se_mueve_antes_de_aceptar(): void
    {
        $sinAceptar = $this->pedidoAceptadoDe($this->cuentaDeUsuario());
        $this->ofrecerA($sinAceptar, $this->repartidor);
        $eventos = $this->eventosDe($sinAceptar);

        $this->marcar($sinAceptar, EstadoPedido::RECOLECTADO)
            ->assertConflict()
            ->assertJsonPath('message', 'Primero acepta el pedido.')
            ->assertJsonPath('pedido.asignacion.estado', 'ofrecido');

        $this->assertPedidoSinCambio($sinAceptar, EstadoPedido::ACEPTADO, $eventos);

        // La lavandería todavía no responde: el repartidor lo aceptó, pero no tiene botón hasta que ella lo acepte.
        $porResponder = $this->pedidoConfirmadoDe($this->cuentaDeUsuario());
        $this->ofrecerA($porResponder, $this->repartidor);
        app(RespuestaDelRepartidor::class)->aceptar($porResponder, $this->repartidor);
        $eventos = $this->eventosDe($porResponder);

        $this->pedidoComoRepartidor($this->repartidor, $porResponder->id_publico)->assertJsonPath('pedido.siguiente', null);
        $this->marcar($porResponder, EstadoPedido::RECOLECTADO)
            ->assertConflict()
            ->assertJsonPath('message', 'Ese no es el paso siguiente del pedido.');

        $this->assertPedidoSinCambio($porResponder, EstadoPedido::CONFIRMADO, $eventos);
    }

    #[TestDox('repartidor-E21: otro repartidor, con o sin oferta del mismo pedido, no cambia el estado de un pedido que aceptó otro: responde 404 sin datos y el pedido conserva su estado')]
    public function test_repartidor_e21_otro_repartidor_no_mueve_el_pedido(): void
    {
        $pedido = $this->pedidoQueAcepto();
        $this->marcar($pedido, EstadoPedido::RECOLECTADO)->assertOk();
        $eventos = $this->eventosDe($pedido);
        $sinOferta = $this->repartidorActivo();
        $conOferta = $this->repartidorActivo();
        $this->ofrecerA($pedido, $conOferta);
        foreach ([EstadoPedido::EN_PROCESO, EstadoPedido::LISTO] as $boton) {
            $this->marcarComoLaLavanderia($pedido, $boton);
        }
        $eventos = $this->eventosDe($pedido);

        foreach ([$sinOferta, $conOferta] as $otro) {
            $this->marcarComo($otro, $pedido, EstadoPedido::EN_ENTREGA)
                ->assertNotFound()
                ->assertExactJson(['message' => 'No existe el pedido.']);
        }

        $this->assertPedidoSinCambio($pedido, EstadoPedido::LISTO, $eventos);
        $this->assertSame($this->repartidor->getKey(), $pedido->fresh()->id_repartidores);
    }

    /**
     * Un pedido que la lavandería aceptó, ofrecido al repartidor y aceptado por él con la operación real.
     */
    private function pedidoQueAcepto(): Pedido
    {
        $pedido = $this->pedidoAceptadoDe($this->cuentaDeUsuario());
        $this->ofrecerA($pedido, $this->repartidor);
        app(RespuestaDelRepartidor::class)->aceptar($pedido, $this->repartidor);

        return $pedido->fresh();
    }

    private function marcar(Pedido $pedido, mixed $estado): TestResponse
    {
        return $this->marcarComo($this->repartidor, $pedido, $estado);
    }

    private function marcarComo(Repartidor $repartidor, Pedido $pedido, mixed $estado): TestResponse
    {
        return $this->comoRepartidor($repartidor)->postJson(
            "/api/repartidor/pedidos/{$pedido->id_publico}/estado",
            $estado === null ? [] : ['estado' => $estado],
        );
    }

    /**
     * El reloj en esa hora del jueves de prueba, en la hora de la plaza.
     */
    private function alas(string $hora): void
    {
        $this->travelTo(CarbonImmutable::parse(self::JUEVES_DE_PRUEBA.' '.$hora, self::ZONA_HORARIA));
    }
}
