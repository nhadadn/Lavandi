<?php

namespace Tests\Feature\Pago;

use App\Models\EstadoPedido;
use App\Models\EventoPasarela;
use App\Models\Pago;
use App\Models\Pedido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Pago\Soporte\CobrosDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * Los comandos de desarrollo del cobro, para probar el flujo en local sin pasarela y sin el teléfono
 * (openspec/changes/pago/design.md, "Decisiones de la implementación"). No son una ruta ni una pantalla del producto, y
 * se niegan a correr fuera de local y de las pruebas.
 *
 * - pago:cobro-de-prueba {pedido} {monto}: le crea al pedido el cobro pendiente que en el flujo real creará pago-E14,
 *   cuando DD-012 fije el monto. El monto es ficticio.
 * - pago:simular-aviso {pedido} {aprobado|rechazado}: la pasarela simulada manda su aviso para el cobro del pedido por el
 *   mismo camino del webhook (App\Cobro\RecepcionDeAvisos); si el cobro no tenía checkout, lo abre antes.
 *
 * Escenarios a los que sirve: pago-E03, pago-E04 y pago-E18, para probarlos a mano · Tarea: herramienta de desarrollo
 * del cambio pago
 */
class ComandosDeDesarrolloTest extends TestCase
{
    use AgendaDePrueba;
    use CobrosDePrueba;
    use LavanderiasDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    private Pedido $pedido;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();
        $this->pedido = $this->pedidoPorPagar();
    }

    #[TestDox('herramienta de desarrollo: pago:cobro-de-prueba le crea al pedido su cobro pendiente con tarjeta, en la pasarela simulada, por el monto que se le da')]
    public function test_cobro_de_prueba_crea_el_cobro_pendiente_del_pedido(): void
    {
        $this->artisan('pago:cobro-de-prueba', ['pedido' => $this->pedido->id_publico, 'monto' => '150.00'])
            ->expectsOutputToContain('Cobro pendiente de $150.00')
            ->assertSuccessful();

        $cobro = $this->pedido->cobro()->sole();
        $this->assertSame([Pago::PENDIENTE, Pago::TARJETA, Pago::PASARELA_SIMULADA, '150.00'], [$cobro->estado, $cobro->metodo, $cobro->pasarela, $cobro->monto]);
        $this->assertNull($cobro->id_pasarela_orden);

        // Otra vez no crea otro: un pedido tiene un solo cobro.
        $this->artisan('pago:cobro-de-prueba', ['pedido' => $this->pedido->id_publico, 'monto' => '150.00'])
            ->expectsOutputToContain('ya tiene su cobro')
            ->assertFailed();
        $this->assertSame(1, Pago::count());
    }

    #[TestDox('herramienta de desarrollo: pago:cobro-de-prueba no crea nada con un pedido que no existe, uno ya confirmado o un monto que no es mayor que cero')]
    public function test_cobro_de_prueba_revisa_el_pedido_y_el_monto(): void
    {
        $this->artisan('pago:cobro-de-prueba', ['pedido' => 'pedido-que-no-existe', 'monto' => '150.00'])
            ->expectsOutputToContain('No existe el pedido')
            ->assertFailed();

        foreach (['0', '-5', 'cien'] as $monto) {
            $this->artisan('pago:cobro-de-prueba', ['pedido' => $this->pedido->id_publico, 'monto' => $monto])
                ->expectsOutputToContain('El monto')
                ->assertFailed();
        }

        $confirmado = $this->pedidoPorPagar();
        $this->conCheckout($this->cobroPendiente($confirmado));
        $this->artisan('pago:simular-aviso', ['pedido' => $confirmado->id_publico, 'resultado' => 'aprobado'])->assertSuccessful();
        $this->artisan('pago:cobro-de-prueba', ['pedido' => $confirmado->id_publico, 'monto' => '150.00'])
            ->expectsOutputToContain('ya tiene su cobro')
            ->assertFailed();

        $this->assertSame(0, Pago::query()->where('id_pedidos', $this->pedido->getKey())->count());
    }

    #[TestDox('pago-E03 y pago-E18, herramienta de desarrollo: pago:simular-aviso aprobado manda el aviso de aprobado por el camino del webhook, abre antes el checkout si falta, y el cobro queda pagado y el pedido confirmado')]
    public function test_simular_aviso_aprobado_paga_el_cobro_y_confirma_el_pedido(): void
    {
        $cobro = $this->cobroPendiente($this->pedido);

        $this->artisan('pago:simular-aviso', ['pedido' => $this->pedido->id_publico, 'resultado' => 'aprobado'])
            ->expectsOutputToContain('pagado')
            ->expectsOutputToContain('confirmado')
            ->assertSuccessful();

        $this->assertNotNull($cobro->fresh()->id_pasarela_orden);
        $this->assertSame(Pago::PAGADO, $cobro->fresh()->estado);
        $this->assertSame(EstadoPedido::CONFIRMADO, $this->pedido->fresh()->estado->codigo);
        $this->assertNotNull($this->pedido->fresh()->folio);

        $evento = EventoPasarela::sole();
        $this->assertSame(EventoPasarela::APROBADO, $evento->tipo);
        $this->assertNotNull($evento->procesado_en);
    }

    #[TestDox('pago-E04, herramienta de desarrollo: pago:simular-aviso rechazado deja el cobro sin pagar y el pedido sin folio')]
    public function test_simular_aviso_rechazado_deja_el_cobro_sin_pagar(): void
    {
        $cobro = $this->conCheckout($this->cobroPendiente($this->pedido));

        $this->artisan('pago:simular-aviso', ['pedido' => $this->pedido->id_publico, 'resultado' => 'rechazado'])
            ->expectsOutputToContain('fallido')
            ->assertSuccessful();

        $this->assertSame(Pago::FALLIDO, $cobro->fresh()->estado);
        $this->assertNull($this->pedido->fresh()->folio);
        $this->assertSame(EventoPasarela::RECHAZADO, EventoPasarela::sole()->tipo);
    }

    #[TestDox('herramienta de desarrollo: pago:simular-aviso no manda nada con un resultado que no es aprobado ni rechazado, un pedido que no existe o uno sin cobro')]
    public function test_simular_aviso_revisa_lo_que_recibe(): void
    {
        $this->artisan('pago:simular-aviso', ['pedido' => $this->pedido->id_publico, 'resultado' => 'aprobado'])
            ->expectsOutputToContain('no tiene cobro')
            ->assertFailed();

        $this->cobroPendiente($this->pedido);

        $this->artisan('pago:simular-aviso', ['pedido' => $this->pedido->id_publico, 'resultado' => 'pagado'])
            ->expectsOutputToContain('aprobado o rechazado')
            ->assertFailed();
        $this->artisan('pago:simular-aviso', ['pedido' => 'pedido-que-no-existe', 'resultado' => 'aprobado'])
            ->expectsOutputToContain('No existe el pedido')
            ->assertFailed();

        $this->assertSame(0, EventoPasarela::count());
        $this->assertSame(Pago::PENDIENTE, $this->pedido->cobro()->sole()->estado);
    }

    #[TestDox('herramienta de desarrollo: fuera de local y de las pruebas, los dos comandos se niegan a correr y no tocan nada')]
    public function test_fuera_de_local_y_de_las_pruebas_los_comandos_no_corren(): void
    {
        $cobro = $this->conCheckout($this->cobroPendiente($this->pedido));
        $otro = $this->pedidoPorPagar();

        foreach (['production', 'staging'] as $entorno) {
            $this->app->detectEnvironment(fn (): string => $entorno);

            $this->artisan('pago:simular-aviso', ['pedido' => $this->pedido->id_publico, 'resultado' => 'aprobado'])
                ->expectsOutputToContain('solo corre en local y en las pruebas')
                ->assertFailed();
            $this->artisan('pago:cobro-de-prueba', ['pedido' => $otro->id_publico, 'monto' => '150.00'])
                ->expectsOutputToContain('solo corre en local y en las pruebas')
                ->assertFailed();
        }

        $this->assertSame(Pago::PENDIENTE, $cobro->fresh()->estado);
        $this->assertSame(0, EventoPasarela::count());
        $this->assertSame(0, Pago::query()->where('id_pedidos', $otro->getKey())->count());

        $this->app->detectEnvironment(fn (): string => 'local');
        $this->artisan('pago:simular-aviso', ['pedido' => $this->pedido->id_publico, 'resultado' => 'aprobado'])
            ->assertSuccessful();
    }
}
