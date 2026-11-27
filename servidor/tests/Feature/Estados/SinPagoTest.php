<?php

namespace Tests\Feature\Estados;

use App\Models\EstadoPedido;
use App\Models\Pedido;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
 * Sin pago acreditado, la lavandería no ve ni mueve el pedido: no se lo devuelve al consultarlo, ni en su lista, ni la
 * deja marcarlo Recolectado (C-188 y C-197; NSV-2026-001-v8.pdf:16-17:ANEXO A, Sección A.3, Módulo A #9 a #11, en ese
 * orden). La regla mira pedidos.confirmado_en, no el nombre del estado previo al pago (ADR-0016).
 *
 * La consulta de un pedido de la lavandería es GET /api/lavanderia/pedidos/{id_publico}; el botón, POST
 * /api/lavanderia/pedidos/{id_publico}/estado.
 *
 * Escenario: estados-E23 · Spec: openspec/changes/estados-del-pedido/specs/estados/spec.md · Tarea 3.2
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();

        $this->cuenta = $this->cuentaDeUsuario();
        $this->socio = $this->lavanderia->administrador;
    }

    #[TestDox('estados-E23: la lavandería consulta un pedido suyo con el pago acreditado y la api se lo devuelve')]
    public function test_estados_e23_con_el_pago_acreditado_la_lavanderia_lo_consulta(): void
    {
        $pedido = $this->pedidoAceptadoDe($this->cuenta);

        $this->pedidoComoLavanderia($this->socio, $pedido->id_publico)
            ->assertOk()
            ->assertJsonPath('pedido.id_publico', $pedido->id_publico)
            ->assertJsonPath('pedido.folio', $pedido->folio)
            ->assertJsonPath('pedido.estado', ['codigo' => EstadoPedido::ACEPTADO, 'nombre' => 'Aceptado'])
            ->assertJsonPath('pedido.siguiente', ['estado' => EstadoPedido::RECOLECTADO, 'boton' => 'Recolectado']);
    }

    #[TestDox('estados-E23: un pedido hecho a la lavandería cuyo pago la pasarela todavía no confirma no se le devuelve al consultarlo, ni en su lista: la api responde que no existe, sin datos')]
    public function test_estados_e23_la_lavanderia_no_recibe_un_pedido_sin_pago(): void
    {
        $sinPago = $this->pedidoPorPagarEn($this->lavanderia, $this->cuenta);

        $this->assertSinDatos($this->pedidoComoLavanderia($this->socio, $sinPago->id_publico), $sinPago);
        $this->assertStringNotContainsString(
            $sinPago->id_publico,
            $this->listaDeLaLavanderia($this->socio)->assertOk()->getContent(),
        );
    }

    #[TestDox('estados-E23: la lavandería no marca Recolectado un pedido sin pago: la api responde que no existe, sin datos, el estado no cambia y no se agrega ningún evento a pedido_eventos')]
    public function test_estados_e23_la_lavanderia_no_mueve_un_pedido_sin_pago(): void
    {
        $sinPago = $this->pedidoPorPagarEn($this->lavanderia, $this->cuenta);
        $eventos = $this->eventosDe($sinPago);

        foreach ([EstadoPedido::RECOLECTADO, EstadoPedido::EN_PROCESO, EstadoPedido::LISTO] as $estado) {
            $this->assertSinDatos($this->marcarPedido($this->socio, $sinPago->id_publico, $estado), $sinPago);
        }

        $this->assertPedidoSinCambio($sinPago, EstadoPedido::POR_PAGAR, $eventos);
        $this->assertNull($sinPago->fresh()->confirmado_en);
    }

    private function assertSinDatos(TestResponse $respuesta, Pedido $pedido): void
    {
        $respuesta->assertNotFound()->assertExactJson(['message' => 'No existe el pedido.']);
        $this->assertStringNotContainsString($pedido->id_publico, $respuesta->getContent());
    }
}
