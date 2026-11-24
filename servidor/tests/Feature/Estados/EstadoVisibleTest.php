<?php

namespace Tests\Feature\Estados;

use App\Models\EstadoPedido;
use App\Models\Pedido;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Estados\Soporte\EstadosDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * Lo que la consulta del usuario, GET /api/pedidos/{id_publico}, entrega para pintar el seguimiento: el estado actual,
 * con el nombre que ve el usuario, y los cinco estados del contrato en su orden: Confirmado, Recolectado, En proceso,
 * Listo y Entregado (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo A #12; ADR-0021).
 *
 * El pedido entra al flujo en Confirmado cuando la pasarela confirma su pago (C-188): aquí lo confirma la operación de
 * confirmar, como lo hará el cobro. La lavandería lo acepta y lo mueve con sus botones, la operación real
 * (EstadosDePrueba::aceptarComoLaLavanderia() y marcarComoLaLavanderia(); estados-del-pedido, tarea 2.1).
 *
 * Escenarios: estados-E01 y estados-E11 · Spec: openspec/changes/estados-del-pedido/specs/estados/spec.md · Tarea 1.1
 */
class EstadoVisibleTest extends TestCase
{
    use AgendaDePrueba;
    use EstadosDePrueba;
    use LavanderiasDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    /** Los cinco estados que ve el usuario, en el orden del contrato (Módulo A #12): código y nombre. */
    private const LOS_CINCO = [
        ['codigo' => 'confirmado', 'nombre' => 'Confirmado'],
        ['codigo' => 'recolectado', 'nombre' => 'Recolectado'],
        ['codigo' => 'en_proceso', 'nombre' => 'En proceso'],
        ['codigo' => 'listo', 'nombre' => 'Listo'],
        ['codigo' => 'entregado', 'nombre' => 'Entregado'],
    ];

    private Usuario $cuenta;

    private Pedido $pedido;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();

        $this->cuenta = $this->cuentaDeUsuario();
        $this->pedido = $this->pedidoConfirmadoDe($this->cuenta);
    }

    #[TestDox('estados-E01: la api entrega el pedido recién confirmado en Confirmado como estado actual, con los cinco estados en el orden del contrato')]
    public function test_estados_e01_la_api_entrega_el_pedido_recien_confirmado_en_confirmado(): void
    {
        $respuesta = $this->consultarPedido($this->cuenta, $this->pedido->id_publico)->assertOk();

        // El pedido tiene su folio: su pago ya está acreditado.
        $respuesta->assertJsonPath('pedido.folio', $this->pedido->folio);
        $this->assertNotNull($this->pedido->folio);

        $respuesta->assertJsonPath('pedido.estado', [
            'codigo' => EstadoPedido::CONFIRMADO,
            'nombre' => 'Confirmado',
            'visible_usuario' => true,
        ]);
        $this->assertLosCinco($respuesta);

        // El estado actual es el primero de los cinco.
        $this->assertSame(0, $this->pasoActual($respuesta));
    }

    #[TestDox('estados-E01: los cinco estados son solo los del contrato: el estado previo al pago no está entre ellos, ni mientras el pedido espera su pago')]
    public function test_estados_e01_los_cinco_estados_son_solo_los_del_contrato(): void
    {
        $porPagar = $this->aceptarResumen($this->cuenta, $this->resumen($this->direccionDe($this->cuenta)))
            ->assertCreated()
            ->json('pedido.id_publico');

        $respuesta = $this->consultarPedido($this->cuenta, $porPagar)->assertOk();

        $respuesta->assertJsonPath('pedido.estado.codigo', EstadoPedido::POR_PAGAR);
        $respuesta->assertJsonPath('pedido.estado.visible_usuario', false);
        $this->assertLosCinco($respuesta);
        $this->assertNull($this->pasoActual($respuesta));
    }

    #[TestDox('estados-E11: cuando la lavandería marca Recolectado, En proceso o Listo, la siguiente consulta del usuario devuelve ese mismo estado, con el mismo nombre')]
    public function test_estados_e11_los_botones_con_el_mismo_nombre_se_ven_igual(): void
    {
        // El botón de la lavandería (Módulo B #3), y el estado y el paso que debe ver el usuario.
        $botones = [
            'Recolectado' => [EstadoPedido::RECOLECTADO, 1],
            'En proceso' => [EstadoPedido::EN_PROCESO, 2],
            'Listo' => [EstadoPedido::LISTO, 3],
        ];

        $this->aceptarComoLaLavanderia($this->pedido);

        foreach ($botones as $boton => [$codigo, $paso]) {
            $this->marcarComoLaLavanderia($this->pedido, $codigo);

            $respuesta = $this->consultarPedido($this->cuenta, $this->pedido->id_publico)->assertOk();

            $respuesta->assertJsonPath('pedido.estado', [
                'codigo' => $codigo,
                'nombre' => $boton,
                'visible_usuario' => true,
            ]);
            // La lista no cambia, y el paso que la app pinta como actual lleva el nombre del botón.
            $this->assertLosCinco($respuesta);
            $this->assertSame($paso, $this->pasoActual($respuesta), $boton);
            $respuesta->assertJsonPath("pedido.seguimiento.{$paso}.nombre", $boton);
        }
    }

    #[TestDox('estados-E01 y estados-E11: el estado y los cinco estados solo los recibe la cuenta que hizo el pedido; otra cuenta o la lavandería no reciben ningún dato')]
    public function test_estados_e01_y_e11_el_seguimiento_solo_lo_recibe_la_cuenta_que_hizo_el_pedido(): void
    {
        $this->aceptarComoLaLavanderia($this->pedido);
        $this->marcarComoLaLavanderia($this->pedido, EstadoPedido::RECOLECTADO);

        $otra = $this->consultarPedido($this->cuentaDeUsuario(), $this->pedido->id_publico);
        $otra->assertNotFound()->assertExactJson(['message' => 'No existe el pedido.']);

        // La consulta del usuario no es de la lavandería: sus pedidos llegan con pedidos-de-la-lavanderia. Con el modo de
        // depuración, el 403 trae además la traza del error: se comprueba el mensaje y que no venga ningún dato.
        $lavanderia = $this->consultarPedido($this->cuentaDeLaLavanderia($this->pedido), $this->pedido->id_publico);
        $lavanderia->assertForbidden()
            ->assertJsonPath('message', 'Esta cuenta no tiene acceso.')
            ->assertJsonMissingPath('pedido');

        foreach ([$otra, $lavanderia] as $respuesta) {
            foreach ([$this->pedido->id_publico, $this->pedido->folio] as $dato) {
                $this->assertStringNotContainsStringIgnoringCase($dato, $respuesta->getContent());
            }
        }
    }

    /**
     * La posición, en la lista de los cinco, del estado actual: la que la app pinta como actual, con los anteriores
     * cumplidos y los siguientes pendientes (estados-E02). null si el estado actual no es uno de los cinco.
     */
    private function pasoActual(TestResponse $respuesta): ?int
    {
        $paso = array_search(
            $respuesta->json('pedido.estado.codigo'),
            array_column($respuesta->json('pedido.seguimiento') ?? [], 'codigo'),
            true,
        );

        return $paso === false ? null : $paso;
    }

    /**
     * El seguimiento trae los cinco estados en el orden del contrato, con su código y su nombre. La hora de cada uno es
     * de estados-E24 (HoraDelSeguimientoTest).
     */
    private function assertLosCinco(TestResponse $respuesta): void
    {
        $this->assertSame(
            self::LOS_CINCO,
            array_map(
                fn (array $paso): array => ['codigo' => $paso['codigo'], 'nombre' => $paso['nombre']],
                $respuesta->json('pedido.seguimiento') ?? [],
            ),
        );
    }
}
