<?php

namespace Tests\Feature\Pago;

use App\Models\EventoPasarela;
use App\Models\Pago;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Pago\Soporte\CobrosDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * El aviso de pago aprobado, ya traducido por la pasarela simulada, marca el cobro pagado con su pagado_en, y
 * servidor/ responde 200 (ADR-0010, decisión 1: el cobro se da por bueno solo con el aviso de la pasarela).
 *
 * El cobro se encuentra por su orden en la pasarela que mandó el aviso: un aviso de una orden que no es de ningún cobro
 * se da por procesado sin cambiar nada, con su nota, y uno de un cobro que ya está pagado no lo cambia.
 *
 * Escenario: pago-E03 · Spec: openspec/changes/pago/specs/pago/spec.md · Tarea 2.2
 */
class PagoAprobadoTest extends TestCase
{
    use AgendaDePrueba;
    use CobrosDePrueba;
    use LavanderiasDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    private Pago $cobro;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();
        $this->cobro = $this->conCheckout($this->cobroPendiente($this->pedidoPorPagar()));
    }

    #[TestDox('pago-E03: con el aviso auténtico de pago aprobado de su orden, el cobro queda pagado, con su pagado_en, y servidor/ responde 200')]
    public function test_pago_e03_el_aviso_de_aprobado_deja_el_cobro_pagado(): void
    {
        $this->elJuevesALas('10:00:00');

        $this->avisar($this->cobro, 'aprobado')->assertOk()->assertExactJson(['recibido' => true]);

        $pagado = $this->cobro->fresh();
        $this->assertSame(Pago::PAGADO, $pagado->estado);
        $this->assertTrue($pagado->pagado_en->eq(CarbonImmutable::now()));

        $evento = EventoPasarela::sole();
        $this->assertNotNull($evento->procesado_en);
        $this->assertNull($evento->error);
    }

    #[TestDox('pago-E03: un segundo aviso de aprobado de la misma orden, con otro identificador, responde 200 y no cambia el pagado_en')]
    public function test_pago_e03_un_segundo_aviso_de_aprobado_no_cambia_el_pagado_en(): void
    {
        $this->elJuevesALas('10:00:00');
        $this->avisar($this->cobro, 'aprobado')->assertOk();
        $pagadoEn = $this->cobro->fresh()->pagado_en;

        $this->elJuevesALas('10:20:00');
        $this->avisar($this->cobro, 'aprobado')->assertOk();

        $this->assertSame(2, EventoPasarela::count());
        $this->assertSame(Pago::PAGADO, $this->cobro->fresh()->estado);
        $this->assertTrue($pagadoEn->eq($this->cobro->fresh()->pagado_en));
    }

    #[TestDox('pago-E03: el aviso de aprobado de una orden que no es de ningún cobro responde 200, queda procesado con su nota y no paga ningún cobro')]
    public function test_pago_e03_el_aviso_de_una_orden_desconocida_no_paga_nada(): void
    {
        $aviso = $this->pasarelaSimulada()->avisoSimulado('orden-de-otro-entorno', 'aprobado');

        $this->avisoCrudo($aviso['cuerpo'], $aviso['cabeceras'])->assertOk();

        $evento = EventoPasarela::sole();
        $this->assertNotNull($evento->procesado_en);
        $this->assertSame('No hay un cobro con esa orden.', $evento->error);
        $this->assertSame(Pago::PENDIENTE, $this->cobro->fresh()->estado);
        $this->assertNull($this->cobro->fresh()->pagado_en);
    }

    #[TestDox('pago-E03: el aviso de aprobado de un cobro vencido o cancelado no lo cambia: queda procesado con su nota, para conciliarlo (DD-016)')]
    public function test_pago_e03_el_aviso_de_aprobado_de_un_cobro_que_ya_no_se_paga_no_lo_cambia(): void
    {
        foreach ([Pago::VENCIDO, Pago::CANCELADO] as $estado) {
            $this->cobro->forceFill(['estado' => $estado])->save();

            $this->avisar($this->cobro, 'aprobado')->assertOk();

            $this->assertSame($estado, $this->cobro->fresh()->estado);
            $this->assertNull($this->cobro->fresh()->pagado_en);
            $this->assertSame(
                "El cobro está {$estado}: el pago no se aplicó.",
                EventoPasarela::query()->latest('id_eventos_pasarela')->first()->error,
            );
        }
    }
}
