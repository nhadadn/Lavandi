<?php

namespace Tests\Feature\Pago;

use App\Models\EstadoPedido;
use App\Models\EventoPasarela;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\PedidoEvento;
use App\Pedidos\ConfirmacionDePedido;
use App\Pedidos\PedidoConfirmado;
use App\Pedidos\PedidoSinPagoAcreditado;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\TestDox;
use RuntimeException;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Pago\Soporte\CobrosDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * El pago acreditado confirma el pedido; sin pago no hay confirmación ni recolección (C-188 y C-197; ADR-0010,
 * decisión 1; openspec/changes/pago/design.md, decisión 4).
 *
 * Al procesar el aviso de aprobado, servidor/ marca el cobro pagado y llama a la operación de confirmar de
 * resumen-y-confirmacion (App\Pedidos\ConfirmacionDePedido) en la misma transacción: si la confirmación falla, el cobro
 * tampoco queda pagado. Y esa operación rechaza un pedido cuyo cobro no está pagado, la llame quien la llame.
 *
 * Que un pedido sin confirmar no llega a la lavandería ni al repartidor lo prueban pedidos-de-la-lavanderia y repartidor.
 *
 * Escenarios: pago-E18 y pago-E19 · Spec: openspec/changes/pago/specs/pago/spec.md · Tarea 2.3
 */
class PagoConfirmaElPedidoTest extends TestCase
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

    #[TestDox('pago-E18: al procesar el aviso que confirma el cobro, el cobro queda pagado y el pedido pasa a Confirmado, con folio y confirmado_en, y el aviso queda procesado')]
    public function test_pago_e18_el_pago_acreditado_confirma_el_pedido(): void
    {
        Event::fake([PedidoConfirmado::class]);
        $this->elJuevesALas('10:00:00');

        $this->avisar($this->cobro, 'aprobado')->assertOk();

        $cobro = $this->cobro->fresh();
        $pedido = $this->pedido->fresh();
        $this->assertSame(Pago::PAGADO, $cobro->estado);
        $this->assertSame(EstadoPedido::CONFIRMADO, $pedido->estado->codigo);
        $this->assertNotNull($pedido->folio);
        $this->assertTrue($pedido->confirmado_en->eq(CarbonImmutable::now()));
        $this->assertTrue($pedido->confirmado_en->eq($cobro->pagado_en));

        $this->assertNotNull(EventoPasarela::sole()->procesado_en);
        $this->assertNull(EventoPasarela::sole()->error);

        $this->assertSame(
            [EstadoPedido::POR_PAGAR, EstadoPedido::CONFIRMADO],
            $this->codigosDelHistorial($this->pedido),
        );
        Event::assertDispatchedTimes(PedidoConfirmado::class, 1);
    }

    #[TestDox('pago-E18: cobro y pedido van en la misma transacción: si la confirmación falla, el cobro sigue pendiente, el pedido sin folio y el aviso sin procesar, y el reintento de la pasarela confirma los dos')]
    public function test_pago_e18_cobro_y_pedido_van_en_la_misma_transaccion(): void
    {
        $fallar = true;
        Event::listen('eloquent.creating: '.PedidoEvento::class, function () use (&$fallar): void {
            if ($fallar) {
                throw new RuntimeException('Falla de prueba.');
            }
        });
        $aviso = $this->pasarelaSimulada()->avisoSimulado($this->cobro->id_pasarela_orden, 'aprobado', 'evento-de-prueba-1');

        $this->avisoCrudo($aviso['cuerpo'], $aviso['cabeceras'])->assertServerError();

        $this->assertSame(Pago::PENDIENTE, $this->cobro->fresh()->estado);
        $this->assertNull($this->cobro->fresh()->pagado_en);
        $this->assertNull($this->pedido->fresh()->folio);
        $this->assertNull($this->pedido->fresh()->confirmado_en);
        $this->assertSame(EstadoPedido::POR_PAGAR, $this->pedido->fresh()->estado->codigo);
        $this->assertNull(EventoPasarela::sole()->procesado_en);
        $this->assertSame('Falla de prueba.', EventoPasarela::sole()->error);

        $fallar = false;
        $this->avisoCrudo($aviso['cuerpo'], $aviso['cabeceras'])->assertOk();

        $this->assertSame(Pago::PAGADO, $this->cobro->fresh()->estado);
        $this->assertSame(EstadoPedido::CONFIRMADO, $this->pedido->fresh()->estado->codigo);
        $this->assertNotNull(EventoPasarela::sole()->procesado_en);
    }

    #[TestDox('pago-E18 y pago-E10: el mismo aviso otra vez, u otro aviso de aprobado del mismo cobro, no confirma dos veces: el folio no cambia y no hay otro evento ni otra publicación')]
    public function test_pago_e18_un_aviso_repetido_no_confirma_dos_veces(): void
    {
        $this->avisar($this->cobro, 'aprobado', 'evento-de-prueba-1')->assertOk();
        $folio = $this->pedido->fresh()->folio;

        Event::fake([PedidoConfirmado::class]);
        $this->avisar($this->cobro, 'aprobado', 'evento-de-prueba-1')->assertOk();
        $this->avisar($this->cobro, 'aprobado', 'evento-de-prueba-2')->assertOk();

        $this->assertSame($folio, $this->pedido->fresh()->folio);
        $this->assertSame([EstadoPedido::POR_PAGAR, EstadoPedido::CONFIRMADO], $this->codigosDelHistorial($this->pedido));
        Event::assertNotDispatched(PedidoConfirmado::class);
    }

    #[TestDox('pago-E19: con su cobro pendiente, fallido o vencido, la operación de confirmar rechaza el pedido, que sigue sin folio, sin confirmado_en y sin otro evento')]
    public function test_pago_e19_sin_pago_acreditado_el_pedido_no_se_confirma(): void
    {
        foreach ([Pago::PENDIENTE, Pago::FALLIDO, Pago::VENCIDO] as $estado) {
            $this->cobro->forceFill(['estado' => $estado])->save();

            $this->assertRechazado($this->pedido);
        }
    }

    #[TestDox('pago-E19: un pedido sin cobro tampoco se confirma, ni con un ajuste pagado: el que confirma es el cobro')]
    public function test_pago_e19_sin_cobro_o_con_solo_un_ajuste_pagado_no_se_confirma(): void
    {
        $sinCobro = $this->pedidoPorPagar();
        $this->assertRechazado($sinCobro);

        Pago::create([
            'id_pedidos' => $sinCobro->getKey(),
            'tipo' => Pago::AJUSTE,
            'metodo' => Pago::TARJETA,
            'pasarela' => Pago::PASARELA_SIMULADA,
            'monto' => '20.00',
            'estado' => Pago::PAGADO,
            'pagado_en' => now(),
        ]);
        $this->assertRechazado($sinCobro);
    }

    #[TestDox('pago-E19: un aviso de rechazo no confirma el pedido')]
    public function test_pago_e19_un_aviso_de_rechazo_no_confirma_el_pedido(): void
    {
        $this->avisar($this->cobro, 'rechazado')->assertOk();

        $this->assertNull($this->pedido->fresh()->folio);
        $this->assertNull($this->pedido->fresh()->confirmado_en);
        $this->assertSame(EstadoPedido::POR_PAGAR, $this->pedido->fresh()->estado->codigo);
    }

    /**
     * La operación de confirmar, llamada directo como la llamaría cualquier otro componente, rechaza el pedido y no le
     * cambia nada.
     */
    private function assertRechazado(Pedido $pedido): void
    {
        $eventos = PedidoEvento::query()->where('id_pedidos', $pedido->getKey())->count();

        try {
            app(ConfirmacionDePedido::class)->confirmar($pedido);
            $this->fail('Sin cobro pagado, el pedido no debió confirmarse.');
        } catch (PedidoSinPagoAcreditado $error) {
            $this->assertSame('El pedido no tiene su cobro pagado.', $error->getMessage());
        }

        $guardado = $pedido->fresh();
        $this->assertNull($guardado->folio);
        $this->assertNull($guardado->confirmado_en);
        $this->assertSame(EstadoPedido::POR_PAGAR, $guardado->estado->codigo);
        $this->assertSame($eventos, PedidoEvento::query()->where('id_pedidos', $pedido->getKey())->count());
    }

    /**
     * @return list<string>
     */
    private function codigosDelHistorial(Pedido $pedido): array
    {
        return PedidoEvento::query()
            ->where('id_pedidos', $pedido->getKey())
            ->orderBy('id_pedido_eventos')
            ->get()
            ->map(fn (PedidoEvento $evento): string => $evento->estado->codigo)
            ->all();
    }
}
