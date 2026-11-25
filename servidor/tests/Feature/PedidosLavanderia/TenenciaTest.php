<?php

namespace Tests\Feature\PedidosLavanderia;

use App\Models\EstadoPedido;
use App\Models\Lavanderia;
use App\Models\Pedido;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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
 * Una lavandería no acepta ni rechaza un pedido hecho a otra lavandería, de su plaza o de otra: el servidor responde
 * igual que si el pedido no existiera, sin ningún dato, y el pedido no cambia (NSV-2026-001-v8.pdf:20:ANEXO A, Sección
 * A.3, Separación por tipo de usuario; ADR-0019, decisión 3). Lo decide App\Autorizacion\Autorizacion.
 *
 * Escenario: pedidos-lavanderia-E09 · Spec: openspec/changes/pedidos-de-la-lavanderia/specs/pedidos-lavanderia/spec.md ·
 * Tarea 1.4
 */
class TenenciaTest extends TestCase
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

    /** Un pedido hecho a la lavandería de prepararPedido(), que espera su respuesta. */
    private Pedido $pedido;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();

        $this->cuenta = $this->cuentaDeUsuario();
        $this->pedido = $this->pedidoConfirmadoDe($this->cuenta);
    }

    #[TestDox('pedidos-lavanderia-E09: una lavandería de la misma plaza no acepta ni rechaza el pedido de otra: el servidor responde que no existe, sin datos, y el pedido no cambia; su lavandería sí lo acepta')]
    public function test_pedidos_lavanderia_e09_otra_lavanderia_de_la_misma_plaza_no_lo_responde(): void
    {
        $this->assertNoLoRespondeLaCuentaDe($this->otraLavanderia());

        // Su propia lavandería sí lo responde: la prueba no pasa en vacío.
        $this->aceptarPedido($this->lavanderia->administrador, $this->pedido->id_publico)->assertOk();
    }

    #[TestDox('pedidos-lavanderia-E09: una lavandería de otra plaza no acepta ni rechaza el pedido: el servidor responde que no existe, sin datos, y el pedido no cambia')]
    public function test_pedidos_lavanderia_e09_una_lavanderia_de_otra_plaza_no_lo_responde(): void
    {
        $otraPlaza = $this->plazaConAgenda('Plaza de prueba 2', anticipacionMinutos: 0, diasHaciaAdelante: 3);

        $this->assertNoLoRespondeLaCuentaDe($this->otraLavanderia($otraPlaza));
    }

    #[TestDox('pedidos-lavanderia-E09: la respuesta a un pedido ajeno es la misma que a uno que no existe o a un valor que no es ULID, como su folio: no revela que existe')]
    public function test_pedidos_lavanderia_e09_la_respuesta_no_revela_que_el_pedido_existe(): void
    {
        $otra = $this->otraLavanderia()->administrador;

        foreach ([$this->pedido->id_publico, (string) Str::ulid(), $this->pedido->folio, Str::upper($this->pedido->id_publico)] as $identificador) {
            $this->aceptarPedido($otra, $identificador)->assertNotFound()->assertExactJson(self::NO_EXISTE);
            $this->rechazarPedido($otra, $identificador, 'Sin lugar.')->assertNotFound()->assertExactJson(self::NO_EXISTE);
        }
    }

    #[TestDox('pedidos-lavanderia-E09: un pedido aceptado por su lavandería tampoco lo toca otra: ni lo rechaza ni lo vuelve a aceptar')]
    public function test_pedidos_lavanderia_e09_un_pedido_aceptado_tampoco_lo_toca_otra(): void
    {
        $this->aceptarComoLaLavanderia($this->pedido);

        $this->assertNoLoRespondeLaCuentaDe($this->otraLavanderia(), EstadoPedido::ACEPTADO);
    }

    /**
     * La cuenta de esa lavandería intenta aceptar y rechazar el pedido: las dos responden que no existe, sin ningún
     * dato del pedido, y el pedido queda igual.
     */
    private function assertNoLoRespondeLaCuentaDe(Lavanderia $ajena, string $estado = EstadoPedido::CONFIRMADO): void
    {
        $eventos = $this->eventosDe($this->pedido);
        $socio = $ajena->administrador;

        $this->assertSinDatos($this->aceptarPedido($socio, $this->pedido->id_publico));
        $this->assertSinDatos($this->rechazarPedido($socio, $this->pedido->id_publico, 'Sin lugar.'));

        $this->assertPedidoSinCambio($this->pedido, $estado, $eventos);
    }

    private function assertSinDatos(TestResponse $respuesta): void
    {
        $respuesta->assertNotFound()->assertExactJson(self::NO_EXISTE);

        foreach ([$this->pedido->id_publico, $this->pedido->folio, $this->cuenta->correo] as $dato) {
            $this->assertStringNotContainsStringIgnoringCase($dato, $respuesta->getContent());
        }
    }
}
