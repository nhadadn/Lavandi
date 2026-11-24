<?php

namespace Tests\Feature\Administracion;

use App\Autorizacion\Autorizacion;
use App\Filament\Pages\MonitoreoDePedidos;
use App\Models\EstadoPedido;
use App\Models\Pedido;
use App\Models\Plaza;
use App\Models\Rol;
use App\Models\Usuario;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\AltaLavanderias\Soporte\PanelDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Estados\Soporte\EstadosDePrueba;
use Tests\Feature\PedidosLavanderia\Soporte\PedidosDeLaLavanderiaDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * El monitoreo de pedidos del panel: todos los pedidos activos de todas las plazas en una sola vista, con su folio, su
 * estado y su plaza, que se refresca sola cada 30 segundos mientras está abierta (NSV-2026-001-v8.pdf:19:ANEXO A,
 * Sección A.3, Módulo D #4, "Vista en tiempo real de todos los pedidos activos"; ADR-0007).
 *
 * Pedido activo es el confirmado que todavía no se entrega ni se cancela (design.md, lectura 1): con C-188, uno que
 * espera su pago no está activo. El rechazado sigue activo mientras DD-103 y DD-016 no digan qué pasa con él: está
 * pagado y no se entregó ni se canceló. El mapa de los mismos pedidos lo prueba MapaDelMonitoreoTest (administracion-E09
 * y E10); el cancelado espera DD-016 (administracion-E08).
 *
 * Escenarios: administracion-E06 y administracion-E07 · Spec: openspec/changes/administracion/specs/administracion/
 * spec.md · Tareas 2.1 y 2.2
 */
class MonitoreoDePedidosTest extends TestCase
{
    use AgendaDePrueba;
    use EstadosDePrueba;
    use LavanderiasDePrueba;
    use PanelDePrueba;
    use PedidosDeLaLavanderiaDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();
    }

    #[TestDox('administracion-E06: con un pedido Confirmado en una plaza, uno En proceso en otra plaza y uno Entregado, la administración ve en el monitoreo los dos primeros con su folio, su estado y su plaza, y no ve el entregado')]
    public function test_administracion_e06_ve_los_activos_de_todas_las_plazas_y_no_el_entregado(): void
    {
        $confirmado = $this->pedidoConfirmadoEn($this->lavanderia, $this->cuentaDeUsuario(), '10:00');

        $plazaDos = $this->plazaDos();
        $enProceso = $this->pedidoConfirmadoEn($this->otraLavanderia($plazaDos), $this->cuentaDeUsuario(), '11:00', latitud: 10.5, longitud: -117.5);
        $this->aceptarComoLaLavanderia($enProceso);
        $this->marcarComoLaLavanderia($enProceso, EstadoPedido::RECOLECTADO);
        $this->marcarComoLaLavanderia($enProceso, EstadoPedido::EN_PROCESO);

        $entregado = $this->pedidoConfirmadoEn($this->lavanderia, $this->cuentaDeUsuario(), '12:00');
        $this->aceptarComoLaLavanderia($entregado);
        $this->llevarHastaEntregado($entregado);

        $this->comoAdministracion();

        Livewire::test(MonitoreoDePedidos::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$confirmado, $enProceso])
            ->assertCanNotSeeTableRecords([$entregado])
            ->assertCountTableRecords(2)
            ->assertTableColumnStateSet('folio', $confirmado->folio, $confirmado)
            ->assertTableColumnFormattedStateSet('estado', 'Confirmado', $confirmado)
            ->assertTableColumnStateSet('plaza.nombre', $this->plaza->nombre, $confirmado)
            ->assertTableColumnStateSet('folio', $enProceso->folio, $enProceso)
            ->assertTableColumnFormattedStateSet('estado', 'En proceso', $enProceso)
            ->assertTableColumnStateSet('plaza.nombre', $plazaDos->nombre, $enProceso)
            ->assertSee([$confirmado->folio, $enProceso->folio, $this->plaza->nombre, $plazaDos->nombre])
            ->assertDontSee($entregado->folio);
    }

    #[TestDox('administracion-E06: entran todos los estados entre la confirmación y la entrega, también el rechazado, y nunca uno que espera su pago')]
    public function test_administracion_e06_entran_todos_los_estados_activos_y_no_el_que_espera_su_pago(): void
    {
        $esperados = [];

        foreach ([EstadoPedido::CONFIRMADO, EstadoPedido::ACEPTADO, EstadoPedido::RECOLECTADO, EstadoPedido::EN_PROCESO, EstadoPedido::LISTO, EstadoPedido::EN_ENTREGA] as $hasta) {
            $esperados[$hasta] = $this->pedidoHasta($hasta);
        }

        $rechazado = $this->pedidoConfirmadoDe($this->cuentaDeUsuario());
        $this->rechazarComoLaLavanderia($rechazado);
        $esperados[EstadoPedido::RECHAZADO] = $rechazado;

        $porPagar = $this->pedidoPorPagarEn($this->lavanderia, $this->cuentaDeUsuario());
        $this->comoAdministracion();

        $monitoreo = Livewire::test(MonitoreoDePedidos::class)
            ->assertCanSeeTableRecords(array_values($esperados))
            ->assertCanNotSeeTableRecords([$porPagar])
            ->assertCountTableRecords(count($esperados));

        $nombres = [
            EstadoPedido::CONFIRMADO => 'Confirmado',
            EstadoPedido::ACEPTADO => 'Aceptado',
            EstadoPedido::RECOLECTADO => 'Recolectado',
            EstadoPedido::EN_PROCESO => 'En proceso',
            EstadoPedido::LISTO => 'Listo',
            EstadoPedido::EN_ENTREGA => 'En proceso de entrega',
            EstadoPedido::RECHAZADO => 'Rechazado',
        ];

        foreach ($esperados as $codigo => $pedido) {
            $monitoreo->assertTableColumnFormattedStateSet('estado', $nombres[$codigo], $pedido);
        }
    }

    #[TestDox('administracion-E06: el monitoreo es de solo lectura: no crea, ni edita, ni borra pedidos')]
    public function test_administracion_e06_el_monitoreo_es_de_solo_lectura(): void
    {
        $pedido = $this->pedidoConfirmadoDe($this->cuentaDeUsuario());
        $this->comoAdministracion();

        $this->get('/admin/monitoreo')->assertOk()->assertSee($pedido->folio);

        Livewire::test(MonitoreoDePedidos::class)
            ->assertActionDoesNotExist('create')
            ->assertTableActionDoesNotExist('edit')
            ->assertTableActionDoesNotExist('delete')
            ->assertTableBulkActionDoesNotExist('delete');
    }

    #[TestDox('administracion-E06: una cuenta que no es de administración no abre el monitoreo y el componente no le da ningún pedido; a la administración le da los activos de todas las plazas')]
    public function test_administracion_e06_solo_la_administracion_abre_el_monitoreo(): void
    {
        $pedido = $this->pedidoConfirmadoDe($this->cuentaDeUsuario());
        $autorizacion = app(Autorizacion::class);

        foreach ([Rol::USUARIO, Rol::LAVANDERIA, 'repartidor'] as $rol) {
            $cuenta = Usuario::factory()->conRol($rol)->create();
            $this->actingAs($cuenta, 'web');

            $respuesta = $this->get('/admin/monitoreo')->assertForbidden();
            $this->assertStringNotContainsString($pedido->folio, $respuesta->getContent());
            $this->assertSame(0, $autorizacion->pedidosActivosDelPanel($cuenta)->count(), $rol);
        }

        // La lavandería del pedido tampoco: el monitoreo es del panel, no del Módulo B.
        $this->actingAs($this->lavanderia->administrador, 'web')->get('/admin/monitoreo')->assertForbidden();

        $this->assertSame(
            [$pedido->getKey()],
            $autorizacion->pedidosActivosDelPanel(Usuario::factory()->conRol(Rol::ADMINISTRACION)->create())->pluck('id_pedidos')->all(),
        );
    }

    #[TestDox('administracion-E07: con el monitoreo abierto y un pedido Recolectado, cuando la lavandería lo pasa a En proceso, el siguiente refresco de 30 segundos lo muestra En proceso sin recargar la página')]
    public function test_administracion_e07_el_monitoreo_se_refresca_solo_cada_30_segundos(): void
    {
        $pedido = $this->pedidoHasta(EstadoPedido::RECOLECTADO);
        $this->comoAdministracion();

        $monitoreo = Livewire::test(MonitoreoDePedidos::class)
            ->assertTableColumnFormattedStateSet('estado', 'Recolectado', $pedido);

        // La vista se consulta sola cada 30 segundos (ADR-0007): Livewire la vuelve a pedir sin recargar la página.
        $this->assertSame('30s', $monitoreo->instance()->getTable()->getPollingInterval());
        $monitoreo->assertSeeHtml('wire:poll.30s');

        // La lavandería toca su botón en la app, por la API.
        $this->marcarPedido($this->cuentaDeLaLavanderia($pedido), $pedido->id_publico, EstadoPedido::EN_PROCESO)->assertOk();

        // El refresco: la misma vista abierta vuelve a pedir sus filas.
        $this->volverAlPanel();
        $monitoreo->call('$refresh')
            ->assertTableColumnFormattedStateSet('estado', 'En proceso', $pedido);
    }

    #[TestDox('administracion-E07: un pedido que se entrega sale del monitoreo en el siguiente refresco, y uno que se confirma entra')]
    public function test_administracion_e07_en_el_refresco_sale_el_entregado_y_entra_el_nuevo(): void
    {
        $enEntrega = $this->pedidoHasta(EstadoPedido::EN_ENTREGA);
        $this->comoAdministracion();

        $monitoreo = Livewire::test(MonitoreoDePedidos::class)->assertCanSeeTableRecords([$enEntrega]);

        $this->entregarComoElRepartidor($enEntrega);
        $nuevo = $this->pedidoConfirmadoDe($this->cuentaDeUsuario());

        $this->volverAlPanel();
        $monitoreo->call('$refresh')
            ->assertCanNotSeeTableRecords([$enEntrega])
            ->assertCanSeeTableRecords([$nuevo]);
    }

    /**
     * Un pedido nuevo de una cuenta nueva, llevado por la lavandería hasta ese estado, con sus botones.
     */
    private function pedidoHasta(string $estado): Pedido
    {
        $pedido = $this->pedidoConfirmadoDe($this->cuentaDeUsuario());

        if ($estado === EstadoPedido::CONFIRMADO) {
            return $pedido;
        }

        $this->aceptarComoLaLavanderia($pedido);

        foreach ([EstadoPedido::RECOLECTADO, EstadoPedido::EN_PROCESO, EstadoPedido::LISTO, EstadoPedido::EN_ENTREGA] as $boton) {
            if ($pedido->fresh()->estado->codigo === $estado) {
                break;
            }

            $this->marcarComoLaLavanderia($pedido, $boton);
        }

        $this->assertSame($estado, $pedido->fresh()->estado->codigo);

        return $pedido->fresh();
    }

    /**
     * La sesión web de la administración otra vez, después de una petición de la app que la olvidó.
     */
    private function volverAlPanel(): void
    {
        $this->app['auth']->forgetGuards();
        Filament::setCurrentPanel('admin');
        $this->actingAs(Usuario::query()->whereHas('roles', fn ($roles) => $roles->where('codigo', Rol::ADMINISTRACION))->firstOrFail(), 'web');
    }

    /**
     * Otra plaza de prueba con su agenda y su zona al este de la de prepararPedido(), en el océano Pacífico.
     */
    private function plazaDos(): Plaza
    {
        $plaza = $this->plazaConAgenda('Plaza de prueba 2', anticipacionMinutos: 0, diasHaciaAdelante: 3);
        $this->zonaDePrueba($plaza, 'Centro 2', $this->cuadrado(-118.0, 10.0));

        return $plaza;
    }
}
