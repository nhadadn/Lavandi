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
 * Solo quien tiene el pedido lo mueve o lo consulta (NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3, Separación por tipo de
 * usuario; Módulo B #2). En este cambio lo mueve la lavandería que lo aceptó; el repartidor entra con repartidor (H8).
 *
 * - Otra lavandería, de la misma plaza o de otra, no lo ve: App\Autorizacion\Autorizacion responde que no existe
 *   (ADR-0019, decisión 3).
 * - El usuario no tiene el rol de lavandería: la ruta del botón le responde 403, y ninguna de sus rutas cambia el estado.
 * - La lavandería no mueve un pedido que no aceptó o que rechazó: la tabla de App\Pedidos\CambioDeEstado no tiene ese
 *   cambio (ADR-0021, decisión 4).
 * - Un usuario no consulta el pedido de otro (confirmacion-E12).
 *
 * Escenarios: estados-E07, estados-E08, estados-E09 y estados-E10 · Spec:
 * openspec/changes/estados-del-pedido/specs/estados/spec.md · Tarea 3.1
 */
class QuienMueveElPedidoTest extends TestCase
{
    use AgendaDePrueba;
    use EstadosDePrueba;
    use LavanderiasDePrueba;
    use PedidosDeLaLavanderiaDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    private const NO_EXISTE = ['message' => 'No existe el pedido.'];

    private Usuario $cuenta;

    private Usuario $socio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();

        $this->cuenta = $this->cuentaDeUsuario();
        $this->socio = $this->lavanderia->administrador;
    }

    #[TestDox('estados-E07: con un pedido aceptado por la lavandería A, la lavandería B de la misma plaza no lo marca Recolectado: la api responde que no existe, sin datos, y el estado no cambia; A sí lo marca')]
    public function test_estados_e07_otra_lavanderia_de_la_misma_plaza_no_mueve_el_pedido(): void
    {
        $pedido = $this->pedidoAceptadoDe($this->cuenta);
        $eventos = $this->eventosDe($pedido);

        $this->assertSinDatos($this->marcarPedido($this->otraLavanderia()->administrador, $pedido->id_publico, EstadoPedido::RECOLECTADO), $pedido);
        $this->assertPedidoSinCambio($pedido, EstadoPedido::ACEPTADO, $eventos);

        // La lavandería que lo aceptó sí lo mueve: la prueba no pasa en vacío.
        $this->marcarPedido($this->socio, $pedido->id_publico, EstadoPedido::RECOLECTADO)->assertOk();
    }

    #[TestDox('estados-E07: una lavandería de otra plaza tampoco lo mueve ni lo consulta: la api responde que no existe, sin datos, y el estado no cambia')]
    public function test_estados_e07_una_lavanderia_de_otra_plaza_no_mueve_el_pedido(): void
    {
        $pedido = $this->pedidoAceptadoDe($this->cuenta);
        $eventos = $this->eventosDe($pedido);
        $otraPlaza = $this->plazaConAgenda('Plaza de prueba 2', anticipacionMinutos: 0, diasHaciaAdelante: 3);
        $ajena = $this->otraLavanderia($otraPlaza)->administrador;

        $this->assertSinDatos($this->marcarPedido($ajena, $pedido->id_publico, EstadoPedido::RECOLECTADO), $pedido);
        $this->assertSinDatos($this->pedidoComoLavanderia($ajena, $pedido->id_publico), $pedido);
        $this->assertPedidoSinCambio($pedido, EstadoPedido::ACEPTADO, $eventos);
    }

    #[TestDox('estados-E08: el usuario no cambia el estado de su pedido en Confirmado: la ruta del botón le responde 403 sin datos, ninguna ruta suya cambia el estado, y el estado no cambia')]
    public function test_estados_e08_el_usuario_no_cambia_el_estado_de_su_pedido(): void
    {
        $pedido = $this->pedidoConfirmadoDe($this->cuenta);
        $eventos = $this->eventosDe($pedido);

        foreach ([
            $this->marcarPedido($this->cuenta, $pedido->id_publico, EstadoPedido::RECOLECTADO),
            $this->aceptarPedido($this->cuenta, $pedido->id_publico),
        ] as $respuesta) {
            $respuesta->assertForbidden()
                ->assertJsonPath('message', 'Esta cuenta no tiene acceso.')
                ->assertJsonMissingPath('pedido');
        }

        // Su pedido solo se consulta: ninguna ruta de la cuenta lo cambia.
        $token = $this->cuenta->createToken('app')->plainTextToken;
        foreach (['put', 'patch', 'post', 'delete'] as $metodo) {
            $this->app['auth']->forgetGuards();
            $this->withToken($token)
                ->json($metodo, "/api/pedidos/{$pedido->id_publico}", ['estado' => EstadoPedido::RECOLECTADO])
                ->assertMethodNotAllowed();
        }

        $this->assertPedidoSinCambio($pedido, EstadoPedido::CONFIRMADO, $eventos);
    }

    #[TestDox('estados-E09: la lavandería no marca Recolectado un pedido que todavía no acepta: la api rechaza el cambio y el estado no cambia')]
    public function test_estados_e09_la_lavanderia_no_mueve_un_pedido_que_no_acepto(): void
    {
        $pedido = $this->pedidoConfirmadoDe($this->cuenta);
        $eventos = $this->eventosDe($pedido);

        $this->marcarPedido($this->socio, $pedido->id_publico, EstadoPedido::RECOLECTADO)
            ->assertConflict()
            ->assertJsonPath('message', 'Ese no es el paso siguiente del pedido.')
            ->assertJsonPath('pedido.por_responder', true);

        $this->assertPedidoSinCambio($pedido, EstadoPedido::CONFIRMADO, $eventos);
    }

    #[TestDox('estados-E09: la lavandería no marca Recolectado, ni ningún otro botón, un pedido que rechazó: la api rechaza el cambio y el estado no cambia')]
    public function test_estados_e09_la_lavanderia_no_mueve_un_pedido_que_rechazo(): void
    {
        $pedido = $this->pedidoConfirmadoDe($this->cuenta);
        $this->rechazarComoLaLavanderia($pedido);
        $eventos = $this->eventosDe($pedido);

        foreach ([EstadoPedido::RECOLECTADO, EstadoPedido::EN_PROCESO, EstadoPedido::LISTO, EstadoPedido::EN_ENTREGA] as $estado) {
            $this->marcarPedido($this->socio, $pedido->id_publico, $estado)
                ->assertConflict()
                ->assertJsonPath('pedido.estado.codigo', EstadoPedido::RECHAZADO)
                ->assertJsonPath('pedido.siguiente', null);
        }

        $this->assertPedidoSinCambio($pedido, EstadoPedido::RECHAZADO, $eventos);
    }

    #[TestDox('estados-E10: con dos usuarios con un pedido cada uno, uno no consulta el pedido del otro: la api no devuelve ningún dato, tampoco cuando la lavandería ya lo movió')]
    public function test_estados_e10_un_usuario_no_consulta_el_pedido_de_otro(): void
    {
        $otro = $this->cuentaDeUsuario();
        $suyo = $this->pedidoConfirmadoDe($this->cuenta);
        $delOtro = $this->pedidoAceptadoDe($otro);
        $this->marcarComoLaLavanderia($delOtro, EstadoPedido::RECOLECTADO);

        $this->assertSinDatos($this->consultarPedido($this->cuenta, $delOtro->id_publico), $delOtro);
        $this->assertSinDatos($this->consultarPedido($otro, $suyo->id_publico), $suyo);

        // Cada uno sí consulta el suyo.
        $this->consultarPedido($otro, $delOtro->id_publico)->assertOk()
            ->assertJsonPath('pedido.estado.codigo', EstadoPedido::RECOLECTADO);
    }

    private function assertSinDatos(TestResponse $respuesta, Pedido $pedido): void
    {
        $respuesta->assertNotFound()->assertExactJson(self::NO_EXISTE);

        foreach ([$pedido->id_publico, $pedido->folio] as $dato) {
            $this->assertStringNotContainsStringIgnoringCase($dato, $respuesta->getContent());
        }
    }
}
