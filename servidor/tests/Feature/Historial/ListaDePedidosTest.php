<?php

namespace Tests\Feature\Historial;

use App\Models\EstadoPedido;
use App\Models\Pedido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Estados\Soporte\EstadosDePrueba;
use Tests\Feature\Historial\Soporte\HistorialDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * La lista de pedidos del usuario, GET /api/pedidos: cada pedido con su folio, su fecha, su lavandería y su estado, y
 * solo los que hizo la cuenta de la sesión (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo A #13;
 * NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3, Separación por tipo de usuario).
 *
 * Entran solo los pedidos confirmados: uno que espera su pago no entró al flujo (C-188; ADR-0021) y no tiene folio ni
 * estado que nombrar (design.md, decisión 4). La fecha es la de la recolección en la hora de la plaza (design.md,
 * decisión 1). Quién ve qué pedido lo dice el componente de autorización (ADR-0019).
 *
 * Escenarios: historial-E01 y historial-E02 · Spec: openspec/changes/historial-y-calificacion/specs/historial/spec.md ·
 * Tarea 1.1
 */
class ListaDePedidosTest extends TestCase
{
    use AgendaDePrueba;
    use EstadosDePrueba;
    use HistorialDePrueba;
    use LavanderiasDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();
    }

    #[TestDox('historial-E01: el usuario con un pedido en proceso y dos entregados ve los tres, cada uno con su folio, su fecha, su lavandería y su estado, del más reciente al más antiguo')]
    public function test_historial_e01_el_usuario_ve_sus_tres_pedidos_con_folio_fecha_lavanderia_y_estado(): void
    {
        $cuenta = $this->cuentaDeUsuario();

        $primero = $this->entregar($this->pedidoConfirmadoEl($cuenta, '2026-12-03'));
        $segundo = $this->entregar($this->pedidoConfirmadoEl($cuenta, '2026-12-05'));
        $enProceso = $this->pedidoConfirmadoEl($cuenta, '2026-12-08');
        $this->aceptarComoLaLavanderia($enProceso);
        $this->marcarComoLaLavanderia($enProceso, EstadoPedido::RECOLECTADO);
        $this->marcarComoLaLavanderia($enProceso, EstadoPedido::EN_PROCESO);

        $respuesta = $this->pedirHistorial($cuenta)->assertOk();

        $entregado = ['codigo' => EstadoPedido::ENTREGADO, 'nombre' => 'Entregado', 'visible_usuario' => true];

        $respuesta->assertExactJson(['pedidos' => [
            $this->elementoDe($enProceso, '2026-12-08', [
                'codigo' => EstadoPedido::EN_PROCESO,
                'nombre' => 'En proceso',
                'visible_usuario' => true,
            ]),
            $this->elementoDe($segundo, '2026-12-05', $entregado),
            $this->elementoDe($primero, '2026-12-03', $entregado),
        ]]);

        // Los tres tienen su folio: el pago de cada uno ya está acreditado.
        foreach ([$primero, $segundo, $enProceso] as $pedido) {
            $this->assertNotNull($pedido->folio);
        }
    }

    #[TestDox('historial-E01: dos pedidos con la misma recolección salen del más nuevo al más viejo')]
    public function test_historial_e01_dos_pedidos_con_la_misma_recoleccion_salen_del_mas_nuevo_al_mas_viejo(): void
    {
        $cuenta = $this->cuentaDeUsuario();

        $antes = $this->pedidoConfirmadoEl($cuenta, '2026-12-03');
        $despues = $this->pedidoConfirmadoEl($cuenta, '2026-12-03');

        $this->assertTrue($antes->ventana_inicio->equalTo($despues->ventana_inicio));
        $this->assertSame(
            [$despues->id_publico, $antes->id_publico],
            $this->idsDe($this->pedirHistorial($cuenta)->assertOk()),
        );
    }

    #[TestDox('historial-E01: la fecha de cada pedido es la de su recolección en la hora de la plaza, la misma que muestra su resumen, aunque en UTC ya sea el día siguiente')]
    public function test_historial_e01_la_fecha_es_la_de_la_recoleccion_en_la_hora_de_la_plaza(): void
    {
        $cuenta = $this->cuentaDeUsuario();

        // 19:00 del 3 de diciembre en la hora de la plaza (UTC-6) es la 01:00 del 4 de diciembre en UTC.
        $pedido = $this->pedidoConfirmadoEl($cuenta, '2026-12-03', '19:00', $this->lavanderiaDeNoche());
        $this->assertSame('2026-12-04', $pedido->ventana_inicio->utc()->toDateString());

        $this->pedirHistorial($cuenta)->assertOk()->assertJsonPath('pedidos.0.fecha', '2026-12-03');
        $this->consultarPedido($cuenta, $pedido->id_publico)->assertOk()->assertJsonPath('pedido.ventana.fecha', '2026-12-03');
    }

    #[TestDox('historial-E01: un pedido que espera su pago no aparece en la lista: no entró al flujo y no tiene folio')]
    public function test_historial_e01_un_pedido_que_espera_su_pago_no_aparece(): void
    {
        $cuenta = $this->cuentaDeUsuario();

        $confirmado = $this->pedidoConfirmadoEl($cuenta, '2026-12-03');
        $porPagar = $this->pedidoPorPagarEl($cuenta, '2026-12-05');
        $this->assertNull($porPagar->confirmado_en);

        $respuesta = $this->pedirHistorial($cuenta)->assertOk();

        $this->assertSame([$confirmado->id_publico], $this->idsDe($respuesta));
        $this->assertStringNotContainsStringIgnoringCase($porPagar->id_publico, $respuesta->getContent());
    }

    #[TestDox('historial-E01: el usuario sin pedidos recibe la lista vacía')]
    public function test_historial_e01_el_usuario_sin_pedidos_recibe_la_lista_vacia(): void
    {
        $this->pedirHistorial($this->cuentaDeUsuario())->assertOk()->assertExactJson(['pedidos' => []]);
    }

    #[TestDox('historial-E02: con dos usuarios, cada uno con sus pedidos, la lista de cada uno trae solo los suyos y ningún dato de los del otro')]
    public function test_historial_e02_cada_usuario_recibe_solo_sus_pedidos(): void
    {
        $ana = $this->cuentaDeUsuario();
        $beto = $this->cuentaDeUsuario();

        $deAna = [
            $this->pedidoConfirmadoEl($ana, '2026-12-03'),
            $this->entregar($this->pedidoConfirmadoEl($ana, '2026-12-05')),
        ];
        $deBeto = [
            $this->pedidoConfirmadoEl($beto, '2026-12-04'),
            $this->entregar($this->pedidoConfirmadoEl($beto, '2026-12-06')),
        ];

        $deLaPrimera = $this->pedirHistorial($ana)->assertOk();
        $deLaSegunda = $this->pedirHistorial($beto)->assertOk();

        $this->assertSame([$deAna[1]->id_publico, $deAna[0]->id_publico], $this->idsDe($deLaPrimera));
        $this->assertSame([$deBeto[1]->id_publico, $deBeto[0]->id_publico], $this->idsDe($deLaSegunda));

        $this->assertSinDatosDe($deBeto, $deLaPrimera->getContent());
        $this->assertSinDatosDe($deAna, $deLaSegunda->getContent());
    }

    #[TestDox('historial-E02: sin sesión, o con la cuenta de la lavandería, la lista no se entrega y la respuesta no trae ningún pedido')]
    public function test_historial_e02_sin_sesion_o_con_la_cuenta_de_la_lavanderia_no_se_entrega(): void
    {
        $pedido = $this->pedidoConfirmadoEl($this->cuentaDeUsuario(), '2026-12-03');

        $sinSesion = $this->pedirSinSesion('/api/pedidos')->assertUnauthorized();

        // La lista es del usuario: los pedidos de la lavandería llegan con pedidos-de-la-lavanderia. Con el modo de
        // depuración, el 403 trae además la traza del error: se comprueba el mensaje y que no venga ningún dato.
        $deLaLavanderia = $this->pedirHistorial($this->cuentaDeLaLavanderia($pedido))
            ->assertForbidden()
            ->assertJsonPath('message', 'Esta cuenta no tiene acceso.')
            ->assertJsonMissingPath('pedidos');

        foreach ([$sinSesion, $deLaLavanderia] as $respuesta) {
            $this->assertSinDatosDe([$pedido], $respuesta->getContent());
        }
    }

    /**
     * Un pedido como sale en la lista.
     *
     * @param  array{codigo: string, nombre: string, visible_usuario: bool}  $estado
     * @return array<string, mixed>
     */
    private function elementoDe(Pedido $pedido, string $fecha, array $estado): array
    {
        return [
            'id_publico' => $pedido->id_publico,
            'folio' => $pedido->folio,
            'fecha' => $fecha,
            'lavanderia' => [
                'id_publico' => $pedido->lavanderia->id_publico,
                'nombre' => $pedido->lavanderia->nombre,
            ],
            'estado' => $estado,
            // Sin calificar todavía (historial-E06).
            'calificacion' => null,
        ];
    }

    /**
     * Ni el id_publico ni el folio de esos pedidos aparecen en la respuesta.
     *
     * @param  list<Pedido>  $pedidos
     */
    private function assertSinDatosDe(array $pedidos, string $contenido): void
    {
        foreach ($pedidos as $pedido) {
            $this->assertStringNotContainsStringIgnoringCase($pedido->id_publico, $contenido);
            $this->assertStringNotContainsStringIgnoringCase($pedido->folio, $contenido);
        }
    }
}
