<?php

namespace Tests\Feature\Confirmacion;

use App\Cobro\Desarrollo\CobroSimulado;
use App\Models\EstadoPedido;
use App\Models\Lavanderia;
use App\Models\Pedido;
use App\Models\PedidoEvento;
use App\Pedidos\FolioDelPedido;
use App\Pedidos\PedidoConfirmado;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as Ruta;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\TestDox;
use RuntimeException;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * El pedido pasa a Confirmado, con un folio que no se repite en su plaza y con confirmado_en, solo cuando el componente
 * de cobro avisa que la pasarela confirmó su pago (C-188; ADR-0010, punto 1). La prueba paga el pedido con la pasarela
 * simulada (App\Cobro\Desarrollo\CobroSimulado): su aviso de aprobado, por el camino del webhook, deja el cobro pagado
 * y llama a la operación de confirmar (pago-E18). Ni el botón del resumen ni ninguna petición de la app confirman.
 *
 * La unicidad del folio la garantiza la base, con el único (id_plazas, folio), no solo el código (design.md, decisión
 * 6). El formato del folio es provisional hasta DD-101: la prueba no lo fija.
 *
 * Escenarios: confirmacion-E06, confirmacion-E07 y confirmacion-E13 · Spec:
 * openspec/changes/resumen-y-confirmacion/specs/confirmacion/spec.md · Tarea 1.3
 */
class ConfirmarPedidoTest extends TestCase
{
    use AgendaDePrueba;
    use LavanderiasDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    /** SQLSTATE de PostgreSQL cuando se repite un valor único. */
    private const UNICO_REPETIDO = '23505';

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();
    }

    #[TestDox('confirmacion-E13: el pago acreditado pasa el pedido a Confirmado, con su folio y con confirmado_en')]
    public function test_confirmacion_e13_el_pago_acreditado_pasa_el_pedido_a_confirmado_con_folio_y_confirmado_en(): void
    {
        $pedido = $this->pedidoPorPagar();
        $this->elJuevesALas('09:45:00');

        // La pasarela simulada acredita el pago con su aviso, por el camino del webhook, y el cobro confirma el pedido.
        $confirmado = app(CobroSimulado::class)->pagar($pedido);

        $guardado = $pedido->fresh();
        $this->assertSame(EstadoPedido::CONFIRMADO, $guardado->estado->codigo);
        $this->assertIsString($guardado->folio);
        $this->assertNotSame('', $guardado->folio);
        $this->assertTrue($guardado->confirmado_en->eq(CarbonImmutable::now()));
        $this->assertSame($guardado->folio, $confirmado->folio);
    }

    #[TestDox('confirmacion-E13: el cambio a Confirmado queda en pedido_eventos, hecho por el sistema, a la hora de la confirmación')]
    public function test_confirmacion_e13_el_cambio_queda_en_pedido_eventos(): void
    {
        $pedido = $this->pedidoPorPagar();
        $this->elJuevesALas('09:45:00');

        app(CobroSimulado::class)->pagar($pedido);

        $eventos = PedidoEvento::query()->where('id_pedidos', $pedido->getKey())->orderBy('id_pedido_eventos')->get();
        $this->assertSame(
            [EstadoPedido::POR_PAGAR, EstadoPedido::CONFIRMADO],
            $eventos->map(fn (PedidoEvento $evento): string => $evento->estado->codigo)->all(),
        );

        $confirmacion = $eventos->last();
        $this->assertSame($pedido->id_plazas, $confirmacion->id_plazas);
        // Lo hace el sistema, por el pago: sin cuenta.
        $this->assertNull($confirmacion->id_usuarios);
        $this->assertTrue($confirmacion->ocurrido_en->eq($pedido->fresh()->confirmado_en));
    }

    #[TestDox('confirmacion-E13: el estado, el folio y el evento van en la misma transacción: si el evento falla, el pedido sigue por pagar y sin folio')]
    public function test_confirmacion_e13_el_estado_el_folio_y_el_evento_van_en_la_misma_transaccion(): void
    {
        $pedido = $this->pedidoPorPagar();
        Event::listen('eloquent.creating: '.PedidoEvento::class, fn () => throw new RuntimeException('Falla de prueba.'));

        try {
            app(CobroSimulado::class)->pagar($pedido);
            $this->fail('El evento debió fallar.');
        } catch (RuntimeException $error) {
            $this->assertSame('Falla de prueba.', $error->getMessage());
        }

        $guardado = $pedido->fresh();
        $this->assertSame(EstadoPedido::POR_PAGAR, $guardado->estado->codigo);
        $this->assertNull($guardado->folio);
        $this->assertNull($guardado->confirmado_en);
        $this->assertSame(1, PedidoEvento::count());
    }

    #[TestDox('confirmacion-E13: al confirmar se publica el evento de pedido confirmado, del que cuelga el correo')]
    public function test_confirmacion_e13_al_confirmar_se_publica_el_evento_de_pedido_confirmado(): void
    {
        Event::fake([PedidoConfirmado::class]);
        $pedido = $this->pedidoPorPagar();

        app(CobroSimulado::class)->pagar($pedido);

        Event::assertDispatchedTimes(PedidoConfirmado::class, 1);
        Event::assertDispatched(
            PedidoConfirmado::class,
            fn (PedidoConfirmado $evento): bool => $evento->pedido->is($pedido) && $evento->pedido->folio !== null,
        );
    }

    #[TestDox('confirmacion-E13: un aviso repetido del cobro no cambia el folio, ni agrega otro evento, ni publica otra vez')]
    public function test_confirmacion_e13_un_aviso_repetido_no_cambia_el_folio_ni_agrega_otro_evento(): void
    {
        $pedido = $this->pedidoPorPagar();
        $cobro = app(CobroSimulado::class);
        $cobro->pagar($pedido);
        $folio = $pedido->fresh()->folio;
        $confirmadoEn = $pedido->fresh()->confirmado_en;

        Event::fake([PedidoConfirmado::class]);
        $this->elJuevesALas('10:15:00');
        $cobro->pagar($pedido->fresh());

        $this->assertSame($folio, $pedido->fresh()->folio);
        $this->assertTrue($confirmadoEn->eq($pedido->fresh()->confirmado_en));
        $this->assertSame(2, PedidoEvento::count());
        Event::assertNotDispatched(PedidoConfirmado::class);
    }

    #[TestDox('confirmacion-E06: varios pedidos de la misma plaza que se confirman reciben cada uno un folio distinto')]
    public function test_confirmacion_e06_varios_pedidos_de_la_misma_plaza_reciben_folios_distintos(): void
    {
        $pedidos = array_map(fn (): Pedido => $this->pedidoPorPagar(), range(1, 5));

        foreach ($pedidos as $pedido) {
            app(CobroSimulado::class)->pagar($pedido);
        }

        $folios = Pedido::query()->where('id_plazas', $this->plaza->getKey())->pluck('folio')->all();
        $this->assertCount(5, $folios);
        $this->assertNotContains(null, $folios);
        $this->assertSame($folios, array_values(array_unique($folios)));
    }

    #[TestDox('confirmacion-E06: la base rechaza un folio repetido en la misma plaza, con el único (id_plazas, folio), aunque el código no lo haya revisado')]
    public function test_confirmacion_e06_la_base_rechaza_un_folio_repetido_en_la_misma_plaza(): void
    {
        $primero = $this->pedidoPorPagar();
        $segundo = $this->pedidoPorPagar();
        app(CobroSimulado::class)->pagar($primero);
        $folio = $primero->fresh()->folio;

        try {
            DB::transaction(fn () => DB::table('pedidos')
                ->where('id_pedidos', $segundo->getKey())
                ->update(['folio' => $folio]));
            $this->fail('La base debió rechazar el folio repetido.');
        } catch (QueryException $error) {
            $this->assertSame(self::UNICO_REPETIDO, $error->getCode());
            $this->assertStringContainsString('pedidos_id_plazas_folio_unique', $error->getMessage());
        }

        $this->assertNull($segundo->fresh()->folio);
    }

    #[TestDox('confirmacion-E06: el folio es único por plaza: otra plaza puede tener el mismo (DD-101)')]
    public function test_confirmacion_e06_el_folio_es_unico_por_plaza(): void
    {
        $deEstaPlaza = $this->pedidoPorPagar();
        app(CobroSimulado::class)->pagar($deEstaPlaza);

        $otraPlaza = $this->plazaConAgenda('Plaza de prueba 2', self::ZONA_HORARIA);
        $otraLavanderia = Lavanderia::factory()->en($otraPlaza)->create();
        $deOtraPlaza = $this->pedidoDirecto($otraLavanderia);

        DB::table('pedidos')->where('id_pedidos', $deOtraPlaza->getKey())->update(['folio' => $deEstaPlaza->fresh()->folio]);

        $this->assertSame($deEstaPlaza->fresh()->folio, $deOtraPlaza->fresh()->folio);
    }

    #[TestDox('confirmacion-E06: si dos confirmaciones al mismo tiempo dan con el mismo folio, la base rechaza el segundo y la confirmación toma otro')]
    public function test_confirmacion_e06_si_dos_confirmaciones_dan_con_el_mismo_folio_la_segunda_toma_otro(): void
    {
        // El generador de folios da el mismo dos veces, como dos confirmaciones simultáneas que dieran con él.
        $this->folios(['FOLIO-1', 'FOLIO-1', 'FOLIO-2']);
        $primero = $this->pedidoPorPagar();
        $segundo = $this->pedidoPorPagar();

        app(CobroSimulado::class)->pagar($primero);
        app(CobroSimulado::class)->pagar($segundo);

        $this->assertSame('FOLIO-1', $primero->fresh()->folio);
        $this->assertSame('FOLIO-2', $segundo->fresh()->folio);
        $this->assertSame(EstadoPedido::CONFIRMADO, $segundo->fresh()->estado->codigo);
        // El intento rechazado no dejó rastro: un solo evento de confirmación por pedido.
        $this->assertSame(4, PedidoEvento::count());
    }

    #[TestDox('confirmacion-E06: si el folio sigue repitiéndose, la confirmación falla entera y el pedido sigue por pagar y sin folio')]
    public function test_confirmacion_e06_si_el_folio_sigue_repitiendose_la_confirmacion_falla_entera(): void
    {
        $this->folios(array_fill(0, 20, 'FOLIO-1'));
        $primero = $this->pedidoPorPagar();
        $segundo = $this->pedidoPorPagar();
        app(CobroSimulado::class)->pagar($primero);

        try {
            app(CobroSimulado::class)->pagar($segundo);
            $this->fail('La confirmación debió fallar.');
        } catch (QueryException $error) {
            $this->assertSame(self::UNICO_REPETIDO, $error->getCode());
        }

        $guardado = $segundo->fresh();
        $this->assertNull($guardado->folio);
        $this->assertNull($guardado->confirmado_en);
        $this->assertSame(EstadoPedido::POR_PAGAR, $guardado->estado->codigo);
        $this->assertSame(1, PedidoEvento::query()->where('id_pedidos', $segundo->getKey())->count());
    }

    #[TestDox('confirmacion-E07: aceptar el resumen no confirma el pedido, aunque la app mande un folio, un estado o que ya pagó')]
    public function test_confirmacion_e07_aceptar_el_resumen_no_confirma_aunque_la_app_diga_que_ya_pago(): void
    {
        $cuenta = $this->cuentaDeUsuario();

        $this->aceptarResumen($cuenta, $this->resumen($this->direccionDe($cuenta), datos: [
            'folio' => 'A-1',
            'estado' => EstadoPedido::CONFIRMADO,
            'id_estados_pedido' => EstadoPedido::where('codigo', EstadoPedido::CONFIRMADO)->sole()->getKey(),
            'confirmado_en' => '2026-12-03T09:00:00-06:00',
            'pagado' => true,
            'pago' => ['estado' => 'pagado', 'id_pasarela_orden' => 'ord_prueba'],
        ]))->assertCreated()
            ->assertJsonPath('pedido.folio', null)
            ->assertJsonPath('pedido.confirmado_en', null)
            ->assertJsonPath('pedido.estado.codigo', EstadoPedido::POR_PAGAR);

        $pedido = Pedido::sole();
        $this->assertNull($pedido->folio);
        $this->assertNull($pedido->confirmado_en);
        $this->assertSame(EstadoPedido::POR_PAGAR, $pedido->estado->codigo);
        $this->assertSame(1, PedidoEvento::count());
    }

    #[TestDox('confirmacion-E07: ninguna ruta de la API confirma un pedido: no hay otra ruta de pedidos que crear y consultar')]
    public function test_confirmacion_e07_ninguna_ruta_de_la_api_confirma_un_pedido(): void
    {
        $rutas = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (Ruta $ruta): bool => str_starts_with($ruta->uri(), 'api/pedidos'))
            ->flatMap(fn (Ruta $ruta): array => array_map(
                fn (string $metodo): string => "{$metodo} {$ruta->uri()}",
                array_diff($ruta->methods(), ['HEAD']),
            ))
            ->values()
            ->all();

        $this->assertContains('POST api/pedidos', $rutas);
        // Las de consultar son el pedido y el historial de la cuenta (historial-y-calificacion, historial-E01). La otra
        // que escribe es la calificación del servicio (historial-E06), que solo guarda la calificación: abajo se ve que
        // no confirma nada.
        $this->assertSame([], array_values(array_diff(
            $rutas,
            ['POST api/pedidos', 'GET api/pedidos', 'GET api/pedidos/{pedido}', 'POST api/pedidos/{pedido}/calificacion'],
        )));

        // Y una petición que intente confirmar no encuentra ruta.
        $pedido = $this->pedidoPorPagar();
        $cuenta = $pedido->usuario;
        $token = $cuenta->createToken('app')->plainTextToken;

        foreach ([
            ['POST', "/api/pedidos/{$pedido->id_publico}/confirmar"],
            ['POST', "/api/pedidos/{$pedido->id_publico}/pago"],
            ['PATCH', "/api/pedidos/{$pedido->id_publico}"],
            ['PUT', "/api/pedidos/{$pedido->id_publico}"],
        ] as [$metodo, $direccion]) {
            $this->app['auth']->forgetGuards();
            $respuesta = $this->withToken($token)->json($metodo, $direccion, ['estado' => EstadoPedido::CONFIRMADO, 'pagado' => true]);

            $this->assertContains($respuesta->status(), [404, 405], "{$metodo} {$direccion}");
        }

        // El historial solo consulta: aunque la petición lleve los datos de un pago, no confirma nada.
        $this->app['auth']->forgetGuards();
        $this->withToken($token)
            ->getJson('/api/pedidos?'.http_build_query(['estado' => EstadoPedido::CONFIRMADO, 'pagado' => true]))
            ->assertOk();

        // La calificación tampoco: un pedido que espera su pago no se califica, aunque la petición lleve los datos de un
        // pago (historial-y-calificacion, lectura provisional de BR-25).
        $this->app['auth']->forgetGuards();
        $this->withToken($token)
            ->postJson("/api/pedidos/{$pedido->id_publico}/calificacion", [
                'estrellas' => 5,
                'estado' => EstadoPedido::CONFIRMADO,
                'pagado' => true,
            ])
            ->assertConflict();

        $this->assertNull($pedido->fresh()->folio);
        $this->assertSame(EstadoPedido::POR_PAGAR, $pedido->fresh()->estado->codigo);
    }

    /**
     * Un pedido en el estado previo al pago, creado como lo crea la app al aceptar el resumen, de una cuenta nueva.
     */
    private function pedidoPorPagar(): Pedido
    {
        $cuenta = $this->cuentaDeUsuario();

        $id = $this->aceptarResumen($cuenta, $this->resumen($this->direccionDe($cuenta)))
            ->assertCreated()
            ->json('pedido.id_publico');

        return Pedido::query()->where('id_publico', $id)->sole();
    }

    /**
     * Un pedido de la lavandería en el estado previo al pago, insertado sin la API, para otra plaza.
     */
    private function pedidoDirecto(Lavanderia $lavanderia): Pedido
    {
        return Pedido::create([
            'id_plazas' => $lavanderia->id_plazas,
            'id_usuarios' => $this->cuentaDeUsuario()->getKey(),
            'id_lavanderias' => $lavanderia->getKey(),
            'id_estados_pedido' => EstadoPedido::where('codigo', EstadoPedido::POR_PAGAR)->sole()->getKey(),
            'ventana_inicio' => '2026-12-03 17:00:00',
            'ventana_fin' => '2026-12-03 18:00:00',
        ]);
    }

    /**
     * El generador de folios da estos, en orden, en lugar de los suyos.
     *
     * @param  list<string>  $folios
     */
    private function folios(array $folios): void
    {
        $this->app->instance(FolioDelPedido::class, new class($folios) extends FolioDelPedido
        {
            /** @param  list<string>  $folios */
            public function __construct(private array $folios) {}

            public function nuevo(): string
            {
                return array_shift($this->folios) ?? throw new RuntimeException('Se acabaron los folios de prueba.');
            }
        });
    }
}
