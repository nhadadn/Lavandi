<?php

namespace Tests\Feature\AdministracionEnLaApp;

use App\Filament\Widgets\Indicadores\MapaDeCalor;
use App\Indicadores\Intensidad;
use App\Indicadores\PedidosPorColonia;
use App\Indicadores\PedidosPorZona;
use App\Indicadores\Rango;
use App\Models\Zona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\AdministracionEnLaApp\Soporte\ContratosDeLaApp;
use Tests\Feature\AdministracionEnLaApp\Soporte\SesionesDeLaApp;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\AltaLavanderias\Soporte\PanelDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Indicadores\Soporte\IndicadoresDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * El mapa de calor de los pedidos del rango en la API de la administración, la categoría geografico de
 * GET /api/administracion/indicadores/{categoria} (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5, categoría Geográfico:
 * "Mapa interactivo"; openspec/changes/administracion-en-la-app/, tarea 17.3).
 *
 * - Por colonia, un punto por colonia con sus pedidos, su intensidad y su centro; por zona de servicio, cada zona con sus
 *   pedidos, su intensidad y su polígono en GeoJSON, como al panel. Los dos con el color de App\Indicadores\Intensidad.
 * - La ruta llama a las mismas clases que el widget del mapa del panel, App\Indicadores\PedidosPorColonia y
 *   App\Indicadores\PedidosPorZona, y la prueba compara su respuesta con esas clases, y con el panel, para los mismos
 *   datos (ADR-0022, decisión 9). Las reglas las fijan indicadores-E19 e indicadores-E20, que prueba sin cambios
 *   tests/Feature/Indicadores/MapaDeCalorTest.php.
 * - Dibujarlo, con el mapa de Apple en iPhone y el de Google en Android, y sin la llave de Android solo la tabla, es de
 *   la app (tarea 18.6).
 *
 * La plaza de prueba tiene dos zonas: Centro, el cuadrado de un grado con su esquina suroeste en (-120, 10), y Norte,
 * el de arriba, con su esquina en (-120, 11). Todo es ficticio y está en el océano Pacífico. Los tokens son los reales
 * de la app (Soporte\SesionesDeLaApp), nunca Sanctum::actingAs().
 *
 * Escenarios: administracion-app-E50, administracion-app-E52, administracion-app-E11, administracion-app-E06 y
 * administracion-app-E07 · Spec: openspec/changes/administracion-en-la-app/specs/administracion-en-la-app/spec.md ·
 * Tarea 17.3
 */
class MapaDeCalorTest extends TestCase
{
    use AgendaDePrueba;
    use ContratosDeLaApp;
    use IndicadoresDePrueba;
    use LavanderiasDePrueba;
    use PanelDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;
    use SesionesDeLaApp;

    private const GEOGRAFICO = '/api/administracion/indicadores/geografico';

    /** La semana del lunes 30 de noviembre al domingo 6 de diciembre de 2026, por día. */
    private const SEMANA = ['desde' => '2026-11-30', 'hasta' => '2026-12-06', 'granularidad' => 'dia'];

    private Zona $norte;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();
        $this->norte = $this->zonaDePrueba($this->plaza, 'Norte', $this->cuadrado(-120.0, 11.0));
    }

    #[TestDox('administracion-app-E50 y administracion-app-E11: con cinco pedidos de la colonia A y dos de la colonia B en el rango, la administración ve el mapa por colonia: un punto por colonia, con 5 y 2 pedidos, el de 5 más intenso y más oscuro, con su centro, los mismos de App\Indicadores\PedidosPorColonia')]
    public function test_administracion_app_e50_por_colonia_un_punto_por_colonia(): void
    {
        $this->sembrarLasColoniasYLasZonas();
        $token = $this->tokenDeLaApp($this->cuentaDeAdministracion());

        $respuesta = $this->geografico($token, [...self::SEMANA, 'agrupacion' => 'colonia'])->assertOk();

        $this->assertSame('geografico', $respuesta->json('categoria'));
        $this->assertSame(['mapa_de_calor'], array_keys($respuesta->json('indicadores')));
        $this->assertSame(['agrupacion', 'colonias'], array_keys($respuesta->json('indicadores.mapa_de_calor')));
        $this->assertSame('colonia', $respuesta->json('indicadores.mapa_de_calor.agrupacion'));

        $colonias = $respuesta->json('indicadores.mapa_de_calor.colonias');
        $this->assertSame(['Colonia A de prueba', 'Colonia B de prueba'], array_column($colonias, 'colonia'));
        $this->assertSame([5, 2], array_column($colonias, 'pedidos'));
        $this->assertEquals([1.0, 0.4], array_column($colonias, 'intensidad'));
        // Más pedidos, más oscuro: el último paso de la rampa para la de 5, uno más claro para la de 2.
        $this->assertSame([Intensidad::RAMPA[4], Intensidad::RAMPA[2]], array_column($colonias, 'color'));
        $this->assertSame(['Municipio de prueba', 'Municipio de prueba'], array_column($colonias, 'municipio'));
        $this->assertSame([$this->plaza->nombre, $this->plaza->nombre], array_column($colonias, 'plaza'));
        // El centro de los pedidos de cada colonia, calculado en el servidor.
        $this->assertEqualsWithDelta(10.2, $colonias[0]['latitud'], 0.0001);
        $this->assertEqualsWithDelta(-119.8, $colonias[0]['longitud'], 0.0001);
        $this->assertEqualsWithDelta(11.8, $colonias[1]['latitud'], 0.0001);
        $this->assertEqualsWithDelta(-119.2, $colonias[1]['longitud'], 0.0001);

        // Las mismas cifras que da la clase de dominio, con el color de su intensidad, y sin llaves internas.
        $this->assertEquals(
            array_map(fn (array $colonia): array => [
                'colonia' => $colonia['colonia'],
                'municipio' => $colonia['municipio'],
                'plaza' => $colonia['plaza'],
                'pedidos' => $colonia['pedidos'],
                'intensidad' => $colonia['intensidad'],
                'color' => Intensidad::color($colonia['intensidad']),
                'latitud' => $colonia['latitud'],
                'longitud' => $colonia['longitud'],
            ], array_values(app(PedidosPorColonia::class)->delRango(Rango::deLosFiltros(self::SEMANA)))),
            $colonias,
        );

        // La respuesta no trae la llave interna de cada colonia, que lleva el id_plazas: las colonias van en lista.
        $this->assertTrue(array_is_list($colonias));
        $this->assertStringNotContainsString('"'.$this->plaza->getKey().'|', (string) $respuesta->getContent());

        // Sin agrupación, el mapa es por colonia.
        $this->assertSame($respuesta->json(), $this->geografico($token, self::SEMANA)->json());
    }

    #[TestDox('administracion-app-E50 y administracion-app-E11: con pedidos dentro de dos zonas de servicio, la administración elige ver por zona: cada zona con su número de pedidos, su color y su geometría en GeoJSON, los mismos de App\Indicadores\PedidosPorZona; una zona activa sin pedidos sale con 0 y una inactiva sin pedidos no sale')]
    public function test_administracion_app_e50_por_zona_cada_zona_pintada_con_su_numero_de_pedidos(): void
    {
        $this->sembrarLasColoniasYLasZonas();
        $this->zonaDePrueba($this->plaza, 'Sur', $this->cuadrado(-120.0, 9.0));
        $this->zonaDePrueba($this->plaza, 'Oeste', $this->cuadrado(-121.0, 10.0), activa: false);
        $token = $this->tokenDeLaApp($this->cuentaDeAdministracion());

        $respuesta = $this->geografico($token, [...self::SEMANA, 'agrupacion' => 'zona'])->assertOk();

        $this->assertSame(['agrupacion', 'zonas'], array_keys($respuesta->json('indicadores.mapa_de_calor')));
        $this->assertSame('zona', $respuesta->json('indicadores.mapa_de_calor.agrupacion'));

        $zonas = $respuesta->json('indicadores.mapa_de_calor.zonas');
        $this->assertSame(['Centro', 'Norte', 'Sur'], array_column($zonas, 'zona'));
        $this->assertSame([5, 2, 0], array_column($zonas, 'pedidos'));
        $this->assertEquals([1.0, 0.4, 0.0], array_column($zonas, 'intensidad'));
        $this->assertSame([Intensidad::RAMPA[4], Intensidad::RAMPA[2], Intensidad::RAMPA[0]], array_column($zonas, 'color'));
        $this->assertSame(array_fill(0, 3, $this->plaza->nombre), array_column($zonas, 'plaza'));
        $this->assertSame(['zona', 'plaza', 'pedidos', 'intensidad', 'color', 'geometria'], array_keys($zonas[0]));

        // Cada zona con su polígono en GeoJSON, el mismo que recibe el panel.
        $delDominio = array_values(app(PedidosPorZona::class)->delRango(Rango::deLosFiltros(self::SEMANA)));
        foreach ($zonas as $i => $zona) {
            $this->assertSame('MultiPolygon', $zona['geometria']['type']);
            $this->assertEquals($delDominio[$i]['geometria'], $zona['geometria'], $zona['zona']);
            $this->assertEquals(Intensidad::color($delDominio[$i]['intensidad']), $zona['color']);
        }
        $this->assertSame(array_column($delDominio, 'pedidos'), array_column($zonas, 'pedidos'));

        // La respuesta no trae la llave interna de la zona, su id_zonas: las zonas van en lista.
        $this->assertTrue(array_is_list($zonas));
        $this->assertStringNotContainsString('"'.$this->norte->getKey().'":', (string) $respuesta->getContent());
    }

    #[TestDox('administracion-app-E50 y administracion-app-E52: por colonia y por zona, las cifras, la intensidad y el color de cada punto y de cada zona son los mismos que le da al mapa el widget del panel para ese rango')]
    public function test_administracion_app_e50_las_cifras_son_las_del_mapa_del_panel(): void
    {
        $this->sembrarLasColoniasYLasZonas();
        $token = $this->tokenDeLaApp($this->cuentaDeAdministracion());
        $porColonia = $this->geografico($token, [...self::SEMANA, 'agrupacion' => 'colonia'])->json('indicadores.mapa_de_calor.colonias');
        $porZona = $this->geografico($token, [...self::SEMANA, 'agrupacion' => 'zona'])->json('indicadores.mapa_de_calor.zonas');

        $this->comoAdministracion();
        $delPanel = fn (string $agrupacion): array => (fn (): array => $this->getViewData())
            ->call($this->widget(MapaDeCalor::class, self::SEMANA)->set('agrupacion', $agrupacion)->instance())['datos'];

        $puntos = $delPanel('colonia')['puntos'];
        $this->assertSame(array_column($puntos, 'nombre'), array_column($porColonia, 'colonia'));
        $this->assertSame(array_column($puntos, 'pedidos'), array_column($porColonia, 'pedidos'));
        $this->assertEquals(array_column($puntos, 'intensidad'), array_column($porColonia, 'intensidad'));
        $this->assertSame(array_column($puntos, 'color'), array_column($porColonia, 'color'));
        $this->assertEquals(array_column($puntos, 'latitud'), array_column($porColonia, 'latitud'));
        $this->assertEquals(array_column($puntos, 'longitud'), array_column($porColonia, 'longitud'));

        $zonas = $delPanel('zona')['zonas'];
        $this->assertSame(array_column($zonas, 'nombre'), array_column($porZona, 'zona'));
        $this->assertSame(array_column($zonas, 'pedidos'), array_column($porZona, 'pedidos'));
        $this->assertSame(array_column($zonas, 'color'), array_column($porZona, 'color'));
        $this->assertEquals(array_column($zonas, 'geometria'), array_column($porZona, 'geometria'));
    }

    #[TestDox('administracion-app-E50 y administracion-app-E11: una agrupación que no es colonia ni zona, o que no llega como texto, se toma como por colonia, como en el panel; sin pedidos en el rango, las colonias van vacías y las zonas activas con 0')]
    public function test_administracion_app_e50_otra_agrupacion_es_por_colonia_y_sin_pedidos_no_hay_puntos(): void
    {
        $token = $this->tokenDeLaApp($this->cuentaDeAdministracion());

        foreach (['otra', 'Zona', ['zona']] as $agrupacion) {
            $this->assertSame(
                ['agrupacion' => 'colonia', 'colonias' => []],
                $this->geografico($token, [...self::SEMANA, 'agrupacion' => $agrupacion])->assertOk()->json('indicadores.mapa_de_calor'),
            );
        }

        $zonas = $this->geografico($token, [...self::SEMANA, 'agrupacion' => 'zona'])->assertOk()->json('indicadores.mapa_de_calor.zonas');
        $this->assertSame(['Centro', 'Norte'], array_column($zonas, 'zona'));
        $this->assertSame([0, 0], array_column($zonas, 'pedidos'));
    }

    #[TestDox('administracion-app-E06 y administracion-app-E07: el mapa de calor, por colonia y por zona, solo lo ve la administración con su token: las cuentas de usuario, de lavandería y de repartidor, y un token de administración sin la habilidad, reciben 403 sin ninguna cifra; sin sesión o con el token vencido, 401')]
    public function test_administracion_app_e06_y_e07_el_mapa_de_calor_solo_lo_ve_la_administracion(): void
    {
        $this->sembrarLasColoniasYLasZonas();

        $this->assertSoloEntraLaAdministracion('GET', self::GEOGRAFICO, [...self::SEMANA, 'agrupacion' => 'colonia']);
        $this->assertSoloEntraLaAdministracion('GET', self::GEOGRAFICO, [...self::SEMANA, 'agrupacion' => 'zona']);
    }

    #[TestDox('administracion-app-E50: el contrato del mapa de calor con la app: por colonia, cada una con su color y su centro; por zona, cada una con su color y su geometría en GeoJSON')]
    public function test_administracion_app_e50_el_contrato_del_mapa_de_calor(): void
    {
        $this->sembrarLasColoniasYLasZonas();
        $token = $this->tokenDeLaApp($this->cuentaDeAdministracion());
        $peticion = 'GET /api/administracion/indicadores/{categoria}';

        $this->assertCumpleSuContrato('indicadores.geografico-por-colonia', $peticion, $this->geografico($token, [...self::SEMANA, 'agrupacion' => 'colonia']));
        $this->assertCumpleSuContrato('indicadores.geografico-por-zona', $peticion, $this->geografico($token, [...self::SEMANA, 'agrupacion' => 'zona']));
    }

    /**
     * La llamada al mapa de calor con ese token y esos filtros.
     *
     * @param  array<string, mixed>  $filtros
     */
    private function geografico(string $token, array $filtros): TestResponse
    {
        return $this->llamarConToken($token, 'GET', self::GEOGRAFICO, $filtros);
    }

    /**
     * Cinco pedidos de la colonia A, con la recolección cerca de (10.2, -119.8), dentro de Centro; y dos de la colonia B,
     * cerca de (11.8, -119.2), dentro de Norte. Todos en el rango.
     */
    private function sembrarLasColoniasYLasZonas(): void
    {
        foreach ([[10.1, -119.9], [10.3, -119.7], [10.2, -119.8], [10.1, -119.7], [10.3, -119.9]] as $i => [$latitud, $longitud]) {
            $this->pedidoConfirmadoEl('2026-12-0'.($i + 1).' 10:00:00', recogida: [
                'colonia' => 'Colonia A de prueba',
                'ubicacion' => ['latitud' => $latitud, 'longitud' => $longitud],
            ]);
        }

        foreach ([[11.7, -119.3], [11.9, -119.1]] as $i => [$latitud, $longitud]) {
            $this->pedidoConfirmadoEl('2026-12-0'.($i + 2).' 11:00:00', recogida: [
                'colonia' => 'Colonia B de prueba',
                'ubicacion' => ['latitud' => $latitud, 'longitud' => $longitud],
            ]);
        }
    }
}
