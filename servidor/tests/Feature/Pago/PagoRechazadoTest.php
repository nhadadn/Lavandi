<?php

namespace Tests\Feature\Pago;

use App\Models\EstadoPedido;
use App\Models\EventoPasarela;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Pago\Soporte\CobrosDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * El pago con tarjeta rechazado: el cobro sigue sin pagar, el pedido sigue en el estado previo al pago y sin folio, y el
 * usuario puede volver a intentar el pago del mismo cobro (ADR-0010, decisión 1).
 *
 * El rechazo deja el cobro fallido, que dice que el último intento se rechazó; la app lo sabe al consultar el cobro,
 * GET /api/pagos/{pago}, que es lo que consulta "Procesando pago" (pago-E08, tarea 4.2). Volver a pedir el checkout
 * reabre el mismo cobro, pendiente otra vez, con el mismo checkout si sigue vigente: no se abre otro cobro
 * (openspec/changes/pago/design.md, "Decisiones de la implementación").
 *
 * Escenario: pago-E04, y el lado del servidor de pago-E08 · Spec: openspec/changes/pago/specs/pago/spec.md · Tarea 2.5
 */
class PagoRechazadoTest extends TestCase
{
    use AgendaDePrueba;
    use CobrosDePrueba;
    use LavanderiasDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    private Pedido $pedido;

    private Pago $cobro;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();
        $this->pedido = $this->pedidoPorPagar();
        $this->cobro = $this->conCheckout($this->cobroPendiente($this->pedido));
    }

    #[TestDox('pago-E04: con el aviso auténtico de que el pago se rechazó, el cobro sigue sin pagar, como fallido, y el pedido sigue en el estado previo al pago, sin folio')]
    public function test_pago_e04_el_rechazo_deja_el_cobro_sin_pagar_y_el_pedido_sin_folio(): void
    {
        $this->avisar($this->cobro, 'rechazado')->assertOk()->assertExactJson(['recibido' => true]);

        $cobro = $this->cobro->fresh();
        $this->assertSame(Pago::FALLIDO, $cobro->estado);
        $this->assertNull($cobro->pagado_en);
        $this->assertSame($this->cobro->id_pasarela_orden, $cobro->id_pasarela_orden);

        $pedido = $this->pedido->fresh();
        $this->assertSame(EstadoPedido::POR_PAGAR, $pedido->estado->codigo);
        $this->assertNull($pedido->folio);
        $this->assertNull($pedido->confirmado_en);
        $this->assertNull(EventoPasarela::sole()->error);
    }

    #[TestDox('pago-E04 y pago-E08: al consultar el cobro, la app ve que el intento se rechazó')]
    public function test_pago_e04_la_consulta_del_cobro_dice_que_el_intento_se_rechazo(): void
    {
        $this->consultar($this->pedido->usuario, $this->cobro->id_publico)
            ->assertOk()
            ->assertJsonPath('pago.estado', Pago::PENDIENTE);

        $this->avisar($this->cobro, 'rechazado')->assertOk();

        $this->consultar($this->pedido->usuario, $this->cobro->id_publico)
            ->assertOk()
            ->assertExactJson(['pago' => [
                'id_publico' => $this->cobro->id_publico,
                'pedido' => $this->pedido->id_publico,
                'tipo' => Pago::COBRO,
                'metodo' => Pago::TARJETA,
                'estado' => Pago::FALLIDO,
                'monto' => self::MONTO_DE_PRUEBA,
                'pagado_en' => null,
            ]]);
    }

    #[TestDox('pago-E04: el usuario vuelve a intentar el pago del mismo cobro: pedir el checkout otra vez reabre el cobro, pendiente, con el mismo checkout vigente y sin abrir otro cobro')]
    public function test_pago_e04_el_usuario_vuelve_a_intentar_con_el_mismo_cobro(): void
    {
        $url = $this->pedirCheckout($this->pedido)->assertOk()->json('checkout.url');
        $this->avisar($this->cobro, 'rechazado')->assertOk();

        $this->pedirCheckout($this->pedido)
            ->assertOk()
            ->assertJsonPath('checkout.url', $url)
            ->assertJsonPath('pago.id_publico', $this->cobro->id_publico)
            ->assertJsonPath('pago.estado', Pago::PENDIENTE);

        $this->assertSame(Pago::PENDIENTE, $this->cobro->fresh()->estado);
        $this->assertSame(1, Pago::query()->where('id_pedidos', $this->pedido->getKey())->count());
        $this->assertSame(1, $this->ordenesCreadas());

        // El segundo intento se aprueba: el cobro queda pagado y confirma el pedido.
        $this->avisar($this->cobro, 'aprobado')->assertOk();
        $this->assertSame(Pago::PAGADO, $this->cobro->fresh()->estado);
        $this->assertSame(EstadoPedido::CONFIRMADO, $this->pedido->fresh()->estado->codigo);
    }

    #[TestDox('pago-E04: si el checkout del intento rechazado ya no está vigente, el reintento crea otra orden para el mismo cobro')]
    public function test_pago_e04_si_el_checkout_ya_no_esta_vigente_el_reintento_crea_otra_orden(): void
    {
        $this->avisar($this->cobro, 'rechazado')->assertOk();
        $this->pasarelaSimulada()->vencer($this->cobro->id_pasarela_orden);

        $this->pedirCheckout($this->pedido)->assertOk()->assertJsonPath('pago.estado', Pago::PENDIENTE);

        $this->assertNotSame($this->cobro->id_pasarela_orden, $this->cobro->fresh()->id_pasarela_orden);
        $this->assertSame(1, Pago::query()->where('id_pedidos', $this->pedido->getKey())->count());
    }

    #[TestDox('pago-E04: si el mismo checkout cobra después de un rechazo, el aviso de aprobado paga el cobro y confirma el pedido')]
    public function test_pago_e04_un_aprobado_despues_del_rechazo_paga_el_cobro(): void
    {
        $this->avisar($this->cobro, 'rechazado')->assertOk();
        $this->avisar($this->cobro, 'aprobado')->assertOk();

        $this->assertSame(Pago::PAGADO, $this->cobro->fresh()->estado);
        $this->assertNotNull($this->cobro->fresh()->pagado_en);
        $this->assertSame(EstadoPedido::CONFIRMADO, $this->pedido->fresh()->estado->codigo);
    }

    #[TestDox('pago-E04: un rechazo que llega después del pago no lo deshace: el cobro sigue pagado y el pedido confirmado')]
    public function test_pago_e04_un_rechazo_tardio_no_deshace_el_pago(): void
    {
        $this->avisar($this->cobro, 'aprobado')->assertOk();
        $folio = $this->pedido->fresh()->folio;

        $this->avisar($this->cobro, 'rechazado')->assertOk();

        $this->assertSame(Pago::PAGADO, $this->cobro->fresh()->estado);
        $this->assertSame($folio, $this->pedido->fresh()->folio);
        $this->assertSame(EstadoPedido::CONFIRMADO, $this->pedido->fresh()->estado->codigo);
    }

    #[TestDox('pago-E08: solo la cuenta que hizo el pedido consulta su cobro: el de otra cuenta, uno que no existe y un valor que no es ULID responden igual, 404 y sin datos')]
    public function test_pago_e08_el_cobro_de_otra_cuenta_responde_que_no_existe(): void
    {
        $otraCuenta = $this->cuentaDeUsuario();

        foreach ([$this->cobro->id_publico, (string) Str::ulid(), 'A-1'] as $pago) {
            $this->consultar($otraCuenta, $pago)->assertNotFound()->assertExactJson(['message' => 'No existe el pago.']);
        }
    }

    #[TestDox('pago-E08: sin sesión la consulta del cobro responde 401, y una cuenta de lavandería o de repartidor no pasa el componente de autorización')]
    public function test_pago_e08_sin_sesion_o_sin_el_rol_de_usuario_no_se_consulta_el_cobro(): void
    {
        $this->app['auth']->forgetGuards();
        $this->withoutToken()->getJson("/api/pagos/{$this->cobro->id_publico}")->assertUnauthorized();

        foreach ([Rol::LAVANDERIA, Rol::REPARTIDOR] as $rol) {
            $this->consultar(Usuario::factory()->conRol($rol)->create(), $this->cobro->id_publico)
                ->assertForbidden()
                ->assertJsonPath('message', 'Esta cuenta no tiene acceso.');
        }
    }

    private function consultar(Usuario $cuenta, string $pago): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($cuenta->createToken('app')->plainTextToken)->getJson("/api/pagos/{$pago}");
    }

    private function pedirCheckout(Pedido $pedido): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($pedido->usuario->createToken('app')->plainTextToken)
            ->postJson('/api/pagos/checkout', ['pedido' => $pedido->id_publico, 'metodo' => Pago::TARJETA]);
    }

    private function ordenesCreadas(): int
    {
        return count(array_filter(
            $this->pasarelaSimulada()->peticiones(),
            fn (array $peticion): bool => $peticion['operacion'] === 'crear_orden',
        ));
    }
}
