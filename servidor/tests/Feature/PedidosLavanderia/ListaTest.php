<?php

namespace Tests\Feature\PedidosLavanderia;

use App\Models\EstadoPedido;
use App\Models\Pedido;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Estados\Soporte\EstadosDePrueba;
use Tests\Feature\PedidosLavanderia\Soporte\PedidosDeLaLavanderiaDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * La lista en tiempo real de los pedidos activos de la lavandería: "Lista en tiempo real" (NSV-2026-001-v8.pdf:17:ANEXO
 * A, Sección A.3, Módulo B #2), con GET /api/lavanderia/pedidos. La app la consulta cada 30 segundos con la pantalla
 * visible (ADR-0007; tarea 2.2).
 *
 * Son activos los confirmados que esperan su respuesta y los que aceptó y aún no llegan a Entregado; nunca uno
 * entregado ni uno rechazado. Solo los de esa lavandería, y ningún dato de un usuario (pedidos-lavanderia-E04 espera
 * DD-022 y DD-014). El pago acreditado es de pedidos-lavanderia-E17 (SinPagoTest).
 *
 * Escenarios: pedidos-lavanderia-E01, pedidos-lavanderia-E02 y, de la lista, pedidos-lavanderia-E07 y E12 · Spec:
 * openspec/changes/pedidos-de-la-lavanderia/specs/pedidos-lavanderia/spec.md · Tarea 1.1
 */
class ListaTest extends TestCase
{
    use AgendaDePrueba;
    use EstadosDePrueba;
    use LavanderiasDePrueba;
    use PedidosDeLaLavanderiaDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    private Usuario $cuenta;

    private Usuario $socio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();

        $this->cuenta = $this->cuentaDeUsuario();
        $this->socio = $this->lavanderia->administrador;
    }

    #[TestDox('pedidos-lavanderia-E01: con un pedido que espera su respuesta, uno aceptado en proceso, uno entregado y uno rechazado, la lista trae el que espera respuesta y el aceptado, y no el entregado ni el rechazado')]
    public function test_pedidos_lavanderia_e01_la_lista_trae_los_pedidos_activos(): void
    {
        $esperaRespuesta = $this->pedidoConfirmadoDe($this->cuenta);
        $enProceso = $this->pedidoAceptadoDe($this->cuenta);
        $this->marcarComoLaLavanderia($enProceso, EstadoPedido::RECOLECTADO);
        $this->marcarComoLaLavanderia($enProceso, EstadoPedido::EN_PROCESO);
        $entregado = $this->llevarHastaEntregado($this->pedidoAceptadoDe($this->cuenta));
        $rechazado = $this->pedidoConfirmadoDe($this->cuenta);
        $this->rechazarComoLaLavanderia($rechazado);

        $respuesta = $this->listaDeLaLavanderia($this->socio)->assertOk();

        $this->assertSame([$esperaRespuesta->id_publico, $enProceso->id_publico], $this->idsDeLaLista($respuesta));
        foreach ([$entregado, $rechazado] as $fuera) {
            $this->assertStringNotContainsString($fuera->id_publico, $respuesta->getContent());
        }
    }

    #[TestDox('pedidos-lavanderia-E01: la lista trae el pedido en cada estado activo, de por aceptar a En proceso de entrega, con su estado y su paso siguiente')]
    public function test_pedidos_lavanderia_e01_la_lista_trae_cada_estado_activo(): void
    {
        $esperados = [];
        $esperados[] = [$this->pedidoConfirmadoDe($this->cuenta), EstadoPedido::CONFIRMADO, null];
        $esperados[] = [$this->pedidoAceptadoDe($this->cuenta), EstadoPedido::ACEPTADO, EstadoPedido::RECOLECTADO];
        $botones = [EstadoPedido::RECOLECTADO, EstadoPedido::EN_PROCESO, EstadoPedido::LISTO, EstadoPedido::EN_ENTREGA];
        foreach ($botones as $i => $estado) {
            $pedido = $this->pedidoAceptadoDe($this->cuenta);
            foreach (array_slice($botones, 0, $i + 1) as $boton) {
                $this->marcarComoLaLavanderia($pedido, $boton);
            }
            $esperados[] = [$pedido, $estado, $botones[$i + 1] ?? null];
        }

        $pedidos = collect($this->listaDeLaLavanderia($this->socio)->assertOk()->json('pedidos'))->keyBy('id_publico');

        $this->assertCount(count($esperados), $pedidos);
        foreach ($esperados as [$pedido, $estado, $siguiente]) {
            $this->assertSame($estado, $pedidos[$pedido->id_publico]['estado']['codigo']);
            $this->assertSame($estado === EstadoPedido::CONFIRMADO, $pedidos[$pedido->id_publico]['por_responder']);
            $this->assertSame($siguiente, $pedidos[$pedido->id_publico]['siguiente']['estado'] ?? null);
        }
    }

    #[TestDox('pedidos-lavanderia-E01: cada pedido de la lista trae su folio, su servicio y su fecha y ventana de recolección, en la hora de la plaza, y nada del usuario')]
    public function test_pedidos_lavanderia_e01_cada_pedido_trae_folio_servicio_y_ventana(): void
    {
        $pedido = $this->pedidoConfirmadoDe($this->cuenta);

        $this->listaDeLaLavanderia($this->socio)->assertOk()->assertExactJson(['pedidos' => [[
            'id_publico' => $pedido->id_publico,
            'folio' => $pedido->folio,
            'confirmado_en' => $pedido->confirmado_en->toIso8601ZuluString(),
            'estado' => ['codigo' => EstadoPedido::CONFIRMADO, 'nombre' => 'Por aceptar'],
            'por_responder' => true,
            'siguiente' => null,
            'servicios' => [[
                'codigo' => 'estandar',
                'nombre' => 'Lavado estándar',
                'unidad' => ['codigo' => 'kg', 'nombre' => 'kilo'],
            ]],
            'zona_horaria' => self::ZONA_HORARIA,
            'ventana' => [
                'inicio' => self::JUEVES_DE_PRUEBA.'T11:00:00-06:00',
                'fin' => self::JUEVES_DE_PRUEBA.'T12:00:00-06:00',
                'fecha' => self::JUEVES_DE_PRUEBA,
                'hora_inicio' => '11:00',
                'hora_fin' => '12:00',
            ],
        ]]]);
    }

    #[TestDox('pedidos-lavanderia-E01: la lista va de la recolección más próxima a la más lejana, y con la misma recolección, del pedido más viejo al más nuevo')]
    public function test_pedidos_lavanderia_e01_la_lista_va_de_la_recoleccion_mas_proxima(): void
    {
        $alMediodia = $this->pedidoConfirmadoEn($this->lavanderia, $this->cuenta, '12:00');
        $primeroALasOnce = $this->pedidoConfirmadoEn($this->lavanderia, $this->cuenta, '11:00');
        $segundoALasOnce = $this->pedidoConfirmadoEn($this->lavanderia, $this->cuenta, '11:00');
        $alasDiez = $this->pedidoConfirmadoEn($this->lavanderia, $this->cuenta, '10:00');

        $this->assertSame(
            [$alasDiez->id_publico, $primeroALasOnce->id_publico, $segundoALasOnce->id_publico, $alMediodia->id_publico],
            $this->idsDeLaLista($this->listaDeLaLavanderia($this->socio)->assertOk()),
        );
    }

    #[TestDox('pedidos-lavanderia-E01: sin pedidos activos, la lista llega vacía')]
    public function test_pedidos_lavanderia_e01_sin_pedidos_activos_la_lista_llega_vacia(): void
    {
        $this->listaDeLaLavanderia($this->socio)->assertOk()->assertExactJson(['pedidos' => []]);
    }

    #[TestDox('pedidos-lavanderia-E07: el pedido aceptado sigue en la lista como aceptado, ya sin Aceptar ni Rechazar')]
    public function test_pedidos_lavanderia_e07_el_aceptado_sigue_en_la_lista(): void
    {
        $pedido = $this->pedidoConfirmadoDe($this->cuenta);
        $this->aceptarPedido($this->socio, $pedido->id_publico)->assertOk();

        $respuesta = $this->listaDeLaLavanderia($this->socio)->assertOk();

        $this->assertSame([$pedido->id_publico], $this->idsDeLaLista($respuesta));
        $respuesta->assertJsonPath('pedidos.0.estado', ['codigo' => EstadoPedido::ACEPTADO, 'nombre' => 'Aceptado'])
            ->assertJsonPath('pedidos.0.por_responder', false);
    }

    #[TestDox('pedidos-lavanderia-E12: el pedido rechazado sale de la lista de pedidos activos')]
    public function test_pedidos_lavanderia_e12_el_rechazado_sale_de_la_lista(): void
    {
        $pedido = $this->pedidoConfirmadoDe($this->cuenta);
        $this->assertSame([$pedido->id_publico], $this->idsDeLaLista($this->listaDeLaLavanderia($this->socio)->assertOk()));

        $this->rechazarPedido($this->socio, $pedido->id_publico, 'Sin lugar.')->assertOk();

        $this->assertSame([], $this->idsDeLaLista($this->listaDeLaLavanderia($this->socio)->assertOk()));
    }

    #[TestDox('pedidos-lavanderia-E02: con dos lavanderías de la misma plaza, cada una con pedidos activos, la lista de la primera no trae ningún pedido de la segunda ni datos de usuarios')]
    public function test_pedidos_lavanderia_e02_solo_los_pedidos_de_esa_lavanderia(): void
    {
        $segunda = $this->otraLavanderia();
        $otraCuenta = $this->cuentaDeUsuario();
        $deLaPrimera = [$this->pedidoConfirmadoDe($this->cuenta), $this->pedidoAceptadoDe($this->cuenta)];
        $deLaSegunda = [
            $this->pedidoConfirmadoEn($segunda, $otraCuenta),
            $this->pedidoConfirmadoEn($segunda, $this->cuenta),
        ];
        $this->aceptarComoLaLavanderia($deLaSegunda[1]);

        $primera = $this->listaDeLaLavanderia($this->socio)->assertOk();
        $deLaOtra = $this->listaDeLaLavanderia($segunda->administrador)->assertOk();

        $this->assertSame(array_map(fn (Pedido $pedido): string => $pedido->id_publico, $deLaPrimera), $this->idsDeLaLista($primera));
        $this->assertSame(array_map(fn (Pedido $pedido): string => $pedido->id_publico, $deLaSegunda), $this->idsDeLaLista($deLaOtra));

        foreach ($deLaSegunda as $ajeno) {
            foreach ([$ajeno->id_publico, $ajeno->folio] as $dato) {
                $this->assertStringNotContainsStringIgnoringCase($dato, $primera->getContent());
            }
        }
        // Ningún dato de una cuenta de usuario, ni de las que sí le pidieron: cuáles ve la lavandería espera E04.
        foreach ([$this->cuenta, $otraCuenta] as $usuario) {
            $this->assertStringNotContainsStringIgnoringCase($usuario->correo, $primera->getContent());
        }
    }

    #[TestDox('pedidos-lavanderia-E02: una cuenta de usuario no recibe la lista de la lavandería: la ruta es del Módulo B y responde 403 sin pedidos')]
    public function test_pedidos_lavanderia_e02_una_cuenta_de_usuario_no_recibe_la_lista(): void
    {
        $this->pedidoConfirmadoDe($this->cuenta);

        $this->listaDeLaLavanderia($this->cuenta)
            ->assertForbidden()
            ->assertJsonPath('message', 'Esta cuenta no tiene acceso.')
            ->assertJsonMissingPath('pedidos');
    }
}
