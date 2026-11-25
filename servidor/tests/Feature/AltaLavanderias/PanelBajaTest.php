<?php

namespace Tests\Feature\AltaLavanderias;

use App\Filament\Resources\Lavanderias\Pages\EditLavanderia;
use App\Filament\Resources\Lavanderias\Pages\ListLavanderias;
use App\Lavanderias\EstadoDeLaLavanderia;
use App\Lavanderias\TransicionNoPermitida;
use App\Models\EstadoPedido;
use App\Models\Lavanderia;
use App\Models\Pedido;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\AltaLavanderias\Soporte\EfectosParaElUsuario;
use Tests\Feature\AltaLavanderias\Soporte\PanelDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Estados\Soporte\EstadosDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * La baja de una lavandería desde el panel: deja de aparecer al usuario y de recibir pedidos, pero la lavandería y sus
 * pedidos pasados se quedan en la base (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #1, "baja"; :20:Servicios
 * externos, "pedidos, pagos e historial completo"). La baja es estado = 'baja', sin borrar la fila ni en suave
 * (design.md, Open Questions 10).
 *
 * Escenario: alta-lavanderias-E17 · Tarea 3.3 · Spec: openspec/changes/alta-de-lavanderias/specs/alta-lavanderias/spec.md
 */
class PanelBajaTest extends TestCase
{
    use AgendaDePrueba;
    use EfectosParaElUsuario;
    use EstadosDePrueba;
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

    #[TestDox('alta-lavanderias-E17: al darle de baja, la lavandería con pedidos entregados deja de aparecer y no recibe pedidos nuevos, y ella y sus pedidos siguen en la base')]
    public function test_alta_lavanderias_e17_la_baja_conserva_la_lavanderia_y_sus_pedidos(): void
    {
        $usuario = $this->cuentaDeUsuario();
        $entregado = $this->pedidoEntregadoDe($usuario);
        $this->calificar($this->lavanderia, [5]);
        $this->comoAdministracion();

        Livewire::test(ListLavanderias::class)
            ->assertTableActionVisible('darDeBaja', $this->lavanderia)
            ->callTableAction('darDeBaja', $this->lavanderia)
            ->assertHasNoTableActionErrors()
            // La sigue listando, con su estado.
            ->assertCanSeeTableRecords([$this->lavanderia])
            ->assertTableColumnFormattedStateSet('estado', 'Dada de baja', $this->lavanderia)
            ->assertTableActionHidden('darDeBaja', $this->lavanderia);

        // Deja de aparecer y no recibe pedidos nuevos.
        $this->assertFalse($this->apareceEnLaBusqueda($this->lavanderia, $usuario));
        $this->pedirComoUsuario("/api/lavanderias/{$this->lavanderia->id_publico}", [], $usuario)->assertNotFound();
        $this->pedirA($this->lavanderia, $usuario)->assertNotFound();

        // La lavandería sigue en la base, sin borrado suave, con sus servicios, su horario y sus calificaciones.
        $fila = DB::table('lavanderias')->where('id_lavanderias', $this->lavanderia->getKey())->sole();
        $this->assertSame([Lavanderia::BAJA, null], [$fila->estado, $fila->deleted_at]);
        $this->assertSame(1, $this->lavanderia->servicios()->count());
        $this->assertSame(7, $this->lavanderia->horarios()->count());
        $this->assertSame(1, $this->lavanderia->calificaciones()->count());

        // Su pedido entregado sigue guardado, apunta a ella y el usuario lo sigue viendo en su historial.
        $this->assertSame(1, Pedido::count());
        $entregado->refresh();
        $this->assertSame([$this->lavanderia->getKey(), EstadoPedido::ENTREGADO], [$entregado->id_lavanderias, $entregado->estado->codigo]);
        $this->assertTrue($this->lavanderia->is($entregado->lavanderia));
        $this->consultarPedido($usuario, $entregado->id_publico)->assertOk();
    }

    #[TestDox('alta-lavanderias-E17: se da de baja desde la página de la lavandería, también si estaba suspendida o pendiente, y la baja no se deshace desde el panel')]
    public function test_alta_lavanderias_e17_la_baja_desde_la_pagina_y_desde_otros_estados(): void
    {
        $suspendida = Lavanderia::factory()->en($this->plaza)->suspendida()->create();
        $pendiente = Lavanderia::factory()->en($this->plaza)->pendiente()->create();
        $this->comoAdministracion();

        foreach ([$suspendida, $pendiente] as $lavanderia) {
            Livewire::test(EditLavanderia::class, ['record' => $lavanderia->getRouteKey()])
                ->assertActionVisible('darDeBaja')
                ->callAction('darDeBaja')
                ->assertActionHidden('darDeBaja')
                ->assertActionHidden('suspender')
                ->assertActionHidden('levantarSuspension');

            $this->assertSame(Lavanderia::BAJA, $lavanderia->fresh()->estado);
        }

        $this->expectException(TransicionNoPermitida::class);
        app(EstadoDeLaLavanderia::class)->darDeBaja($suspendida->fresh());
    }

    /**
     * Un pedido de la cuenta con la lavandería de prepararPedido(), confirmado, aceptado y llevado por la lavandería
     * hasta En proceso de entrega; "Entregado" lo pone el repartidor (H8), aquí con su ayuda de prueba.
     */
    private function pedidoEntregadoDe(Usuario $cuenta): Pedido
    {
        $pedido = $this->pedidoAceptadoDe($cuenta);

        foreach ([EstadoPedido::RECOLECTADO, EstadoPedido::EN_PROCESO, EstadoPedido::LISTO, EstadoPedido::EN_ENTREGA] as $codigo) {
            $this->marcarComoLaLavanderia($pedido, $codigo);
        }
        $this->entregarComoElRepartidor($pedido);

        return $pedido->fresh();
    }
}
