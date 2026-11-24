<?php

namespace Tests\Feature\Pago;

use App\Models\EstadoPedido;
use App\Models\Lavanderia;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Plaza;
use App\Pedidos\PedidoConfirmado;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Pago\Soporte\CobrosDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * De cada pago confirmado, servidor/ guarda la pasarela que cobró, la referencia de su orden, el monto, el método, la
 * fecha de confirmación, el pedido y su plaza, para que el Módulo D lo concilie con la pasarela
 * (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #6; :20, la base guarda "pagos"; :4:Cláusula I, la plaza).
 *
 * La conciliación y los reportes viven en administracion (DD-112); aquí solo se prueba que el registro queda completo,
 * en una sola fila de pagos y sin ningún dato de tarjeta.
 *
 * Escenario: pago-E11 · Spec: openspec/changes/pago/specs/pago/spec.md · Tarea 2.4
 */
class RegistroParaConciliacionTest extends TestCase
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

    #[TestDox('pago-E11: al procesar el aviso que confirma el cobro de un pedido de la plaza P, el pago queda guardado con su pasarela, su id_pasarela_orden, su monto, su método, su pagado_en, su id_pedidos y la plaza P')]
    public function test_pago_e11_el_pago_confirmado_queda_con_todo_lo_de_la_conciliacion(): void
    {
        // El pedido de la plaza P se inserta sin la API, sin servicios ni direcciones: no se le manda correo ni aviso.
        Event::fake([PedidoConfirmado::class]);
        $plaza = $this->plazaConAgenda('Plaza de prueba P', self::ZONA_HORARIA);
        $pedido = $this->pedidoEn($plaza);
        $cobro = $this->conCheckout($this->cobroPendiente($pedido, '245.50'));
        $orden = $cobro->id_pasarela_orden;
        $this->elJuevesALas('10:00:00');

        $this->avisar($cobro, 'aprobado')->assertOk();

        $fila = (array) DB::table('pagos')->where('id_pagos', $cobro->getKey())->sole();
        $this->assertSame(Pago::PASARELA_SIMULADA, $fila['pasarela']);
        $this->assertSame($orden, $fila['id_pasarela_orden']);
        $this->assertSame('245.50', $fila['monto']);
        $this->assertSame(Pago::TARJETA, $fila['metodo']);
        $this->assertSame(Pago::COBRO, $fila['tipo']);
        $this->assertSame(Pago::PAGADO, $fila['estado']);
        $this->assertTrue(CarbonImmutable::parse($fila['pagado_en'])->eq(CarbonImmutable::now()));
        $this->assertSame($pedido->getKey(), $fila['id_pedidos']);
        $this->assertSame($plaza->getKey(), $fila['id_plazas']);
        $this->assertNotSame($this->plaza->getKey(), $fila['id_plazas']);

        // La conciliación lo encuentra por su orden en su pasarela, y su pedido queda confirmado en la misma plaza.
        $this->assertTrue($cobro->is(Pago::query()->where('pasarela', Pago::PASARELA_SIMULADA)->where('id_pasarela_orden', $orden)->sole()));
        $this->assertSame(EstadoPedido::CONFIRMADO, $pedido->fresh()->estado->codigo);
        $this->assertSame($plaza->getKey(), $pedido->fresh()->id_plazas);
        $this->assertSame(1, Pago::count());
    }

    #[TestDox('pago-E11: el registro del pago no lleva ningún dato de tarjeta: solo la pasarela y la referencia de su orden la ligan con la pasarela')]
    public function test_pago_e11_el_registro_no_lleva_datos_de_tarjeta(): void
    {
        $cobro = $this->conCheckout($this->cobroPendiente($this->pedidoPorPagar()));

        $this->avisar($cobro, 'aprobado')->assertOk();

        $this->assertSame(
            [
                'id_pagos', 'id_publico', 'id_pedidos', 'id_plazas', 'tipo', 'metodo', 'pasarela', 'monto', 'estado',
                'id_pasarela_orden', 'referencia_efectivo', 'vence_en', 'pagado_en', 'created_at', 'updated_at',
            ],
            array_keys((array) DB::table('pagos')->where('id_pagos', $cobro->getKey())->sole()),
        );
        $this->assertNull($cobro->fresh()->referencia_efectivo);
        $this->assertNull($cobro->fresh()->vence_en);
    }

    /**
     * Un pedido por pagar de una lavandería nueva de la plaza, de una cuenta nueva, insertado sin la API.
     */
    private function pedidoEn(Plaza $plaza): Pedido
    {
        return Pedido::create([
            'id_plazas' => $plaza->getKey(),
            'id_usuarios' => $this->cuentaDeUsuario()->getKey(),
            'id_lavanderias' => Lavanderia::factory()->en($plaza)->create()->getKey(),
            'id_estados_pedido' => $this->estadoPrevioAlPago()->getKey(),
            'ventana_inicio' => '2026-12-03 17:00:00+00',
            'ventana_fin' => '2026-12-03 18:00:00+00',
        ]);
    }
}
