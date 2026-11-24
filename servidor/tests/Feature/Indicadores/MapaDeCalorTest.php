<?php

namespace Tests\Feature\Indicadores;

use App\Filament\Pages\Indicadores;
use App\Filament\Widgets\Indicadores\MapaDeCalor;
use App\Indicadores\PedidosPorColonia;
use App\Indicadores\PedidosPorZona;
use App\Indicadores\Rango;
use App\Models\Rol;
use App\Models\Usuario;
use App\Models\Zona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\AltaLavanderias\Soporte\PanelDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Indicadores\Soporte\IndicadoresDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * El mapa de calor de los pedidos del rango, por colonia o por zona de servicio, a elección de la administración
 * (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5, categoría Geográfico; ADR-0009, decisión 3).
 *
 * - Los agregados se calculan en el servidor, con PostGIS (ADR-0004): un punto por colonia, en el centro de los pedidos
 *   de esa colonia, o cada zona con su número de pedidos. El mapa web de Google solo los dibuja, sin su capa de calor.
 * - La colonia es la que se capturó en la dirección de recolección del pedido (design.md, pregunta 4): no se deduce del
 *   mapa, así que no hay geocodificación inversa (presupuesto-de-mapas.md, decisión 4).
 * - Pedido del rango es el que llegó a Confirmado en el rango, en el día de su plaza (lectura 1).
 * - La llave de Google Maps JavaScript del panel sale de la configuración. Sin ella, el mapa no se monta y el panel dice
 *   por qué, como la app sin la llave de Android; las cifras se ven igual en la tabla.
 *
 * La plaza de prueba tiene dos zonas: Centro, el cuadrado de un grado con su esquina suroeste en (-120, 10), y Norte,
 * el de arriba, con su esquina en (-120, 11). Todo es ficticio y está en el océano Pacífico.
 *
 * Escenarios: indicadores-E19 e indicadores-E20 · Spec: openspec/changes/indicadores/specs/indicadores/spec.md · Tareas
 * 5.1 y 5.2
 */
class MapaDeCalorTest extends TestCase
{
    use AgendaDePrueba;
    use IndicadoresDePrueba;
    use LavanderiasDePrueba;
    use PanelDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    /** La semana del lunes 30 de noviembre al domingo 6 de diciembre de 2026, por día. */
    private const SEMANA = ['desde' => '2026-11-30', 'hasta' => '2026-12-06', 'granularidad' => 'dia'];

    private const LLAVE_DE_PRUEBA = 'llave-de-prueba-del-panel';

    private Zona $norte;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();
        $this->norte = $this->zonaDePrueba($this->plaza, 'Norte', $this->cuadrado(-120.0, 11.0));
        config(['services.google_maps_panel.llave' => null]);
    }

    #[TestDox('indicadores-E19: con cinco pedidos de la colonia A y dos de la colonia B en el rango, el mapa por colonia trae un punto por colonia, con 5 y 2 pedidos, y el de 5 con más intensidad')]
    public function test_indicadores_e19_un_punto_por_colonia_con_su_numero_de_pedidos_y_su_intensidad(): void
    {
        $this->sembrarLasColonias();

        $puntos = array_values(app(PedidosPorColonia::class)->delRango(Rango::deLosFiltros(self::SEMANA)));

        $this->assertSame(['Colonia A de prueba', 'Colonia B de prueba'], array_column($puntos, 'colonia'));
        $this->assertSame([5, 2], array_column($puntos, 'pedidos'));
        $this->assertSame([1.0, 0.4], array_column($puntos, 'intensidad'));
        $this->assertGreaterThan($puntos[1]['intensidad'], $puntos[0]['intensidad']);
        $this->assertSame(['Municipio de prueba', 'Municipio de prueba'], array_column($puntos, 'municipio'));
        $this->assertSame([$this->plaza->nombre, $this->plaza->nombre], array_column($puntos, 'plaza'));

        // El punto de cada colonia es el centro de sus pedidos, calculado en el servidor.
        $this->assertEqualsWithDelta(10.2, $puntos[0]['latitud'], 0.0001);
        $this->assertEqualsWithDelta(-119.8, $puntos[0]['longitud'], 0.0001);
        $this->assertEqualsWithDelta(10.8, $puntos[1]['latitud'], 0.0001);
        $this->assertEqualsWithDelta(-119.2, $puntos[1]['longitud'], 0.0001);
    }

    #[TestDox('indicadores-E19: el mapa por colonia no hace ninguna geocodificación inversa: no sale ninguna petición a Google')]
    public function test_indicadores_e19_no_se_hace_ninguna_geocodificacion_inversa(): void
    {
        $this->sembrarLasColonias();
        config(['services.google_maps_panel.llave' => self::LLAVE_DE_PRUEBA]);
        $this->comoAdministracion();
        Http::preventStrayRequests();
        Http::fake();

        $this->mapa()->assertOk()->assertSee('Colonia A de prueba');
        $this->get('/admin/indicadores?'.http_build_query(['filters' => self::SEMANA]))->assertOk();

        Http::assertNothingSent();
    }

    #[TestDox('indicadores-E19: con la llave de la configuración, el widget monta el mapa con los puntos de cada colonia, su número de pedidos y su intensidad')]
    public function test_indicadores_e19_con_la_llave_el_widget_monta_el_mapa_con_los_puntos(): void
    {
        $this->sembrarLasColonias();
        config(['services.google_maps_panel.llave' => self::LLAVE_DE_PRUEBA]);
        $this->comoAdministracion();

        $mapa = $this->mapa()
            ->assertSee('Mapa de calor de pedidos')
            ->assertSee('data-llave="'.self::LLAVE_DE_PRUEBA.'"', false)
            ->assertDontSee('El mapa no está disponible');

        $datos = $this->datosDelMapa($mapa);
        $this->assertSame('colonia', $datos['agrupacion']);
        $this->assertSame([5, 2], array_column($datos['puntos'], 'pedidos'));
        $this->assertSame([1.0, 0.4], array_column($datos['puntos'], 'intensidad'));
        $this->assertSame(['Colonia A de prueba', 'Colonia B de prueba'], array_column($datos['puntos'], 'nombre'));
        $this->assertSame([], $datos['zonas']);

        // La tabla da las mismas cifras, para leerlas sin el mapa.
        $mapa->assertSeeInOrder(['Colonia A de prueba', '5', 'Colonia B de prueba', '2']);
        $this->assertContains(MapaDeCalor::class, (new Indicadores)->getWidgets());

        // La página lleva el script que carga Google Maps JavaScript con la llave del contenedor. Livewire lo manda entre
        // los efectos del componente, en JSON.
        $this->get('/admin/indicadores?'.http_build_query(['filters' => self::SEMANA]))
            ->assertOk()
            ->assertSee('maps.googleapis.com', false)
            ->assertSee('alCargarLosMapasDelPanel', false)
            ->assertSee('data-llave="'.self::LLAVE_DE_PRUEBA.'"', false);
    }

    #[TestDox('indicadores-E19: sin la llave de Google Maps del panel, el mapa no se monta y el panel lo dice, y las cifras se ven en la tabla')]
    public function test_indicadores_e19_sin_la_llave_el_mapa_no_se_monta_y_lo_dice(): void
    {
        $this->sembrarLasColonias();
        $this->comoAdministracion();

        $this->mapa()
            ->assertSee('El mapa no está disponible porque falta la llave de Google Maps del panel.')
            ->assertDontSee('data-llave', false)
            ->assertDontSee('maps.googleapis.com')
            ->assertSeeInOrder(['Colonia A de prueba', '5', 'Colonia B de prueba', '2']);
    }

    #[TestDox('indicadores-E19: cuenta la colonia de la recolección, solo los pedidos confirmados del rango en el día de su plaza, y junta la misma colonia aunque se escriba con espacios de más')]
    public function test_indicadores_e19_cuenta_la_colonia_de_la_recoleccion_y_solo_los_pedidos_del_rango(): void
    {
        $colonia = fn (string $nombre, float $latitud = 10.5, float $longitud = -119.5): array => [
            'colonia' => $nombre,
            'ubicacion' => ['latitud' => $latitud, 'longitud' => $longitud],
        ];
        // La recolección en la colonia A y la entrega en la C: cuenta en la A.
        $this->pedidoConfirmadoEl('2026-12-01 10:00:00', recogida: $colonia('Colonia A de prueba'), entrega: $colonia('Colonia C de prueba'));
        $this->pedidoConfirmadoEl('2026-12-02 10:00:00', recogida: $colonia('  Colonia A de prueba '));
        // El domingo 6 a las 23:30 de la plaza, que en UTC ya es el lunes 7: es del rango.
        $this->pedidoConfirmadoEl('2026-12-06 23:30:00', recogida: $colonia('Colonia A de prueba'));
        // Fuera del rango, y uno que nunca se pagó.
        $this->pedidoConfirmadoEl('2026-12-07 10:00:00', recogida: $colonia('Colonia B de prueba'));
        $this->pedidoSinPagarEl('2026-12-03 10:00:00', recogida: $colonia('Colonia B de prueba'));

        $puntos = array_values(app(PedidosPorColonia::class)->delRango(Rango::deLosFiltros(self::SEMANA)));

        $this->assertSame(['Colonia A de prueba'], array_column($puntos, 'colonia'));
        $this->assertSame([3], array_column($puntos, 'pedidos'));
    }

    #[TestDox('indicadores-E20: con pedidos dentro de dos zonas de servicio, el mapa por zona pinta cada zona con su número de pedidos')]
    public function test_indicadores_e20_cada_zona_se_pinta_con_su_numero_de_pedidos(): void
    {
        $this->sembrarLasZonas();

        $zonas = app(PedidosPorZona::class)->delRango(Rango::deLosFiltros(self::SEMANA));

        $this->assertSame(['Centro', 'Norte'], array_column(array_values($zonas), 'zona'));
        $this->assertSame([3, 1], array_column(array_values($zonas), 'pedidos'));
        $this->assertSame([1.0, 0.33], array_column(array_values($zonas), 'intensidad'));
        $this->assertSame([$this->plaza->nombre, $this->plaza->nombre], array_column(array_values($zonas), 'plaza'));

        // Cada zona lleva su polígono para pintarla, en GeoJSON.
        foreach ($zonas as $zona) {
            $this->assertSame('MultiPolygon', $zona['geometria']['type']);
        }
    }

    #[TestDox('indicadores-E20: la administración elige ver el mapa por zona: el widget pinta las zonas con su número de pedidos, y una zona activa sin pedidos sale con 0')]
    public function test_indicadores_e20_la_administracion_elige_ver_el_mapa_por_zona(): void
    {
        $this->sembrarLasZonas();
        $this->zonaDePrueba($this->plaza, 'Sur', $this->cuadrado(-120.0, 9.0));
        // Una zona inactiva sin pedidos no se pinta.
        $this->zonaDePrueba($this->plaza, 'Oeste', $this->cuadrado(-121.0, 10.0), activa: false);
        config(['services.google_maps_panel.llave' => self::LLAVE_DE_PRUEBA]);
        $this->comoAdministracion();

        $mapa = $this->mapa()->set('agrupacion', 'zona');

        $datos = $this->datosDelMapa($mapa);
        $this->assertSame('zona', $datos['agrupacion']);
        $this->assertSame(['Centro', 'Norte', 'Sur'], array_column($datos['zonas'], 'nombre'));
        $this->assertSame([3, 1, 0], array_column($datos['zonas'], 'pedidos'));
        $this->assertSame([], $datos['puntos']);
        $mapa->assertSeeInOrder(['Centro', '3', 'Norte', '1', 'Sur', '0'])->assertDontSee('Oeste');

        // Una agrupación que no existe vuelve a la de colonia.
        $this->assertSame('colonia', $this->datosDelMapa($this->mapa()->set('agrupacion', 'otra'))['agrupacion']);
    }

    #[TestDox('indicadores-E20: un pedido cuenta en la zona que cubre su punto de recolección, con PostGIS, y no en la de su entrega; solo los pedidos confirmados del rango')]
    public function test_indicadores_e20_el_pedido_cuenta_en_la_zona_que_cubre_su_recoleccion(): void
    {
        $centro = ['ubicacion' => ['latitud' => 10.5, 'longitud' => -119.5]];
        $norte = ['ubicacion' => ['latitud' => 11.5, 'longitud' => -119.5]];
        // La recolección en Centro y la entrega en Norte: cuenta en Centro.
        $this->pedidoConfirmadoEl('2026-12-02 10:00:00', recogida: $centro, entrega: $norte);
        $this->pedidoConfirmadoEl('2026-12-03 10:00:00', recogida: $norte);
        // Fuera del rango, y uno que nunca se pagó.
        $this->pedidoConfirmadoEl('2026-12-07 10:00:00', recogida: $norte);
        $this->pedidoSinPagarEl('2026-12-04 10:00:00', recogida: $norte);

        $zonas = array_values(app(PedidosPorZona::class)->delRango(Rango::deLosFiltros(self::SEMANA)));

        $this->assertSame(['Centro', 'Norte'], array_column($zonas, 'zona'));
        $this->assertSame([1, 1], array_column($zonas, 'pedidos'));
    }

    #[TestDox('indicadores-E19 e indicadores-E20: una cuenta que no es de administración no ve el mapa de calor')]
    public function test_indicadores_e19_otra_cuenta_no_ve_el_mapa_de_calor(): void
    {
        foreach ([Rol::USUARIO, Rol::LAVANDERIA, Rol::REPARTIDOR] as $rol) {
            $this->actingAs(Usuario::factory()->conRol($rol)->create(), 'web');

            $this->assertFalse(MapaDeCalor::canView(), $rol);
            $this->mapa()->assertForbidden();
        }
    }

    /**
     * Cinco pedidos de la colonia A, cerca de (10.2, -119.8), y dos de la B, cerca de (10.8, -119.2), en el rango.
     */
    private function sembrarLasColonias(): void
    {
        foreach ([[10.1, -119.9], [10.3, -119.7], [10.2, -119.8], [10.1, -119.7], [10.3, -119.9]] as $i => [$latitud, $longitud]) {
            $this->pedidoConfirmadoEl('2026-12-0'.($i + 1).' 10:00:00', recogida: [
                'colonia' => 'Colonia A de prueba',
                'ubicacion' => ['latitud' => $latitud, 'longitud' => $longitud],
            ]);
        }

        foreach ([[10.7, -119.3], [10.9, -119.1]] as $i => [$latitud, $longitud]) {
            $this->pedidoConfirmadoEl('2026-12-0'.($i + 2).' 11:00:00', recogida: [
                'colonia' => 'Colonia B de prueba',
                'ubicacion' => ['latitud' => $latitud, 'longitud' => $longitud],
            ]);
        }
    }

    /**
     * Tres pedidos con la recolección en Centro y uno en Norte, en el rango.
     */
    private function sembrarLasZonas(): void
    {
        foreach (['2026-11-30', '2026-12-02', '2026-12-04'] as $dia) {
            $this->pedidoConfirmadoEl("{$dia} 10:00:00", recogida: ['ubicacion' => ['latitud' => 10.5, 'longitud' => -119.5]]);
        }

        $this->pedidoConfirmadoEl('2026-12-03 10:00:00', recogida: ['ubicacion' => ['latitud' => 11.5, 'longitud' => -119.5]]);
    }

    private function mapa(): Testable
    {
        return $this->widget(MapaDeCalor::class, self::SEMANA);
    }

    /**
     * Lo que el widget le da al mapa: los datos en JSON del atributo data-mapa-datos.
     *
     * @return array{agrupacion: string, puntos: list<array<string, mixed>>, zonas: list<array<string, mixed>>}
     */
    private function datosDelMapa(Testable $mapa): array
    {
        $this->assertSame(1, preg_match('/data-mapa-datos="([^"]*)"/', $mapa->html(), $coincidencia), 'El widget no trae los datos del mapa.');

        return json_decode(html_entity_decode($coincidencia[1], ENT_QUOTES | ENT_HTML5), true, 512, JSON_THROW_ON_ERROR);
    }
}
