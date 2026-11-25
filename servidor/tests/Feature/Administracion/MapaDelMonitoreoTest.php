<?php

namespace Tests\Feature\Administracion;

use App\Autorizacion\Autorizacion;
use App\Cobro\Desarrollo\CobroSimulado;
use App\Filament\Pages\MonitoreoDePedidos;
use App\Models\EstadoPedido;
use App\Models\Pedido;
use App\Models\Rol;
use App\Models\Usuario;
use App\Pedidos\MarcadoresDelMonitoreo;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
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
 * El mapa del monitoreo de pedidos: los pedidos activos en un mapa web, con un marcador en la dirección de recolección de
 * cada pedido y otro en la de entrega cuando es distinta (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #4,
 * "Vista en tiempo real de todos los pedidos activos con mapa"; design.md, lectura 5; BR-30). El mapa no muestra la
 * posición del repartidor: está fuera del contrato (proposal.md).
 *
 * - El mapa es Google Maps JavaScript (ADR-0009, decisión 3), con la llave, el aviso sin llave, el contenedor con
 *   wire:ignore y el cargador del mapa de calor de los indicadores (App\Filament\MapaDelPanel).
 * - Se crea una sola vez al abrir el monitoreo; el refresco de 30 segundos (administracion-E07) cambia solo sus datos,
 *   sin cargar otro mapa (presupuesto-de-mapas.md: "una sesión por turno de trabajo").
 * - Los puntos son los que se guardaron con cada dirección del pedido: el mapa no geocodifica nada
 *   (presupuesto-de-mapas.md, decisión 3).
 * - La entrega es distinta cuando su copia salió de otra dirección guardada que la recolección. Si es la misma, el
 *   pedido lleva un solo marcador, en la recolección, que dice "Recolección y entrega".
 *
 * La plaza de prueba tiene su zona en el cuadrado de un grado con su esquina suroeste en (-120, 10). Todo es ficticio y
 * está en el océano Pacífico.
 *
 * Escenarios: administracion-E09 y administracion-E10 · Spec: openspec/changes/administracion/specs/administracion/
 * spec.md · Tarea 2.4
 */
class MapaDelMonitoreoTest extends TestCase
{
    use AgendaDePrueba;
    use EstadosDePrueba;
    use LavanderiasDePrueba;
    use PanelDePrueba;
    use PedidosDeLaLavanderiaDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    private const LLAVE_DE_PRUEBA = 'llave-de-prueba-del-panel';

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();
        config(['services.google_maps_panel.llave' => null]);
    }

    #[TestDox('administracion-E09: con dos pedidos activos con su recolección guardada con coordenadas, el mapa del monitoreo trae un marcador por pedido en su recolección, y cada marcador lleva el folio y el estado del pedido')]
    public function test_administracion_e09_un_marcador_por_pedido_en_su_recoleccion_con_su_folio_y_su_estado(): void
    {
        $uno = $this->pedidoConfirmadoEn($this->lavanderia, $this->cuentaDeUsuario(), '10:00', latitud: 10.2, longitud: -119.8);
        $dos = $this->pedidoConfirmadoEn($this->lavanderia, $this->cuentaDeUsuario(), '11:00', latitud: 10.7, longitud: -119.3);
        $this->aceptarComoLaLavanderia($dos);
        config(['services.google_maps_panel.llave' => self::LLAVE_DE_PRUEBA]);
        $this->comoAdministracion();

        $monitoreo = Livewire::test(MonitoreoDePedidos::class)
            ->assertOk()
            ->assertSee('Mapa de los pedidos activos')
            ->assertSeeHtml('data-llave="'.self::LLAVE_DE_PRUEBA.'"')
            ->assertSeeHtml('aria-label="Mapa de los pedidos activos"')
            ->assertDontSee('El mapa no está disponible');
        $this->assertContenedorQueLivewireNoToca($monitoreo);

        $marcadores = $this->marcadoresDelMapa($monitoreo);

        // Uno por pedido, de la recolección más próxima a la más lejana, como la tabla.
        $this->assertSame([$uno->id_publico, $dos->id_publico], array_column($marcadores, 'pedido'));
        $this->assertSame([$uno->folio, $dos->folio], array_column($marcadores, 'folio'));
        $this->assertSame(['Confirmado', 'Aceptado'], array_column($marcadores, 'estado'));
        $this->assertSame([$this->plaza->nombre, $this->plaza->nombre], array_column($marcadores, 'plaza'));
        $this->assertSame([MarcadoresDelMonitoreo::RECOLECCION, MarcadoresDelMonitoreo::RECOLECCION], array_column($marcadores, 'tipo'));

        // En el punto guardado con la dirección de recolección.
        $this->assertEqualsWithDelta(10.2, $marcadores[0]['latitud'], 0.000001);
        $this->assertEqualsWithDelta(-119.8, $marcadores[0]['longitud'], 0.000001);
        $this->assertEqualsWithDelta(10.7, $marcadores[1]['latitud'], 0.000001);
        $this->assertEqualsWithDelta(-119.3, $marcadores[1]['longitud'], 0.000001);

        // Al elegir un marcador, su ventana dice el folio y el estado, como texto. Livewire manda el script entre los
        // efectos del componente, con el cargador de los mapas del panel.
        $script = $this->scriptDelMapa($monitoreo);
        $this->assertStringContainsString('cargarLosMapasDelPanel', $script);
        $this->assertStringContainsString('maps.googleapis.com', $script);
        $this->assertStringContainsString('marcador.folio', $script);
        $this->assertStringContainsString('marcador.estado', $script);
        $this->assertStringContainsString('textContent', $script);
        $this->assertStringNotContainsString('innerHTML', $script);

        $this->get('/admin/monitoreo')
            ->assertOk()
            ->assertSee('data-llave="'.self::LLAVE_DE_PRUEBA.'"', false)
            ->assertSee('alCargarLosMapasDelPanel', false)
            ->assertSee($uno->folio);
    }

    #[TestDox('administracion-E09: el mapa se crea una sola vez al abrir el monitoreo: el refresco de 30 segundos cambia sus marcadores sin volver a mandar el script del mapa')]
    public function test_administracion_e09_el_mapa_se_crea_una_vez_y_el_refresco_cambia_solo_sus_datos(): void
    {
        $recolectado = $this->pedidoConfirmadoDe($this->cuentaDeUsuario());
        $this->aceptarComoLaLavanderia($recolectado);
        $this->marcarComoLaLavanderia($recolectado, EstadoPedido::RECOLECTADO);
        $enEntrega = $this->pedidoConfirmadoDe($this->cuentaDeUsuario());
        $this->aceptarComoLaLavanderia($enEntrega);

        foreach ([EstadoPedido::RECOLECTADO, EstadoPedido::EN_PROCESO, EstadoPedido::LISTO, EstadoPedido::EN_ENTREGA] as $boton) {
            $this->marcarComoLaLavanderia($enEntrega, $boton);
        }

        config(['services.google_maps_panel.llave' => self::LLAVE_DE_PRUEBA]);
        $this->comoAdministracion();

        $monitoreo = Livewire::test(MonitoreoDePedidos::class);
        $this->assertStringContainsString('cargarLosMapasDelPanel', $this->scriptDelMapa($monitoreo));
        $this->assertSame(['Recolectado', 'En proceso de entrega'], array_column($this->marcadoresDelMapa($monitoreo), 'estado'));
        $monitoreo->assertSeeHtml('wire:poll.30s');

        // La lavandería mueve un pedido, el repartidor entrega el otro y entra uno nuevo.
        $this->marcarPedido($this->cuentaDeLaLavanderia($recolectado), $recolectado->id_publico, EstadoPedido::EN_PROCESO)->assertOk();
        $this->entregarComoElRepartidor($enEntrega);
        $nuevo = $this->pedidoConfirmadoDe($this->cuentaDeUsuario());

        // El refresco: los marcadores cambian y el script del mapa no vuelve a llegar, así que no se crea otro mapa.
        $this->volverAlPanel();
        $monitoreo->call('$refresh');
        $this->assertContenedorQueLivewireNoToca($monitoreo);

        $this->assertArrayNotHasKey('scripts', $monitoreo->effects);
        $marcadores = $this->marcadoresDelMapa($monitoreo);
        $this->assertSame([$recolectado->folio, $nuevo->folio], array_column($marcadores, 'folio'));
        $this->assertSame(['En proceso', 'Confirmado'], array_column($marcadores, 'estado'));
    }

    #[TestDox('administracion-E09: el mapa usa el punto guardado con cada dirección del pedido: abrir el monitoreo no manda ninguna petición a Google desde el servidor')]
    public function test_administracion_e09_el_mapa_no_geocodifica_nada(): void
    {
        $this->pedidoConfirmadoDe($this->cuentaDeUsuario());
        config(['services.google_maps_panel.llave' => self::LLAVE_DE_PRUEBA]);
        $this->comoAdministracion();
        Http::preventStrayRequests();
        Http::fake();

        $this->get('/admin/monitoreo')->assertOk();
        $this->assertCount(1, $this->marcadoresDelMapa(Livewire::test(MonitoreoDePedidos::class)));

        Http::assertNothingSent();
    }

    #[TestDox('administracion-E09: sin la llave de Google Maps del panel, el mapa no se monta y el monitoreo lo dice, como el mapa de calor, y los pedidos se ven en la tabla')]
    public function test_administracion_e09_sin_la_llave_el_mapa_no_se_monta_y_lo_dice(): void
    {
        $pedido = $this->pedidoConfirmadoDe($this->cuentaDeUsuario());
        $this->comoAdministracion();

        $monitoreo = Livewire::test(MonitoreoDePedidos::class)
            ->assertOk()
            ->assertSee('El mapa no está disponible porque falta la llave de Google Maps del panel. Los pedidos están en la tabla.')
            ->assertDontSeeHtml('data-llave')
            ->assertDontSeeHtml('aria-label="Mapa de los pedidos activos"')
            ->assertCanSeeTableRecords([$pedido]);

        $this->assertArrayNotHasKey('scripts', $monitoreo->effects);
        $this->get('/admin/monitoreo')->assertOk()->assertDontSee('maps.googleapis.com')->assertSee($pedido->folio);
    }

    #[TestDox('administracion-E09: en el mapa entran solo los pedidos activos: ni el entregado ni el que espera su pago; a una cuenta que no es de administración el componente no le da ningún marcador')]
    public function test_administracion_e09_solo_los_pedidos_activos_y_solo_para_la_administracion(): void
    {
        $activo = $this->pedidoConfirmadoDe($this->cuentaDeUsuario());
        $rechazado = $this->pedidoConfirmadoDe($this->cuentaDeUsuario());
        $this->rechazarComoLaLavanderia($rechazado);
        $entregado = $this->pedidoConfirmadoDe($this->cuentaDeUsuario());
        $this->aceptarComoLaLavanderia($entregado);
        $this->llevarHastaEntregado($entregado);
        $this->pedidoPorPagarEn($this->lavanderia, $this->cuentaDeUsuario());
        $administracion = $this->comoAdministracion();

        $marcadores = $this->marcadoresDelMapa(Livewire::test(MonitoreoDePedidos::class));

        // Los mismos que la tabla (administracion-E06): el rechazado sigue activo mientras DD-103 y DD-016 no digan otra cosa.
        $this->assertEqualsCanonicalizing([$activo->id_publico, $rechazado->id_publico], array_column($marcadores, 'pedido'));
        $this->assertNotContains($entregado->id_publico, array_column($marcadores, 'pedido'));

        $autorizacion = app(Autorizacion::class);
        $marcadoresDe = fn (Usuario $cuenta): array => app(MarcadoresDelMonitoreo::class)->deLosPedidos($autorizacion->pedidosActivosDelPanel($cuenta));
        $this->assertCount(2, $marcadoresDe($administracion));

        foreach ([Rol::USUARIO, Rol::LAVANDERIA, Rol::REPARTIDOR] as $rol) {
            $this->assertSame([], $marcadoresDe(Usuario::factory()->conRol($rol)->create()), $rol);
        }
    }

    #[TestDox('administracion-E10: un pedido activo cuya entrega es en otra dirección tiene dos marcadores, uno marcado como recolección y otro como entrega, cada uno en su punto; el que se entrega en la misma dirección tiene uno solo')]
    public function test_administracion_e10_la_entrega_en_otra_direccion_lleva_su_propio_marcador(): void
    {
        $conOtraEntrega = $this->pedidoConEntrega(
            recogida: ['latitud' => 10.2, 'longitud' => -119.8],
            entrega: ['latitud' => 10.8, 'longitud' => -119.2],
        );
        $enLaMisma = $this->pedidoConEntrega(recogida: ['latitud' => 10.5, 'longitud' => -119.5]);
        config(['services.google_maps_panel.llave' => self::LLAVE_DE_PRUEBA]);
        $this->comoAdministracion();

        $monitoreo = Livewire::test(MonitoreoDePedidos::class);
        $marcadores = $this->marcadoresDelMapa($monitoreo);
        $delPrimero = array_values(array_filter($marcadores, fn (array $marcador): bool => $marcador['pedido'] === $conOtraEntrega->id_publico));
        $delSegundo = array_values(array_filter($marcadores, fn (array $marcador): bool => $marcador['pedido'] === $enLaMisma->id_publico));

        // Dos marcadores, la recolección primero, cada uno con su tipo y su texto.
        $this->assertSame([MarcadoresDelMonitoreo::RECOLECCION, MarcadoresDelMonitoreo::ENTREGA], array_column($delPrimero, 'tipo'));
        $this->assertSame(['Recolección', 'Entrega'], array_column($delPrimero, 'etiqueta'));
        $this->assertSame([$conOtraEntrega->folio, $conOtraEntrega->folio], array_column($delPrimero, 'folio'));
        $this->assertEqualsWithDelta(10.2, $delPrimero[0]['latitud'], 0.000001);
        $this->assertEqualsWithDelta(-119.8, $delPrimero[0]['longitud'], 0.000001);
        $this->assertEqualsWithDelta(10.8, $delPrimero[1]['latitud'], 0.000001);
        $this->assertEqualsWithDelta(-119.2, $delPrimero[1]['longitud'], 0.000001);

        // Con la entrega en la misma dirección, un solo marcador en la recolección, que lo dice.
        $this->assertSame([MarcadoresDelMonitoreo::RECOLECCION], array_column($delSegundo, 'tipo'));
        $this->assertSame(['Recolección y entrega'], array_column($delSegundo, 'etiqueta'));

        // Solo recolecciones y entregas: el mapa no trae la posición de ningún repartidor.
        $this->assertSame([], array_diff(array_column($marcadores, 'tipo'), [MarcadoresDelMonitoreo::RECOLECCION, MarcadoresDelMonitoreo::ENTREGA]));

        // Cada marcador lleva su letra en el mapa, y la leyenda dice cuál es cuál.
        $script = $this->scriptDelMapa($monitoreo);
        $this->assertStringContainsString("recoleccion: 'R'", $script);
        $this->assertStringContainsString("entrega: 'E'", $script);
        $monitoreo->assertSee('R: recolección')->assertSee('E: entrega en otra dirección');
    }

    /**
     * Un pedido confirmado de una cuenta nueva, a la lavandería de prueba, con la recogida en ese punto y, si se pasa,
     * la entrega en otra dirección guardada de la cuenta, en el suyo. Sin entrega, se entrega en la misma dirección.
     *
     * @param  array{latitud: float, longitud: float}  $recogida
     * @param  array{latitud: float, longitud: float}|null  $entrega
     */
    private function pedidoConEntrega(array $recogida, ?array $entrega = null): Pedido
    {
        $cuenta = $this->cuentaDeUsuario();
        $id = $this->aceptarResumen($cuenta, $this->resumen(
            $this->direccionDe($cuenta, ['ubicacion' => $recogida]),
            $entrega === null ? null : $this->direccionDe($cuenta, ['calle' => 'Calle de la entrega', 'ubicacion' => $entrega]),
        ))->assertCreated()->json('pedido.id_publico');

        return app(CobroSimulado::class)->pagar(Pedido::query()->where('id_publico', $id)->sole())->fresh();
    }

    /**
     * Lo que la página le da al mapa: los marcadores en JSON del atributo data-mapa-datos.
     *
     * @return list<array<string, mixed>>
     */
    private function marcadoresDelMapa(Testable $monitoreo): array
    {
        $this->assertSame(1, preg_match('/data-mapa-datos="([^"]*)"/', $monitoreo->html(), $coincidencia), 'El monitoreo no trae los datos del mapa.');

        return json_decode(html_entity_decode($coincidencia[1], ENT_QUOTES | ENT_HTML5), true, 512, JSON_THROW_ON_ERROR)['marcadores'];
    }

    /**
     * El contenedor del mapa, con wire:ignore: Livewire no lo toca al refrescar, así que el mapa que vive en él no se
     * vuelve a crear.
     */
    private function assertContenedorQueLivewireNoToca(Testable $monitoreo): void
    {
        $this->assertMatchesRegularExpression('/<div\s+wire:ignore\s+data-mapa\s+data-llave="[^"]+"/', $monitoreo->html());
    }

    /**
     * El script del mapa, de los efectos con que Livewire lo manda la primera vez que se dibuja la página.
     */
    private function scriptDelMapa(Testable $monitoreo): string
    {
        $scripts = $monitoreo->effects['scripts'] ?? [];
        $this->assertNotEmpty($scripts, 'El monitoreo no manda el script del mapa.');

        return implode("\n", $scripts);
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
}
