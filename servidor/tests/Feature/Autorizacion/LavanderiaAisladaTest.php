<?php

namespace Tests\Feature\Autorizacion;

use App\Models\EstadoPedido;
use App\Models\Lavanderia;
use App\Models\Pedido;
use App\Models\Usuario;
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
 * Una lavandería no ve los datos de otra: "Un socio lavandería no puede consultar los datos de otro socio"
 * (NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3). Se prueba con las primeras rutas del Módulo B, las de sus pedidos
 * (pedidos-de-la-lavanderia y estados-del-pedido), recorridas desde la tabla de rutas.
 *
 * El historial y las estadísticas de la lavandería (historial-de-la-lavanderia, F-B04 y F-B05) tampoco traen ni cuentan
 * los pedidos de la otra. El perfil público de una lavandería es del Módulo A.
 *
 * Escenario: autorizacion-E09 · Spec: openspec/changes/autorizacion-por-rol/specs/autorizacion/spec.md · Tarea 2.3
 */
class LavanderiaAisladaTest extends TestCase
{
    use AgendaDePrueba;
    use EstadosDePrueba;
    use LavanderiasDePrueba;
    use PedidosDeLaLavanderiaDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;
    use RutasDelModuloB;

    private Usuario $cuenta;

    /** La segunda lavandería, de la misma plaza que la de prepararPedido(). */
    private Lavanderia $segunda;

    /** @var list<Pedido> Los pedidos de la segunda: uno por aceptar y uno aceptado. */
    private array $deLaSegunda;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();

        $this->cuenta = $this->cuentaDeUsuario();
        $this->segunda = $this->otraLavanderia();
        $this->deLaSegunda = [
            $this->pedidoConfirmadoEn($this->segunda, $this->cuenta),
            $this->pedidoConfirmadoEn($this->segunda, $this->cuentaDeUsuario()),
        ];
        $this->aceptarComoLaLavanderia($this->deLaSegunda[1]);
    }

    #[TestDox('autorizacion-E09: con dos lavanderías de la misma plaza, cada una con pedidos, la lista de la primera no trae pedidos de la segunda, y la de la segunda trae los suyos')]
    public function test_autorizacion_e09_la_lista_de_la_primera_no_trae_pedidos_de_la_segunda(): void
    {
        $propio = $this->pedidoConfirmadoDe($this->cuenta);

        $primera = $this->listaDeLaLavanderia($this->lavanderia->administrador)->assertOk();
        $segunda = $this->listaDeLaLavanderia($this->segunda->administrador)->assertOk();

        $this->assertSame([$propio->id_publico], $this->idsDeLaLista($primera));
        $this->assertSame(
            array_map(fn (Pedido $pedido): string => $pedido->id_publico, $this->deLaSegunda),
            $this->idsDeLaLista($segunda),
        );
        $this->assertSinDatosDeLaSegunda($primera->getContent());
    }

    #[TestDox('autorizacion-E09: la primera lavandería pide por cada ruta del Módulo B un pedido de la segunda: ninguna devuelve un dato, todas responden que no existe, y los pedidos de la segunda no cambian')]
    public function test_autorizacion_e09_ninguna_ruta_del_modulo_b_le_da_un_pedido_de_la_segunda(): void
    {
        $rutas = $this->rutasDeLaLavanderiaConPedido();
        // La consulta, aceptar, rechazar y el botón del estado, por lo menos: la prueba no pasa en vacío.
        $this->assertGreaterThanOrEqual(4, count($rutas));
        $estados = array_map(fn (Pedido $pedido): string => $pedido->fresh()->estado->codigo, $this->deLaSegunda);

        foreach ($rutas as $nombre => $ruta) {
            foreach ($this->deLaSegunda as $pedido) {
                $respuesta = $this->llamarConElPedido($this->lavanderia->administrador, $nombre, $ruta, $pedido->id_publico);

                $respuesta->assertNotFound();
                $this->assertSame(['message' => 'No existe el pedido.'], $respuesta->json(), $nombre);
                $this->assertSinDatosDeLaSegunda($respuesta->getContent(), $nombre);
            }
        }

        $this->assertSame(
            $estados,
            array_map(fn (Pedido $pedido): string => $pedido->fresh()->estado->codigo, $this->deLaSegunda),
        );
        $this->assertSame([EstadoPedido::CONFIRMADO, EstadoPedido::ACEPTADO], $estados);
    }

    #[TestDox('autorizacion-E09: el historial y las estadísticas de la primera lavandería no traen ni cuentan los pedidos entregados de la segunda, y los de la segunda sí')]
    public function test_autorizacion_e09_el_historial_y_las_estadisticas_no_traen_pedidos_de_la_segunda(): void
    {
        foreach ($this->deLaSegunda as $pedido) {
            if ($pedido->fresh()->estado->codigo === EstadoPedido::CONFIRMADO) {
                $this->aceptarComoLaLavanderia($pedido);
            }

            $this->llevarHastaEntregado($pedido);
        }

        $this->app['auth']->forgetGuards();
        $token = $this->lavanderia->administrador->createToken('app')->plainTextToken;
        $historial = $this->withToken($token)->getJson('/api/lavanderia/historial')->assertOk()->assertExactJson(['pedidos' => []]);
        $estadisticas = $this->withToken($token)->getJson('/api/lavanderia/estadisticas')
            ->assertOk()
            ->assertExactJson(['pedidos_del_dia' => 0, 'pedidos_de_la_semana' => 0]);

        $this->assertSinDatosDeLaSegunda($historial->getContent());
        $this->assertSinDatosDeLaSegunda($estadisticas->getContent());

        // La segunda sí los ve y los cuenta: la prueba no pasa en vacío.
        $this->app['auth']->forgetGuards();
        $deLaSegunda = $this->segunda->administrador->createToken('app')->plainTextToken;
        $this->assertCount(2, $this->withToken($deLaSegunda)->getJson('/api/lavanderia/historial')->assertOk()->json('pedidos'));
        $this->withToken($deLaSegunda)->getJson('/api/lavanderia/estadisticas')
            ->assertOk()
            ->assertExactJson(['pedidos_del_dia' => 2, 'pedidos_de_la_semana' => 2]);
    }

    #[TestDox('autorizacion-E09: la primera lavandería no pide el perfil ni las ventanas de la segunda por las rutas del Módulo A: responden 403 sin datos')]
    public function test_autorizacion_e09_no_pide_el_perfil_de_la_segunda_por_el_modulo_a(): void
    {
        foreach (["/api/lavanderias/{$this->segunda->id_publico}", "/api/lavanderias/{$this->segunda->id_publico}/ventanas"] as $direccion) {
            $this->app['auth']->forgetGuards();
            $respuesta = $this->withToken($this->lavanderia->administrador->createToken('app')->plainTextToken)
                ->getJson($direccion)
                ->assertForbidden();

            $this->assertStringNotContainsString($this->segunda->nombre, $respuesta->getContent());
        }
    }

    private function assertSinDatosDeLaSegunda(string $contenido, string $donde = ''): void
    {
        foreach ($this->deLaSegunda as $pedido) {
            foreach ([$pedido->id_publico, $pedido->folio] as $dato) {
                $this->assertStringNotContainsStringIgnoringCase($dato, $contenido, $donde);
            }
        }
        $this->assertStringNotContainsString($this->segunda->nombre, $contenido, $donde);
    }
}
