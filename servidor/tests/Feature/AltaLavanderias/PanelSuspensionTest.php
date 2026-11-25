<?php

namespace Tests\Feature\AltaLavanderias;

use App\Filament\Resources\Lavanderias\Pages\EditLavanderia;
use App\Filament\Resources\Lavanderias\Pages\ListLavanderias;
use App\Lavanderias\EstadoDeLaLavanderia;
use App\Lavanderias\TransicionNoPermitida;
use App\Models\Lavanderia;
use App\Models\Pedido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\AltaLavanderias\Soporte\EfectosParaElUsuario;
use Tests\Feature\AltaLavanderias\Soporte\PanelDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * La suspensión de una lavandería desde el panel y su levantamiento: mientras está suspendida no aparece al usuario ni
 * recibe pedidos nuevos; al levantarla recupera el estado que tenía, activa si ya tenía alta (aprobada_en) y pendiente
 * si no (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #1, "suspensión"; :16:Módulo A #3, "lavanderías cercanas
 * activas"; design.md, "Cómo usa los estados este cambio").
 *
 * Lo que pasa con los pedidos en curso al suspender espera DD-104 (alta-lavanderias-E16): aquí no se tocan.
 *
 * Escenarios: alta-lavanderias-E14 y alta-lavanderias-E15 · Tarea 3.2 · Spec:
 * openspec/changes/alta-de-lavanderias/specs/alta-lavanderias/spec.md
 */
class PanelSuspensionTest extends TestCase
{
    use AgendaDePrueba;
    use EfectosParaElUsuario;
    use LavanderiasDePrueba;
    use PanelDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();
    }

    #[TestDox('alta-lavanderias-E14: al suspenderla en el panel, la lavandería activa deja de aparecer en la búsqueda y el servidor rechaza un pedido nuevo para ella')]
    public function test_alta_lavanderias_e14_la_lavanderia_suspendida_deja_de_aparecer_y_no_recibe_pedidos(): void
    {
        $usuario = $this->cuentaDeUsuario();
        $this->assertTrue($this->apareceEnLaBusqueda($this->lavanderia, $usuario));
        $this->comoAdministracion();

        Livewire::test(ListLavanderias::class)
            ->assertTableActionVisible('suspender', $this->lavanderia)
            ->assertTableActionHidden('levantarSuspension', $this->lavanderia)
            ->callTableAction('suspender', $this->lavanderia)
            ->assertHasNoTableActionErrors();

        $this->assertSame(Lavanderia::SUSPENDIDA, $this->lavanderia->fresh()->estado);
        $this->assertFalse($this->apareceEnLaBusqueda($this->lavanderia, $usuario));
        $this->pedirComoUsuario("/api/lavanderias/{$this->lavanderia->id_publico}", [], $usuario)->assertNotFound();
        $this->pedirA($this->lavanderia, $usuario)->assertNotFound()->assertJsonPath('message', 'No existe la lavandería.');
        $this->assertSame(0, Pedido::count());
    }

    #[TestDox('alta-lavanderias-E14: la suspensión también se hace desde la página de la lavandería, y no borra nada')]
    public function test_alta_lavanderias_e14_la_suspension_se_hace_desde_la_pagina_de_la_lavanderia(): void
    {
        $this->comoAdministracion();

        Livewire::test(EditLavanderia::class, ['record' => $this->lavanderia->getRouteKey()])
            ->assertActionVisible('suspender')
            ->callAction('suspender')
            ->assertActionHidden('suspender')
            ->assertActionVisible('levantarSuspension');

        $lavanderia = Lavanderia::query()->whereKey($this->lavanderia->getKey())->sole();
        $this->assertSame(Lavanderia::SUSPENDIDA, $lavanderia->estado);
        $this->assertNull($lavanderia->deleted_at);
        $this->assertSame(1, $lavanderia->servicios()->count());
    }

    #[TestDox('alta-lavanderias-E15: al levantar la suspensión, la lavandería que tenía alta vuelve a activa, aparece y recibe pedidos nuevos')]
    public function test_alta_lavanderias_e15_al_levantar_la_suspension_vuelve_a_activa_y_recibe_pedidos(): void
    {
        $usuario = $this->cuentaDeUsuario();
        $this->lavanderia->update(['estado' => Lavanderia::SUSPENDIDA]);
        $this->assertNotNull($this->lavanderia->aprobada_en);
        $this->comoAdministracion();

        Livewire::test(ListLavanderias::class)
            ->assertTableActionHidden('suspender', $this->lavanderia)
            ->assertTableActionVisible('levantarSuspension', $this->lavanderia)
            ->callTableAction('levantarSuspension', $this->lavanderia)
            ->assertHasNoTableActionErrors();

        $this->assertSame(Lavanderia::ACTIVA, $this->lavanderia->fresh()->estado);
        $this->assertTrue($this->apareceEnLaBusqueda($this->lavanderia, $usuario));
        $this->pedirA($this->lavanderia, $usuario)->assertCreated();
        $this->assertSame(1, Pedido::count());
    }

    #[TestDox('alta-lavanderias-E15: una lavandería suspendida que nunca tuvo alta regresa a pendiente, no a activa')]
    public function test_alta_lavanderias_e15_la_que_nunca_tuvo_alta_regresa_a_pendiente(): void
    {
        $pendiente = Lavanderia::factory()->en($this->plaza)->pendiente()->create();
        $this->comoAdministracion();

        Livewire::test(ListLavanderias::class)
            ->assertTableActionVisible('suspender', $pendiente)
            ->callTableAction('suspender', $pendiente);
        $this->assertSame(Lavanderia::SUSPENDIDA, $pendiente->fresh()->estado);

        Livewire::test(ListLavanderias::class)->callTableAction('levantarSuspension', $pendiente);

        $pendiente->refresh();
        $this->assertSame(Lavanderia::PENDIENTE, $pendiente->estado);
        $this->assertNull($pendiente->aprobada_en);
    }

    #[TestDox('alta-lavanderias-E14 y E15: una lavandería de baja no se suspende, y solo se levanta la suspensión de una suspendida')]
    public function test_alta_lavanderias_e14_y_e15_solo_se_suspende_lo_que_se_puede(): void
    {
        $deBaja = Lavanderia::factory()->en($this->plaza)->deBaja()->create();
        $this->comoAdministracion();

        Livewire::test(ListLavanderias::class)
            ->assertTableActionHidden('suspender', $deBaja)
            ->assertTableActionHidden('levantarSuspension', $deBaja)
            ->assertTableActionHidden('levantarSuspension', $this->lavanderia);

        // La regla vive en el estado de la lavandería, no solo en los botones.
        $estado = app(EstadoDeLaLavanderia::class);

        foreach ([fn () => $estado->suspender($deBaja), fn () => $estado->levantarSuspension($this->lavanderia)] as $transicion) {
            try {
                $transicion();
                $this->fail('Se permitió una transición que no se puede.');
            } catch (TransicionNoPermitida) {
                // Esperado.
            }
        }

        $this->assertSame(Lavanderia::BAJA, $deBaja->fresh()->estado);
        $this->assertSame(Lavanderia::ACTIVA, $this->lavanderia->fresh()->estado);
    }
}
