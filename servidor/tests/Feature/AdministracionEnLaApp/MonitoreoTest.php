<?php

namespace Tests\Feature\AdministracionEnLaApp;

use App\Autorizacion\Autorizacion;
use App\Cobro\Desarrollo\CobroSimulado;
use App\Filament\Pages\MonitoreoDePedidos;
use App\Models\EstadoPedido;
use App\Models\Pedido;
use App\Models\Plaza;
use App\Models\Repartidor;
use App\Models\Usuario;
use App\Pedidos\MarcadoresDelMonitoreo;
use App\Pedidos\NombresDelEstado;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\AdministracionEnLaApp\Soporte\ContratosDeLaApp;
use Tests\Feature\AdministracionEnLaApp\Soporte\SesionesDeLaApp;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Estados\Soporte\EstadosDePrueba;
use Tests\Feature\PedidosLavanderia\Soporte\PedidosDeLaLavanderiaDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * El monitoreo de pedidos en la API de la administración, GET /api/administracion/monitoreo
 * (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #4, "Vista en tiempo real de todos los pedidos activos con
 * mapa"; ADR-0022, decisiones 9, 12 y 13): los pedidos activos de todas las plazas y sus marcadores, en una sola
 * respuesta, con los marcadores solo de los pedidos de la lista. Un cambio de estado justo entre las dos lecturas se
 * corrige en la siguiente consulta.
 *
 * La respuesta es la de las clases que usa el panel para los mismos datos (administracion-app-E11): los pedidos de
 * Autorizacion::pedidosActivosDelPanel(), con la regla de administracion-E06; su estado con los nombres operativos de
 * App\Pedidos\NombresDelEstado; y los marcadores de App\Pedidos\MarcadoresDelMonitoreo::deLosPedidos(), con la de
 * administracion-E09 y E10. No trae nada de ningún repartidor: su posición está fuera del contrato (DD-077).
 *
 * El pedido cancelado, administracion-app-E31, espera DD-016 y BR-22: no se prueba aquí.
 *
 * Las plazas de prueba tienen su zona en el cuadrado de un grado con su esquina suroeste en (-120, 10) y en (-118, 10).
 * Todo es ficticio y está en el océano Pacífico.
 *
 * Escenarios: administracion-app-E28, administracion-app-E11, administracion-app-E06 y administracion-app-E07 · Spec:
 * openspec/changes/administracion-en-la-app/specs/administracion-en-la-app/spec.md · Tarea 9.1
 */
class MonitoreoTest extends TestCase
{
    use AgendaDePrueba;
    use ContratosDeLaApp;
    use EstadosDePrueba;
    use LavanderiasDePrueba;
    use PedidosDeLaLavanderiaDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;
    use SesionesDeLaApp;

    private const MONITOREO = '/api/administracion/monitoreo';

    /** Las llaves de cada pedido de la lista, en el orden del contrato del controlador. */
    private const LLAVES_DEL_PEDIDO = ['id_publico', 'folio', 'estado', 'plaza', 'ventana'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();
    }

    #[TestDox('administracion-app-E28: con un pedido Confirmado en una plaza, uno En proceso en otra plaza con su entrega en otra dirección y uno Entregado, el monitoreo trae los dos primeros con su folio, su estado operativo, su plaza y su recolección, y no el entregado; y sus marcadores: R en la recolección de cada uno y E en la entrega del que la tiene en otra dirección, cada uno con su folio, su estado y su plaza')]
    public function test_administracion_app_e28_los_pedidos_activos_en_la_lista_y_en_el_mapa(): void
    {
        [$confirmado, $enProceso, $entregado, $plazaDos] = $this->sembrarElMonitoreo();

        $respuesta = $this->monitoreoDe($this->cuentaDeAdministracion())->assertOk();
        $this->assertSame(['pedidos', 'marcadores'], array_keys($respuesta->json()));

        // La lista: los dos activos, de la recolección más próxima a la más lejana, y no el entregado (administracion-E06).
        $pedidos = $respuesta->json('pedidos');
        $this->assertSame([$confirmado->id_publico, $enProceso->id_publico], array_column($pedidos, 'id_publico'));
        $this->assertStringNotContainsString($entregado->folio, $respuesta->getContent());

        foreach ($pedidos as $pedido) {
            $this->assertSame(self::LLAVES_DEL_PEDIDO, array_keys($pedido));
        }

        $this->assertSame([
            'id_publico' => $confirmado->id_publico,
            'folio' => $confirmado->folio,
            'estado' => ['codigo' => EstadoPedido::CONFIRMADO, 'nombre' => 'Confirmado'],
            'plaza' => $this->plaza->nombre,
            'ventana' => [
                'inicio' => self::JUEVES_DE_PRUEBA.'T10:00:00-06:00',
                'fin' => self::JUEVES_DE_PRUEBA.'T11:00:00-06:00',
                'fecha' => self::JUEVES_DE_PRUEBA,
                'hora_inicio' => '10:00',
                'hora_fin' => '11:00',
            ],
        ], $pedidos[0]);
        $this->assertSame($enProceso->folio, $pedidos[1]['folio']);
        $this->assertSame(['codigo' => EstadoPedido::EN_PROCESO, 'nombre' => 'En proceso'], $pedidos[1]['estado']);
        $this->assertSame($plazaDos->nombre, $pedidos[1]['plaza']);
        $this->assertSame('11:00', $pedidos[1]['ventana']['hora_inicio']);
        $this->assertSame('12:00', $pedidos[1]['ventana']['hora_fin']);

        // El mapa: R en la recolección de cada pedido y E en la entrega del que la tiene en otra dirección
        // (administracion-E09 y E10), cada uno con su folio, su estado y su plaza, en su punto.
        $marcadores = $respuesta->json('marcadores');
        $this->assertSame(
            [
                [$confirmado->id_publico, $confirmado->folio, 'Confirmado', $this->plaza->nombre, MarcadoresDelMonitoreo::RECOLECCION, 'Recolección y entrega'],
                [$enProceso->id_publico, $enProceso->folio, 'En proceso', $plazaDos->nombre, MarcadoresDelMonitoreo::RECOLECCION, 'Recolección'],
                [$enProceso->id_publico, $enProceso->folio, 'En proceso', $plazaDos->nombre, MarcadoresDelMonitoreo::ENTREGA, 'Entrega'],
            ],
            array_map(fn (array $marcador): array => [
                $marcador['pedido'],
                $marcador['folio'],
                $marcador['estado'],
                $marcador['plaza'],
                $marcador['tipo'],
                $marcador['etiqueta'],
            ], $marcadores),
        );
        $this->assertEqualsWithDelta(10.2, $marcadores[0]['latitud'], 0.000001);
        $this->assertEqualsWithDelta(-119.8, $marcadores[0]['longitud'], 0.000001);
        $this->assertEqualsWithDelta(10.3, $marcadores[1]['latitud'], 0.000001);
        $this->assertEqualsWithDelta(-117.7, $marcadores[1]['longitud'], 0.000001);
        $this->assertEqualsWithDelta(10.8, $marcadores[2]['latitud'], 0.000001);
        $this->assertEqualsWithDelta(-117.2, $marcadores[2]['longitud'], 0.000001);

        // Cada marcador es de un pedido de la lista: la lista y el mapa salen de la misma respuesta.
        $this->assertSame([], array_diff(array_column($marcadores, 'pedido'), array_column($pedidos, 'id_publico')));
    }

    #[TestDox('administracion-app-E28: la lista y los marcadores son dos lecturas de la misma respuesta; si un pedido se vuelve activo justo entre las dos, esa respuesta no trae un marcador de un pedido que no está en la lista, y la siguiente consulta lo trae en las dos')]
    public function test_administracion_app_e28_un_pedido_que_se_activa_entre_las_dos_lecturas_no_deja_un_marcador_sin_pedido(): void
    {
        $confirmado = $this->pedidoConfirmadoEn($this->lavanderia, $this->cuentaDeUsuario(), '10:00');
        $porPagar = $this->pedidoPorPagarEn($this->lavanderia, $this->cuentaDeUsuario(), '11:00', latitud: 10.6, longitud: -119.4);
        $token = $this->tokenDeLaApp($this->cuentaDeAdministracion());

        // El pago se acredita justo después de que se lee la lista: la primera consulta a los pedidos de la petición.
        $pagado = false;
        DB::listen(function (QueryExecuted $consulta) use (&$pagado, $porPagar): void {
            if ($pagado || ! str_contains($consulta->sql, 'from "pedidos"')) {
                return;
            }

            $pagado = true;
            app(CobroSimulado::class)->pagar($porPagar);
        });

        $respuesta = $this->llamarConToken($token, 'GET', self::MONITOREO)->assertOk();
        $this->assertTrue($pagado, 'El pago se acreditó durante la consulta.');

        // La lista se leyó antes del pago; los marcadores, después, pero solo de los pedidos de la lista.
        $this->assertSame([$confirmado->id_publico], array_column($respuesta->json('pedidos'), 'id_publico'));
        $this->assertSame([$confirmado->id_publico], array_column($respuesta->json('marcadores'), 'pedido'));

        // La siguiente consulta, a los 30 s, ya lo trae en la lista y en el mapa.
        $siguiente = $this->llamarConToken($token, 'GET', self::MONITOREO)->assertOk();
        $this->assertSame([$confirmado->id_publico, $porPagar->id_publico], array_column($siguiente->json('pedidos'), 'id_publico'));
        $this->assertSame([$confirmado->id_publico, $porPagar->id_publico], array_column($siguiente->json('marcadores'), 'pedido'));
    }

    #[TestDox('administracion-app-E28: el monitoreo no trae nada de ningún repartidor, ni del que lleva un pedido activo ni del que entregó: ni su posición, ni su nombre, ni su teléfono, ni su correo, ni su identificador')]
    public function test_administracion_app_e28_el_monitoreo_no_trae_ningun_repartidor(): void
    {
        [, $enProceso] = $this->sembrarElMonitoreo();
        $this->assertNotNull($enProceso->fresh()->id_repartidores, 'El pedido En proceso lleva su repartidor.');

        $respuesta = $this->monitoreoDe($this->cuentaDeAdministracion())->assertOk();
        $contenido = $respuesta->getContent();

        $this->assertNotContains('repartidor', $this->llavesDe($respuesta->json()));
        $this->assertSame([], array_diff(
            array_column($respuesta->json('marcadores'), 'tipo'),
            [MarcadoresDelMonitoreo::RECOLECCION, MarcadoresDelMonitoreo::ENTREGA],
        ));

        $repartidores = Repartidor::query()->with('cuenta')->get();
        $this->assertCount(2, $repartidores, 'El del pedido En proceso y el del entregado.');

        foreach ($repartidores as $repartidor) {
            foreach ([$repartidor->id_publico, $repartidor->cuenta->id_publico, $repartidor->cuenta->nombre, $repartidor->cuenta->correo, $repartidor->cuenta->telefono] as $dato) {
                if (filled($dato)) {
                    $this->assertStringNotContainsStringIgnoringCase($dato, $contenido);
                }
            }
        }
    }

    #[TestDox('administracion-app-E11: con un pedido en cada uno de los siete estados activos, la lista del monitoreo es la de Autorizacion::pedidosActivosDelPanel(), en el orden de la tabla del monitoreo del panel, con los nombres de NombresDelEstado y la plaza de cada pedido, y sus marcadores son los de MarcadoresDelMonitoreo::deLosPedidos() para los mismos datos, los mismos del panel')]
    public function test_administracion_app_e11_el_monitoreo_da_lo_mismo_que_el_panel(): void
    {
        // Confirmado y En proceso, y el entregado, que no entra.
        $this->sembrarElMonitoreo();

        // Los otros cinco estados activos de administracion-E06, también el rechazado, y uno que espera su pago, que no
        // entra.
        $rechazado = $this->pedidoConfirmadoEn($this->lavanderia, $this->cuentaDeUsuario(), '12:00');
        $this->rechazarComoLaLavanderia($rechazado);

        $aceptado = $this->pedidoConfirmadoEn($this->lavanderia, $this->cuentaDeUsuario(), '12:00');
        $this->aceptarComoLaLavanderia($aceptado);

        foreach ([
            EstadoPedido::RECOLECTADO => [EstadoPedido::RECOLECTADO],
            EstadoPedido::LISTO => [EstadoPedido::RECOLECTADO, EstadoPedido::EN_PROCESO, EstadoPedido::LISTO],
            EstadoPedido::EN_ENTREGA => [EstadoPedido::RECOLECTADO, EstadoPedido::EN_PROCESO, EstadoPedido::LISTO, EstadoPedido::EN_ENTREGA],
        ] as $estado => $botones) {
            $pedido = $this->pedidoConfirmadoEn($this->lavanderia, $this->cuentaDeUsuario(), '12:00');
            $this->aceptarComoLaLavanderia($pedido);

            foreach ($botones as $boton) {
                $this->marcarComoLaLavanderia($pedido, $boton);
            }

            $this->assertSame($estado, $pedido->fresh()->estado->codigo);
        }

        $porPagar = $this->pedidoPorPagarEn($this->lavanderia, $this->cuentaDeUsuario(), '12:00');
        $administracion = $this->cuentaDeAdministracion();

        $respuesta = $this->monitoreoDe($administracion)->assertOk();

        $autorizacion = app(Autorizacion::class);
        $delPanel = $autorizacion->pedidosActivosDelPanel($administracion)
            ->deLaRecoleccionMasProxima()
            ->with(['estado', 'plaza'])
            ->get();
        $this->assertCount(7, $delPanel);
        $this->assertEqualsCanonicalizing(
            [
                EstadoPedido::CONFIRMADO,
                EstadoPedido::ACEPTADO,
                EstadoPedido::RECHAZADO,
                EstadoPedido::RECOLECTADO,
                EstadoPedido::EN_PROCESO,
                EstadoPedido::LISTO,
                EstadoPedido::EN_ENTREGA,
            ],
            $delPanel->pluck('estado.codigo')->all(),
        );

        // La tabla del monitoreo del panel, con la misma cuenta y los mismos datos, muestra esos pedidos en ese orden:
        // si el orden de un lado cambia, la app y el panel ya no muestran lo mismo.
        Filament::setCurrentPanel('admin');
        $this->actingAs($administracion, 'web');

        Livewire::test(MonitoreoDePedidos::class)
            ->assertOk()
            ->assertCountTableRecords(7)
            ->assertCanSeeTableRecords($delPanel, inOrder: true);

        // Las columnas de la tabla del panel: el folio, el estado y la plaza.
        $this->assertSame($delPanel->pluck('id_publico')->all(), array_column($respuesta->json('pedidos'), 'id_publico'));
        $this->assertSame($delPanel->pluck('folio')->all(), array_column($respuesta->json('pedidos'), 'folio'));
        $this->assertSame(
            $delPanel->map(fn (Pedido $pedido): array => ['codigo' => $pedido->estado->codigo, 'nombre' => NombresDelEstado::nombre($pedido->estado)])->all(),
            array_column($respuesta->json('pedidos'), 'estado'),
        );
        $this->assertSame($delPanel->pluck('plaza.nombre')->all(), array_column($respuesta->json('pedidos'), 'plaza'));
        $this->assertNotContains($porPagar->id_publico, array_column($respuesta->json('pedidos'), 'id_publico'));

        $this->assertEquals(
            app(MarcadoresDelMonitoreo::class)->deLosPedidos($autorizacion->pedidosActivosDelPanel($administracion)),
            $respuesta->json('marcadores'),
        );
    }

    #[TestDox('administracion-app-E28: sin pedidos activos, la lista y los marcadores llegan vacíos')]
    public function test_administracion_app_e28_sin_pedidos_activos_la_lista_y_el_mapa_llegan_vacios(): void
    {
        $entregado = $this->pedidoConfirmadoEn($this->lavanderia, $this->cuentaDeUsuario(), '12:00');
        $this->aceptarComoLaLavanderia($entregado);
        $this->llevarHastaEntregado($entregado);

        $this->monitoreoDe($this->cuentaDeAdministracion())
            ->assertOk()
            ->assertExactJson(['pedidos' => [], 'marcadores' => []]);
    }

    #[TestDox('administracion-app-E06 y administracion-app-E07: GET /api/administracion/monitoreo responde 403 sin datos a las cuentas de usuario, de lavandería y de repartidor y a un token de administración sin la habilidad, y 401 sin token y con el token vencido; nada cambia')]
    public function test_administracion_app_e06_y_e07_solo_la_administracion_abre_el_monitoreo(): void
    {
        // Con un pedido activo, para que un rechazo que trajera datos se notara.
        $this->pedidoConfirmadoEn($this->lavanderia, $this->cuentaDeUsuario(), '10:00');

        $this->assertSoloEntraLaAdministracion('GET', self::MONITOREO);
    }

    #[TestDox('administracion-app-E28: el contrato del monitoreo con la app: cada pedido con su id_publico, su folio, su estado operativo, su plaza y su recolección, sin la zona horaria, y sus marcadores; sin pedidos activos, las dos listas vacías (design.md, F-D04)')]
    public function test_administracion_app_e28_el_contrato_del_monitoreo(): void
    {
        $administracion = $this->cuentaDeAdministracion();
        $this->assertCumpleSuContrato('monitoreo.vacio', 'GET /api/administracion/monitoreo', $this->monitoreoDe($administracion));

        $this->sembrarElMonitoreo();
        $respuesta = $this->monitoreoDe($administracion);

        $this->assertCumpleSuContrato('monitoreo', 'GET /api/administracion/monitoreo', $respuesta);
        foreach ($respuesta->json('pedidos') as $pedido) {
            $this->assertSame(self::LLAVES_DEL_PEDIDO, array_keys($pedido));
        }
    }

    /**
     * Los tres pedidos de administracion-app-E28: uno Confirmado en la plaza de prueba, con la entrega en la misma
     * dirección; uno En proceso en otra plaza, con la entrega en otra dirección y su repartidor; y uno Entregado.
     *
     * @return array{Pedido, Pedido, Pedido, Plaza}
     */
    private function sembrarElMonitoreo(): array
    {
        $confirmado = $this->pedidoConfirmadoEn($this->lavanderia, $this->cuentaDeUsuario(), '10:00', latitud: 10.2, longitud: -119.8);

        $plazaDos = $this->plazaDos();
        $enProceso = $this->pedidoConfirmadoConEntregaEn(
            $plazaDos,
            '11:00',
            recogida: ['latitud' => 10.3, 'longitud' => -117.7],
            entrega: ['latitud' => 10.8, 'longitud' => -117.2],
        );
        $this->aceptarComoLaLavanderia($enProceso);
        $this->marcarComoLaLavanderia($enProceso, EstadoPedido::RECOLECTADO);
        $this->marcarComoLaLavanderia($enProceso, EstadoPedido::EN_PROCESO);
        $this->repartidorQueLoAcepta($enProceso);

        $entregado = $this->pedidoConfirmadoEn($this->lavanderia, $this->cuentaDeUsuario(), '12:00');
        $this->aceptarComoLaLavanderia($entregado);
        $this->llevarHastaEntregado($entregado);

        return [$confirmado->fresh(), $enProceso->fresh(), $entregado->fresh(), $plazaDos];
    }

    /**
     * Otra plaza activa con su agenda y su zona, en el cuadrado de un grado con su esquina suroeste en (-118, 10).
     */
    private function plazaDos(): Plaza
    {
        $plaza = $this->plazaConAgenda('Plaza de prueba 2', anticipacionMinutos: 0, diasHaciaAdelante: 3);
        $this->zonaDePrueba($plaza, 'Centro 2', $this->cuadrado(-118.0, 10.0));

        return $plaza;
    }

    /**
     * Un pedido confirmado de una cuenta nueva a otra lavandería de esa plaza, con la recolección de una hora a esa
     * hora del jueves de prueba, la recogida en un punto y la entrega en otra dirección guardada de la cuenta, en el
     * suyo.
     *
     * @param  array{latitud: float, longitud: float}  $recogida
     * @param  array{latitud: float, longitud: float}  $entrega
     */
    private function pedidoConfirmadoConEntregaEn(Plaza $plaza, string $hora, array $recogida, array $entrega): Pedido
    {
        $cuenta = $this->cuentaDeUsuario();
        $lavanderia = $this->otraLavanderia($plaza);
        $inicio = CarbonImmutable::parse(self::JUEVES_DE_PRUEBA.' '.$hora, $plaza->zona_horaria);

        $id = $this->aceptarResumen($cuenta, $this->resumen(
            $this->direccionDe($cuenta, ['id_plazas' => $plaza->getKey(), 'ubicacion' => $recogida]),
            $this->direccionDe($cuenta, ['id_plazas' => $plaza->getKey(), 'calle' => 'Calle de la entrega', 'ubicacion' => $entrega]),
            [
                'lavanderia' => $lavanderia->id_publico,
                'ventana' => ['inicio' => $inicio->toIso8601String(), 'fin' => $inicio->addHour()->toIso8601String()],
            ],
        ))->assertCreated()->json('pedido.id_publico');

        return app(CobroSimulado::class)->pagar(Pedido::query()->where('id_publico', $id)->sole())->fresh();
    }

    /**
     * El monitoreo con el token de la app de esa cuenta.
     */
    private function monitoreoDe(Usuario $cuenta): TestResponse
    {
        return $this->llamarConToken($this->tokenDeLaApp($cuenta), 'GET', self::MONITOREO);
    }

    /**
     * Todas las llaves de la respuesta, a cualquier profundidad.
     *
     * @param  array<array-key, mixed>  $datos
     * @return list<string>
     */
    private function llavesDe(array $datos): array
    {
        $llaves = [];

        foreach ($datos as $llave => $valor) {
            if (is_string($llave)) {
                $llaves[] = $llave;
            }

            if (is_array($valor)) {
                $llaves = [...$llaves, ...$this->llavesDe($valor)];
            }
        }

        return $llaves;
    }
}
