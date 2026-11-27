<?php

namespace Tests\Feature\Pago;

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
 * El checkout de un cobro pendiente: la app lo pide mandando solo el pedido y el método, y servidor/ crea en la pasarela
 * la orden por el monto del cobro, el suyo, nunca el que mande la app (NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3,
 * Módulo A #10; ADR-0010, decisión 1). La pasarela vive detrás de la interfaz del componente cobro; aquí es la simulada
 * de las pruebas, que no llama a ningún proveedor.
 *
 * Un cobro tiene un solo checkout vigente: pedirlo otra vez devuelve el mismo. Solo la cuenta que hizo el pedido lo pide:
 * el de otra cuenta responde igual que uno que no existe (ADR-0019, decisión 3).
 *
 * Escenario: pago-E01 · Spec: openspec/changes/pago/specs/pago/spec.md · Tarea 1.3
 */
class CrearCheckoutTest extends TestCase
{
    use AgendaDePrueba;
    use CobrosDePrueba;
    use LavanderiasDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();
    }

    #[TestDox('pago-E01: la app pide pagar con tarjeta, aunque mande otro monto, y servidor/ crea en la pasarela simulada una orden por el monto del cobro, ligada al cobro por su id_pasarela_orden, y devuelve la URL del checkout')]
    public function test_pago_e01_crea_la_orden_por_el_monto_del_cobro_y_devuelve_el_checkout(): void
    {
        $pedido = $this->pedidoPorPagar();
        $cobro = $this->cobroPendiente($pedido);

        $respuesta = $this->pedirCheckout($pedido->usuario, $pedido, [
            'monto' => '1.00',
            'monto_estimado' => '1.00',
            'pago' => ['monto' => '1.00', 'estado' => Pago::PAGADO],
        ])->assertOk();

        $peticiones = $this->pasarelaSimulada()->peticiones();
        $this->assertCount(1, $peticiones);
        $this->assertSame('crear_orden', $peticiones[0]['operacion']);
        $this->assertSame(self::MONTO_DE_PRUEBA, $peticiones[0]['monto']);
        $this->assertSame($cobro->id_publico, $peticiones[0]['pago']);

        $guardado = $cobro->fresh();
        $this->assertSame($peticiones[0]['orden'], $guardado->id_pasarela_orden);
        $this->assertSame(Pago::PASARELA_SIMULADA, $guardado->pasarela);
        $this->assertSame(self::MONTO_DE_PRUEBA, $guardado->monto);
        $this->assertSame(Pago::PENDIENTE, $guardado->estado);
        $this->assertNull($guardado->pagado_en);

        $respuesta->assertExactJson([
            'checkout' => [
                // La pasarela simulada lleva a la página de checkout de desarrollo del propio servidor.
                'url' => url('/desarrollo/pasarela/checkout/'.$guardado->id_pasarela_orden),
                'regreso' => config('pasarela.regreso'),
            ],
            'pago' => [
                'id_publico' => $cobro->id_publico,
                'pedido' => $pedido->id_publico,
                'tipo' => Pago::COBRO,
                'metodo' => Pago::TARJETA,
                'estado' => Pago::PENDIENTE,
                'monto' => self::MONTO_DE_PRUEBA,
                'pagado_en' => null,
            ],
        ]);

        // Pedir el checkout no confirma nada: el pedido sigue por pagar y sin folio.
        $this->assertNull($pedido->fresh()->folio);
        $this->assertNull($pedido->fresh()->confirmado_en);
    }

    #[TestDox('pago-E01: si el cobro ya tenía un checkout vigente, servidor/ devuelve ese mismo en lugar de crear otro')]
    public function test_pago_e01_si_ya_hay_un_checkout_vigente_devuelve_el_mismo(): void
    {
        $pedido = $this->pedidoPorPagar();
        $cobro = $this->cobroPendiente($pedido);

        $primera = $this->pedirCheckout($pedido->usuario, $pedido)->assertOk()->json('checkout.url');
        $orden = $cobro->fresh()->id_pasarela_orden;

        $segunda = $this->pedirCheckout($pedido->usuario, $pedido)->assertOk()->json('checkout.url');

        $this->assertSame($primera, $segunda);
        $this->assertSame($orden, $cobro->fresh()->id_pasarela_orden);
        $this->assertSame(1, $this->ordenesCreadas());
        $this->assertSame(1, Pago::query()->where('id_pedidos', $pedido->getKey())->count());
    }

    #[TestDox('pago-E01: si el checkout que tenía el cobro ya no está vigente en la pasarela, servidor/ crea otra orden para el mismo cobro')]
    public function test_pago_e01_si_el_checkout_ya_no_esta_vigente_crea_otra_orden_para_el_mismo_cobro(): void
    {
        $pedido = $this->pedidoPorPagar();
        $cobro = $this->cobroPendiente($pedido);

        $primera = $this->pedirCheckout($pedido->usuario, $pedido)->assertOk()->json('checkout.url');
        $vencida = $cobro->fresh()->id_pasarela_orden;
        $this->pasarelaSimulada()->vencer($vencida);

        $segunda = $this->pedirCheckout($pedido->usuario, $pedido)->assertOk()->json('checkout.url');

        $this->assertNotSame($primera, $segunda);
        $this->assertNotSame($vencida, $cobro->fresh()->id_pasarela_orden);
        $this->assertSame(2, $this->ordenesCreadas());
        $this->assertSame(1, Pago::query()->where('id_pedidos', $pedido->getKey())->count());
    }

    #[TestDox('pago-E01: el pedido de otra cuenta, uno que no existe y un valor que no es ULID responden igual, 404 y sin datos, y no se crea ninguna orden')]
    public function test_pago_e01_el_pedido_de_otra_cuenta_responde_que_no_existe(): void
    {
        $ajeno = $this->pedidoPorPagar();
        $this->cobroPendiente($ajeno);
        $otraCuenta = $this->cuentaDeUsuario();

        foreach ([$ajeno->id_publico, Str::upper($ajeno->id_publico), (string) Str::ulid(), 'A-1'] as $pedido) {
            $this->pedirCheckout($otraCuenta, $pedido)
                ->assertNotFound()
                ->assertExactJson(['message' => 'No existe el pedido.']);
        }

        $this->assertSame(0, $this->ordenesCreadas());
        $this->assertNull($ajeno->cobro->fresh()->id_pasarela_orden);
    }

    #[TestDox('pago-E01: el pedido de otra cuenta responde 404 aunque el método falte o venga mal: lo propio se busca antes de revisar el cuerpo')]
    public function test_pago_e01_el_pedido_de_otra_cuenta_responde_404_aunque_el_metodo_venga_mal(): void
    {
        $ajeno = $this->pedidoPorPagar();
        $this->cobroPendiente($ajeno);
        $otraCuenta = $this->cuentaDeUsuario();

        foreach ([[], ['metodo' => Pago::EFECTIVO], ['metodo' => 'oxxo'], ['metodo' => ['tarjeta']]] as $metodo) {
            $this->pedirConDatos($otraCuenta, ['pedido' => $ajeno->id_publico, ...$metodo])
                ->assertNotFound()
                ->assertExactJson(['message' => 'No existe el pedido.']);
        }

        $this->assertSame(0, $this->ordenesCreadas());
    }

    #[TestDox('pago-E01: sin sesión responde 401, y una cuenta de lavandería o de repartidor no pasa el componente de autorización')]
    public function test_pago_e01_sin_sesion_o_sin_el_rol_de_usuario_no_se_pide_el_checkout(): void
    {
        $pedido = $this->pedidoPorPagar();
        $this->cobroPendiente($pedido);

        // Sin el token con que se creó el pedido.
        $this->app['auth']->forgetGuards();
        $this->withoutToken()
            ->postJson('/api/pagos/checkout', ['pedido' => $pedido->id_publico, 'metodo' => Pago::TARJETA])
            ->assertUnauthorized();

        foreach ([Rol::LAVANDERIA, Rol::REPARTIDOR] as $rol) {
            $this->pedirCheckout(Usuario::factory()->conRol($rol)->create(), $pedido)
                ->assertForbidden()
                ->assertJsonPath('message', 'Esta cuenta no tiene acceso.');
        }

        $this->assertSame(0, $this->ordenesCreadas());
    }

    #[TestDox('pago-E01: el pedido y el método son obligatorios; el método es tarjeta, y el efectivo todavía no está disponible (DD-056)')]
    public function test_pago_e01_el_pedido_y_el_metodo_son_obligatorios(): void
    {
        $pedido = $this->pedidoPorPagar();
        $this->cobroPendiente($pedido);
        $cuenta = $pedido->usuario;

        $this->pedirConDatos($cuenta, ['metodo' => Pago::TARJETA])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pedido' => 'Falta el pedido.']);
        $this->pedirConDatos($cuenta, ['pedido' => $pedido->id_publico])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['metodo' => 'Elige cómo pagar.']);
        $this->pedirConDatos($cuenta, ['pedido' => $pedido->id_publico, 'metodo' => Pago::EFECTIVO])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['metodo' => 'El pago en efectivo todavía no está disponible.']);
        $this->pedirConDatos($cuenta, ['pedido' => $pedido->id_publico, 'metodo' => 'oxxo'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['metodo' => 'El método de pago no es válido.']);

        $this->assertSame(0, $this->ordenesCreadas());
    }

    #[TestDox('pago-E01 y pago-E14: un pedido sin cobro todavía no se paga: responde 409 y no crea ninguna orden, porque el cobro lo crea pago-E14 cuando DD-012 fije el monto')]
    public function test_pago_e01_un_pedido_sin_cobro_todavia_no_se_paga(): void
    {
        $pedido = $this->pedidoPorPagar();

        $this->pedirCheckout($pedido->usuario, $pedido)
            ->assertConflict()
            ->assertExactJson(['message' => 'El cobro de este pedido todavía no está listo.']);

        $this->assertSame(0, $this->ordenesCreadas());
        $this->assertSame(0, Pago::count());
    }

    #[TestDox('pago-E01: un cobro pagado, vencido o cancelado ya no abre checkout: responde 409 sin tocar el cobro')]
    public function test_pago_e01_un_cobro_que_ya_no_se_paga_no_abre_checkout(): void
    {
        $casos = [
            [Pago::PAGADO, 'Este pedido ya está pagado.'],
            [Pago::VENCIDO, 'Este cobro ya no se puede pagar.'],
            [Pago::CANCELADO, 'Este cobro ya no se puede pagar.'],
        ];

        foreach ($casos as [$estado, $mensaje]) {
            $pedido = $this->pedidoPorPagar();
            $cobro = $this->cobroPendiente($pedido, datos: [
                'estado' => $estado,
                'pagado_en' => $estado === Pago::PAGADO ? now() : null,
            ]);

            $this->pedirCheckout($pedido->usuario, $pedido)->assertConflict()->assertExactJson(['message' => $mensaje]);

            $this->assertSame($estado, $cobro->fresh()->estado);
            $this->assertNull($cobro->fresh()->id_pasarela_orden);
        }

        $this->assertSame(0, $this->ordenesCreadas());
    }

    #[TestDox('pago-E01 y pago-E17: sin pasarela disponible responde 503 y el cobro queda como estaba')]
    public function test_pago_e01_sin_pasarela_disponible_responde_503(): void
    {
        $pedido = $this->pedidoPorPagar();
        $cobro = $this->cobroPendiente($pedido);
        config(['pasarela.simulada.llave' => null]);

        $this->pedirCheckout($pedido->usuario, $pedido)
            ->assertServiceUnavailable()
            ->assertExactJson(['message' => 'El pago no está disponible por ahora.']);

        $this->assertNull($cobro->fresh()->id_pasarela_orden);
        $this->assertSame(Pago::PENDIENTE, $cobro->fresh()->estado);
    }

    /**
     * La app pide el checkout del pedido con tarjeta, con la sesión de la cuenta y lo que se pase encima.
     *
     * @param  array<string, mixed>  $datos
     */
    private function pedirCheckout(Usuario $cuenta, Pedido|string $pedido, array $datos = []): TestResponse
    {
        return $this->pedirConDatos($cuenta, [
            'pedido' => $pedido instanceof Pedido ? $pedido->id_publico : $pedido,
            'metodo' => Pago::TARJETA,
            ...$datos,
        ]);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function pedirConDatos(Usuario $cuenta, array $datos): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($cuenta->createToken('app')->plainTextToken)->postJson('/api/pagos/checkout', $datos);
    }

    private function ordenesCreadas(): int
    {
        return count(array_filter(
            $this->pasarelaSimulada()->peticiones(),
            fn (array $peticion): bool => $peticion['operacion'] === 'crear_orden',
        ));
    }
}
