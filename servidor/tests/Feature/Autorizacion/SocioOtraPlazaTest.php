<?php

namespace Tests\Feature\Autorizacion;

use App\Models\EstadoPedido;
use App\Models\Lavanderia;
use App\Models\Pedido;
use App\Models\Plaza;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\Autorizacion\Soporte\RutasDelModuloB;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Estados\Soporte\EstadosDePrueba;
use Tests\Feature\PedidosLavanderia\Soporte\PedidosDeLaLavanderiaDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * La plaza separa a los socios: una lavandería de la plaza P1 no ve un pedido de la plaza P2, aunque conozca su
 * id_publico (ADR-0003; NSV-2026-001-v8.pdf:4:Cláusula I). Lo decide App\Autorizacion\Autorizacion, con la tenencia y la
 * plaza de la lavandería.
 *
 * El repartidor de la plaza P1 se agrega con repartidor (H8), cuando nazcan repartidores y pedido_asignaciones
 * (design.md, "Tablas y migración").
 *
 * Escenario: autorizacion-E12 · Spec: openspec/changes/autorizacion-por-rol/specs/autorizacion/spec.md · Tarea 3.1
 */
class SocioOtraPlazaTest extends TestCase
{
    use AgendaDePrueba;
    use EstadosDePrueba;
    use LavanderiasDePrueba;
    use PedidosDeLaLavanderiaDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;
    use RutasDelModuloB;

    private Plaza $plazaDos;

    private Lavanderia $deLaPlazaDos;

    /** Un pedido de la plaza P2, aceptado por su lavandería. */
    private Pedido $pedidoDeLaPlazaDos;

    protected function setUp(): void
    {
        parent::setUp();

        // La plaza P1 es la de prepararPedido(); la P2, otra plaza de prueba con su zona al este, en el océano Pacífico.
        $this->prepararPedido();
        $this->plazaDos = $this->plazaConAgenda('Plaza de prueba 2', anticipacionMinutos: 0, diasHaciaAdelante: 3);
        $this->zonaDePrueba($this->plazaDos, 'Centro 2', $this->cuadrado(-118.0, 10.0));
        $this->deLaPlazaDos = $this->otraLavanderia($this->plazaDos);
        $this->pedidoDeLaPlazaDos = $this->pedidoConfirmadoEn(
            $this->deLaPlazaDos,
            $this->cuentaDeUsuario(),
            latitud: 10.5,
            longitud: -117.5,
        );
        $this->aceptarComoLaLavanderia($this->pedidoDeLaPlazaDos);
    }

    #[TestDox('autorizacion-E12: una lavandería de la plaza P1 pide un pedido de la plaza P2 por su id_publico en cada ruta del Módulo B: ninguna le devuelve un dato, y el pedido no cambia')]
    public function test_autorizacion_e12_una_lavanderia_no_ve_un_pedido_de_otra_plaza(): void
    {
        $this->assertSame($this->plazaDos->getKey(), $this->pedidoDeLaPlazaDos->id_plazas);
        $eventos = $this->eventosDe($this->pedidoDeLaPlazaDos);

        foreach ($this->rutasDeLaLavanderiaConPedido() as $nombre => $ruta) {
            $respuesta = $this->llamarConElPedido($this->lavanderia->administrador, $nombre, $ruta, $this->pedidoDeLaPlazaDos->id_publico);

            $respuesta->assertNotFound();
            $this->assertSame(['message' => 'No existe el pedido.'], $respuesta->json(), $nombre);
            foreach ([$this->pedidoDeLaPlazaDos->id_publico, $this->pedidoDeLaPlazaDos->folio] as $dato) {
                $this->assertStringNotContainsStringIgnoringCase($dato, $respuesta->getContent(), $nombre);
            }
        }

        $this->assertPedidoSinCambio($this->pedidoDeLaPlazaDos, EstadoPedido::ACEPTADO, $eventos);
    }

    #[TestDox('autorizacion-E12: la lista de la lavandería de la plaza P1 no trae el pedido de la plaza P2, y la lavandería de P2 sí lo ve')]
    public function test_autorizacion_e12_la_lista_no_trae_pedidos_de_otra_plaza(): void
    {
        $this->assertStringNotContainsString(
            $this->pedidoDeLaPlazaDos->id_publico,
            $this->listaDeLaLavanderia($this->lavanderia->administrador)->assertOk()->getContent(),
        );

        // Su propia lavandería sí lo ve: la prueba no pasa en vacío.
        $this->assertSame(
            [$this->pedidoDeLaPlazaDos->id_publico],
            $this->idsDeLaLista($this->listaDeLaLavanderia($this->deLaPlazaDos->administrador)->assertOk()),
        );
        $this->pedidoComoLavanderia($this->deLaPlazaDos->administrador, $this->pedidoDeLaPlazaDos->id_publico)->assertOk();
    }
}
