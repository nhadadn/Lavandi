<?php

namespace Tests\Feature\PedidosLavanderia;

use App\Cobro\Desarrollo\CobroSimulado;
use App\Models\EstadoPedido;
use App\Models\Pedido;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Estados\Soporte\EstadosDePrueba;
use Tests\Feature\PedidosLavanderia\Soporte\PedidosDeLaLavanderiaDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * Sin pago acreditado no hay pedido para la lavandería (C-188 y C-197): uno que nació al aceptar el resumen y cuyo pago
 * la pasarela todavía no confirma, con pedidos.confirmado_en vacío, no aparece en su lista ni se acepta o se rechaza. La
 * regla mira confirmado_en, no el nombre del estado previo al pago (ADR-0016).
 *
 * Aquí el pago lo acredita la pasarela simulada y el cobro confirma el pedido; uno sin acreditar se queda como lo dejó
 * POST /api/pedidos.
 *
 * Escenarios: pedidos-lavanderia-E17 y pedidos-lavanderia-E18 · Spec:
 * openspec/changes/pedidos-de-la-lavanderia/specs/pedidos-lavanderia/spec.md · Tarea 1.5
 */
class SinPagoTest extends TestCase
{
    use AgendaDePrueba;
    use EstadosDePrueba;
    use LavanderiasDePrueba;
    use PedidosDeLaLavanderiaDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    private Usuario $cuenta;

    private Usuario $socio;

    private Pedido $sinPago;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();

        $this->cuenta = $this->cuentaDeUsuario();
        $this->socio = $this->lavanderia->administrador;
        $this->sinPago = $this->pedidoPorPagarEn($this->lavanderia, $this->cuenta);
    }

    #[TestDox('pedidos-lavanderia-E17: con un pedido con su pago acreditado y otro cuyo pago la pasarela todavía no confirma, la lista trae solo el del pago acreditado')]
    public function test_pedidos_lavanderia_e17_un_pedido_sin_pago_no_aparece_en_la_lista(): void
    {
        $pagado = $this->pedidoConfirmadoDe($this->cuenta);
        $this->assertNull($this->sinPago->confirmado_en);

        $respuesta = $this->listaDeLaLavanderia($this->socio)->assertOk();

        $this->assertSame([$pagado->id_publico], $this->idsDeLaLista($respuesta));
        $this->assertStringNotContainsString($this->sinPago->id_publico, $respuesta->getContent());
    }

    #[TestDox('pedidos-lavanderia-E17: en cuanto la pasarela confirma el pago, el pedido aparece en la lista como por aceptar')]
    public function test_pedidos_lavanderia_e17_al_acreditarse_el_pago_el_pedido_aparece(): void
    {
        $this->assertSame([], $this->idsDeLaLista($this->listaDeLaLavanderia($this->socio)->assertOk()));

        $this->confirmarComoElCobro($this->sinPago);

        $respuesta = $this->listaDeLaLavanderia($this->socio)->assertOk();
        $this->assertSame([$this->sinPago->id_publico], $this->idsDeLaLista($respuesta));
        $respuesta->assertJsonPath('pedidos.0.por_responder', true);
    }

    #[TestDox('pedidos-lavanderia-E18: un pedido cuyo pago la pasarela no ha confirmado no se acepta ni se rechaza: el servidor responde que no existe, sin datos, y no agrega ningún evento a su historial')]
    public function test_pedidos_lavanderia_e18_un_pedido_sin_pago_no_se_acepta_ni_se_rechaza(): void
    {
        $eventos = $this->eventosDe($this->sinPago);

        foreach ([
            $this->aceptarPedido($this->socio, $this->sinPago->id_publico),
            $this->rechazarPedido($this->socio, $this->sinPago->id_publico, 'Sin lugar.'),
        ] as $respuesta) {
            $respuesta->assertNotFound()->assertExactJson(['message' => 'No existe el pedido.']);
            $this->assertStringNotContainsString($this->sinPago->id_publico, $respuesta->getContent());
        }

        $this->assertPedidoSinCambio($this->sinPago, EstadoPedido::POR_PAGAR, $eventos);
        $this->assertNull($this->sinPago->fresh()->confirmado_en);
    }

    #[TestDox('pedidos-lavanderia-E18: ya con el pago acreditado, su lavandería sí lo acepta')]
    public function test_pedidos_lavanderia_e18_con_el_pago_acreditado_si_se_acepta(): void
    {
        $this->confirmarComoElCobro($this->sinPago);

        $this->aceptarPedido($this->socio, $this->sinPago->id_publico)->assertOk();
        $this->assertSame(EstadoPedido::ACEPTADO, $this->sinPago->fresh()->estado->codigo);
    }

    /**
     * La pasarela confirma el pago: aquí, la simulada, cuyo aviso deja el cobro pagado y confirma el pedido (C-188).
     */
    private function confirmarComoElCobro(Pedido $pedido): void
    {
        app(CobroSimulado::class)->pagar($pedido);
    }
}
