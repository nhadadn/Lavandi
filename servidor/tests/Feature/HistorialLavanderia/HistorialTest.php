<?php

namespace Tests\Feature\HistorialLavanderia;

use App\Models\EstadoPedido;
use App\Models\Pedido;
use App\Models\Plaza;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Estados\Soporte\EstadosDePrueba;
use Tests\Feature\Historial\Soporte\HistorialDePrueba;
use Tests\Feature\HistorialLavanderia\Soporte\HistorialDeLaLavanderiaDePrueba;
use Tests\Feature\PedidosLavanderia\Soporte\PedidosDeLaLavanderiaDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * El historial de la lavandería, GET /api/lavanderia/historial: sus pedidos completados, cada uno con su folio y la
 * fecha en que se entregó, y solo los suyos, de su plaza (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo B #4;
 * NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3, Separación por tipo de usuario).
 *
 * "Completado" es Entregado, y la fecha es la del paso a Entregado en la hora de la plaza (design.md, decisiones 1 y 3).
 * El monto y la calificación esperan DD-013, DD-020, DD-012 y DD-052 (historial-lavanderia-E03 y E04). Quién ve qué
 * pedido lo dice el componente de autorización (ADR-0019): uno ajeno responde igual que uno que no existe.
 *
 * Escenarios: historial-lavanderia-E01 e historial-lavanderia-E02 · Spec:
 * openspec/changes/historial-de-la-lavanderia/specs/historial-lavanderia/spec.md · Tarea 1.1
 */
class HistorialTest extends TestCase
{
    use AgendaDePrueba;
    use EstadosDePrueba;
    use HistorialDeLaLavanderiaDePrueba;
    use HistorialDePrueba;
    use LavanderiasDePrueba;
    use PedidosDeLaLavanderiaDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    private const NO_EXISTE = ['message' => 'No existe el pedido.'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();
    }

    #[TestDox('historial-lavanderia-E01: la lavandería con dos pedidos entregados y uno en proceso ve los dos entregados, cada uno con su folio y la fecha en que se entregó, del más reciente al más antiguo, y no ve el que sigue en proceso')]
    public function test_historial_lavanderia_e01_ve_sus_entregados_con_folio_y_fecha_y_no_el_que_sigue_en_proceso(): void
    {
        $cuenta = $this->cuentaDeUsuario();

        $primero = $this->entregarEl($this->pedidoConfirmadoEl($cuenta, '2026-12-01'), '2026-12-01 13:00');
        $segundo = $this->entregarEl($this->pedidoConfirmadoEl($cuenta, '2026-12-02'), '2026-12-02 12:45');
        $enProceso = $this->pedidoConfirmadoEl($cuenta, '2026-12-03');
        $this->aceptarComoLaLavanderia($enProceso);
        $this->marcarComoLaLavanderia($enProceso, EstadoPedido::RECOLECTADO);
        $this->marcarComoLaLavanderia($enProceso, EstadoPedido::EN_PROCESO);

        $respuesta = $this->historialDeLaLavanderia($this->lavanderia->administrador)->assertOk();

        // 12:45 y 13:00 en la hora de la plaza (UTC-6) son 18:45 y 19:00 en UTC.
        $respuesta->assertExactJson(['pedidos' => [
            ['id_publico' => $segundo->id_publico, 'folio' => $segundo->folio, 'entregado_en' => '2026-12-02T18:45:00Z', 'fecha' => '2026-12-02'],
            ['id_publico' => $primero->id_publico, 'folio' => $primero->folio, 'entregado_en' => '2026-12-01T19:00:00Z', 'fecha' => '2026-12-01'],
        ]]);

        $this->assertNotNull($primero->folio);
        $this->assertStringNotContainsStringIgnoringCase($enProceso->id_publico, $respuesta->getContent());
        $this->assertStringNotContainsStringIgnoringCase($enProceso->folio, $respuesta->getContent());
    }

    #[TestDox('historial-lavanderia-E01: la fecha es la de la entrega en la hora de la plaza, aunque en UTC ya sea el día siguiente, y no la de la recolección')]
    public function test_historial_lavanderia_e01_la_fecha_es_la_de_la_entrega_en_la_hora_de_la_plaza(): void
    {
        // Se recolecta el 1 de diciembre y se entrega el 3 a las 19:30 de la plaza, que en UTC es la 01:30 del 4.
        $pedido = $this->entregarEl($this->pedidoConfirmadoEl($this->cuentaDeUsuario(), '2026-12-01'), '2026-12-03 19:30');

        $this->historialDeLaLavanderia($this->lavanderia->administrador)
            ->assertOk()
            ->assertJsonPath('pedidos.0.id_publico', $pedido->id_publico)
            ->assertJsonPath('pedidos.0.entregado_en', '2026-12-04T01:30:00Z')
            ->assertJsonPath('pedidos.0.fecha', '2026-12-03');
    }

    #[TestDox('historial-lavanderia-E01: no entran el que espera su pago, el que espera respuesta, el rechazado ni el que va en camino; solo los entregados')]
    public function test_historial_lavanderia_e01_solo_entran_los_entregados(): void
    {
        $cuenta = $this->cuentaDeUsuario();

        $entregado = $this->entregarEl($this->pedidoConfirmadoEl($cuenta, '2026-12-01'), '2026-12-01 13:00');
        $porPagar = $this->pedidoPorPagarEl($cuenta, '2026-12-02');
        $porAceptar = $this->pedidoConfirmadoEl($cuenta, '2026-12-02', '10:00');
        $rechazado = $this->pedidoConfirmadoEl($cuenta, '2026-12-02', '11:00');
        $this->rechazarComoLaLavanderia($rechazado);
        $enCamino = $this->pedidoConfirmadoEl($cuenta, '2026-12-02', '12:00');
        $this->aceptarComoLaLavanderia($enCamino);
        foreach ([EstadoPedido::RECOLECTADO, EstadoPedido::EN_PROCESO, EstadoPedido::LISTO, EstadoPedido::EN_ENTREGA] as $boton) {
            $this->marcarComoLaLavanderia($enCamino, $boton);
        }

        $respuesta = $this->historialDeLaLavanderia($this->lavanderia->administrador)->assertOk();

        $this->assertSame([$entregado->id_publico], $this->idsDeLaLista($respuesta));
        foreach ([$porPagar, $porAceptar, $rechazado, $enCamino] as $fuera) {
            $this->assertStringNotContainsStringIgnoringCase($fuera->id_publico, $respuesta->getContent());
        }
    }

    #[TestDox('historial-lavanderia-E01: la lavandería sin pedidos entregados recibe la lista vacía')]
    public function test_historial_lavanderia_e01_sin_entregados_la_lista_llega_vacia(): void
    {
        $this->pedidoConfirmadoDe($this->cuentaDeUsuario());

        $this->historialDeLaLavanderia($this->lavanderia->administrador)->assertOk()->assertExactJson(['pedidos' => []]);
    }

    #[TestDox('historial-lavanderia-E02: un pedido entregado de otra lavandería de la misma plaza y uno de otra plaza no aparecen en su historial, y la API no le entrega ninguno por su identificador; cada una sí ve el suyo')]
    public function test_historial_lavanderia_e02_no_ve_pedidos_de_otra_lavanderia_ni_de_otra_plaza(): void
    {
        $propio = $this->entregarEl($this->pedidoConfirmadoEn($this->lavanderia, $this->cuentaDeUsuario()), '2026-12-03 12:30');

        $this->elRelojEn('2026-12-03 09:30');
        $vecina = $this->otraLavanderia();
        $deLaVecina = $this->entregarEl($this->pedidoConfirmadoEn($vecina, $this->cuentaDeUsuario()), '2026-12-03 12:40');

        $this->elRelojEn('2026-12-03 09:30');
        $otraPlaza = $this->plazaDos();
        $lejana = $this->otraLavanderia($otraPlaza);
        $deOtraPlaza = $this->entregarEl(
            $this->pedidoConfirmadoEn($lejana, $this->cuentaDeUsuario(), latitud: 10.5, longitud: -117.5),
            '2026-12-03 12:50',
        );
        $this->assertSame($otraPlaza->getKey(), $deOtraPlaza->id_plazas);

        $historial = $this->historialDeLaLavanderia($this->lavanderia->administrador)->assertOk();

        $this->assertSame([$propio->id_publico], $this->idsDeLaLista($historial));

        foreach ([$deLaVecina, $deOtraPlaza] as $ajeno) {
            $this->assertSinDatosDe($ajeno, $historial->getContent());

            $porSuIdentificador = $this->pedidoComoLavanderia($this->lavanderia->administrador, $ajeno->id_publico)
                ->assertNotFound()
                ->assertExactJson(self::NO_EXISTE);
            $this->assertSinDatosDe($ajeno, $porSuIdentificador->getContent());
        }

        // Cada lavandería sí ve el suyo: la prueba no pasa en vacío.
        $this->assertSame([$deLaVecina->id_publico], $this->idsDeLaLista($this->historialDeLaLavanderia($vecina->administrador)));
        $this->assertSame([$deOtraPlaza->id_publico], $this->idsDeLaLista($this->historialDeLaLavanderia($lejana->administrador)));
    }

    #[TestDox('historial-lavanderia-E02: sin sesión, o con la cuenta de un usuario, el historial de la lavandería no se entrega y la respuesta no trae ningún pedido')]
    public function test_historial_lavanderia_e02_sin_sesion_o_con_la_cuenta_de_un_usuario_no_se_entrega(): void
    {
        $cuenta = $this->cuentaDeUsuario();
        $pedido = $this->entregarEl($this->pedidoConfirmadoEn($this->lavanderia, $cuenta), '2026-12-03 12:30');

        $sinSesion = $this->pedirSinSesion('/api/lavanderia/historial')->assertUnauthorized();
        $deUnUsuario = $this->historialDeLaLavanderia($cuenta)
            ->assertForbidden()
            ->assertJsonPath('message', 'Esta cuenta no tiene acceso.')
            ->assertJsonMissingPath('pedidos');

        foreach ([$sinSesion, $deUnUsuario] as $respuesta) {
            $this->assertSinDatosDe($pedido, $respuesta->getContent());
        }
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

    private function assertSinDatosDe(Pedido $pedido, string $contenido): void
    {
        foreach ([$pedido->id_publico, $pedido->folio] as $dato) {
            $this->assertStringNotContainsStringIgnoringCase($dato, $contenido);
        }
    }
}
