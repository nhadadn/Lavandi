<?php

namespace Tests\Feature\AdministracionEnLaApp;

use App\Filament\Widgets\Indicadores\EntregasPorRepartidorTabla;
use App\Filament\Widgets\Indicadores\IngresosBrutosGrafica;
use App\Filament\Widgets\Indicadores\MontoPromedioIndicador;
use App\Filament\Widgets\Indicadores\PedidosPorEstadoGrafica;
use App\Filament\Widgets\Indicadores\PedidosTotalesTabla;
use App\Filament\Widgets\Indicadores\RankingDeLavanderiasTabla;
use App\Filament\Widgets\Indicadores\RetencionTabla;
use App\Filament\Widgets\Indicadores\UsuariosActivosGrafica;
use App\Finanzas\Ingresos;
use App\Finanzas\Pesos;
use App\Indicadores\CortesDelSemaforo;
use App\Indicadores\EntregasPorRepartidor;
use App\Indicadores\MontoPromedio;
use App\Indicadores\PedidosPorEstado;
use App\Indicadores\PedidosTotales;
use App\Indicadores\Rango;
use App\Indicadores\RankingDeLavanderias;
use App\Indicadores\Refresco;
use App\Indicadores\RetencionPorMesDeRegistro;
use App\Indicadores\Semaforo;
use App\Indicadores\Tendencia;
use App\Indicadores\UsuariosActivos;
use App\Models\Calificacion;
use App\Models\Lavanderia;
use App\Models\Pedido;
use App\Models\Repartidor;
use App\Models\Rol;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Database\Factories\RepartidorFactory;
use Filament\Widgets\TableWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Administracion\Soporte\PagosDelPeriodoDePrueba;
use Tests\Feature\AdministracionEnLaApp\Soporte\ContratosDeLaApp;
use Tests\Feature\AdministracionEnLaApp\Soporte\SesionesDeLaApp;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\AltaLavanderias\Soporte\PanelDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Estados\Soporte\EstadosDePrueba;
use Tests\Feature\Indicadores\Soporte\IndicadoresDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\Feature\Repartidor\Soporte\RepartidorDePrueba;
use Tests\TestCase;

/**
 * Los indicadores del A.5 en la API de la administración (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5;
 * NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.4; openspec/changes/administracion-en-la-app/, tareas 17.1 y 17.2):
 * GET /api/administracion/indicadores/{categoria}, una categoría del A.5 por llamada, con el rango y la granularidad que
 * valen para las cinco.
 *
 * La ruta llama a las mismas clases que los widgets del panel (App\Indicadores y App\Finanzas\Ingresos), con el rango de
 * App\Indicadores\Rango y el intervalo de App\Indicadores\Refresco, y la prueba compara su respuesta con esas clases, y
 * con el panel, para los mismos datos (ADR-0022, decisión 9). Las reglas de cada cifra las fijan indicadores-E01 a E24,
 * que prueban sin cambios los tests de tests/Feature/Indicadores/. El mapa de calor, la categoría geografico, tiene su
 * propia prueba: MapaDeCalorTest.
 *
 * Cada pedido pasa por el camino real: se crea al aceptar el resumen y lo confirma la pasarela simulada
 * (Tests\Feature\Indicadores\Soporte\IndicadoresDePrueba). Los tokens son los reales de la app (Soporte\SesionesDeLaApp),
 * nunca Sanctum::actingAs(). Todo es ficticio: las cifras que esperan las pruebas salen de las filas que ellas siembran.
 *
 * Las lavanderías activas frente a registradas (F-I06) y las comisiones (F-I10) esperan DD-083, DD-020 y DD-013
 * (administracion-app-E54): van en espera, sin cifra. Lo que espera respuesta en los indicadores que ya tienen cifra
 * (administracion-app-E55) sigue la regla de hoy de su clase.
 *
 * Escenarios: administracion-app-E45, administracion-app-E46, administracion-app-E47, administracion-app-E48,
 * administracion-app-E49, administracion-app-E51, administracion-app-E52, administracion-app-E53,
 * administracion-app-E11, administracion-app-E06 y administracion-app-E07 · Spec:
 * openspec/changes/administracion-en-la-app/specs/administracion-en-la-app/spec.md · Tareas 17.1 y 17.2
 */
class IndicadoresTest extends TestCase
{
    use AgendaDePrueba;
    use ContratosDeLaApp;
    use EstadosDePrueba;
    use IndicadoresDePrueba;
    use LavanderiasDePrueba;
    use PagosDelPeriodoDePrueba;
    use PanelDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;
    use RepartidorDePrueba;
    use SesionesDeLaApp;

    private const INDICADORES = '/api/administracion/indicadores/';

    /** La ruta de los indicadores en los contratos con la app. */
    private const PETICION = 'GET /api/administracion/indicadores/{categoria}';

    /** Las cinco categorías del A.5, en su orden. */
    private const CATEGORIAS = ['operaciones', 'usuarios', 'socios', 'financiero', 'geografico'];

    /** La semana del lunes 30 de noviembre al domingo 6 de diciembre de 2026, por día. */
    private const SEMANA = ['desde' => '2026-11-30', 'hasta' => '2026-12-06', 'granularidad' => 'dia'];

    /** Enero y febrero de 2027, por mes. */
    private const ENERO_Y_FEBRERO = ['desde' => '2027-01-01', 'hasta' => '2027-02-28', 'granularidad' => 'mes'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();
    }

    #[TestDox('administracion-app-E45: sin rango elegido, cada categoría responde el mes en curso hasta hoy, por día, con el hoy de la zona horaria de referencia (indicadores-E24): a las 23:30 del 31 de marzo, que en UTC ya es el 1 de abril, el rango es del 1 al 31 de marzo')]
    public function test_administracion_app_e45_sin_rango_es_el_mes_en_curso_por_dia_con_el_hoy_de_la_zona_de_referencia(): void
    {
        $this->travelTo(CarbonImmutable::parse('2027-03-31 23:30:00', (string) config('app.zona_horaria_sin_plaza')));
        $this->assertSame('2027-04-01', now()->utc()->toDateString());
        $token = $this->tokenDeLaApp($this->cuentaDeAdministracion());

        foreach (self::CATEGORIAS as $categoria) {
            $respuesta = $this->indicadores($token, $categoria)->assertOk();

            $this->assertSame(['categoria', 'rango', 'refresco', 'indicadores'], array_keys($respuesta->json()), $categoria);
            $this->assertSame($categoria, $respuesta->json('categoria'));
            $this->assertSame([
                'desde' => '2027-03-01',
                'hasta' => '2027-03-31',
                'granularidad' => 'dia',
                'descripcion' => 'Del 01/03/2027 al 31/03/2027, por día',
            ], $respuesta->json('rango'), $categoria);
            $this->assertEsElRango($respuesta, Rango::deLosFiltros(null));
        }

        $this->assertCount(31, $this->indicadores($token, 'operaciones')->json('indicadores.pedidos_totales.periodos'));
    }

    #[TestDox('administracion-app-E45: con un rango y una granularidad, la respuesta trae el rango ajustado a periodos completos, como el panel: por semana, del lunes de la primera al domingo de la última; por mes, del día 1 al último día; y vale para las cinco categorías')]
    public function test_administracion_app_e45_la_respuesta_trae_el_rango_ajustado_a_periodos_completos(): void
    {
        $token = $this->tokenDeLaApp($this->cuentaDeAdministracion());
        $porSemana = ['desde' => '2026-11-25', 'hasta' => '2026-12-02', 'granularidad' => 'semana'];
        $porMes = ['desde' => '2026-10-15', 'hasta' => '2026-12-02', 'granularidad' => 'mes'];

        foreach (self::CATEGORIAS as $categoria) {
            $semana = $this->indicadores($token, $categoria, $porSemana)->assertOk();
            $this->assertSame([
                'desde' => '2026-11-23',
                'hasta' => '2026-12-06',
                'granularidad' => 'semana',
                'descripcion' => 'Del 23/11/2026 al 06/12/2026, por semana',
            ], $semana->json('rango'), $categoria);
            $this->assertEsElRango($semana, Rango::deLosFiltros($porSemana));

            $mes = $this->indicadores($token, $categoria, $porMes)->assertOk();
            $this->assertSame([
                'desde' => '2026-10-01',
                'hasta' => '2026-12-31',
                'granularidad' => 'mes',
                'descripcion' => 'Del 01/10/2026 al 31/12/2026, por mes',
            ], $mes->json('rango'), $categoria);
        }

        $this->assertSame(
            ['Semana del 23/11/2026', 'Semana del 30/11/2026'],
            array_column($this->indicadores($token, 'operaciones', $porSemana)->json('indicadores.pedidos_totales.periodos'), 'periodo'),
        );
    }

    #[TestDox('administracion-app-E46, administracion-app-E52 y administracion-app-E11: con pedidos confirmados en varios días de la semana, unos activos y uno entregado, la pestaña Operaciones da los pedidos totales de cada día junto a los del anterior y los pedidos por estado, los mismos de App\Indicadores y del panel para esa semana')]
    public function test_administracion_app_e46_los_indicadores_de_operaciones(): void
    {
        $this->sembrarOperaciones();
        $token = $this->tokenDeLaApp($this->cuentaDeAdministracion());

        $respuesta = $this->indicadores($token, 'operaciones', self::SEMANA)->assertOk();

        $this->assertSame(['pedidos_totales', 'pedidos_por_estado'], array_keys($respuesta->json('indicadores')));
        $periodos = $respuesta->json('indicadores.pedidos_totales.periodos');
        $this->assertSame(
            ['2026-11-30', '2026-12-01', '2026-12-02', '2026-12-03', '2026-12-04', '2026-12-05', '2026-12-06'],
            array_column($periodos, 'inicio'),
        );
        // El domingo 29, el día anterior al primero, va junto al lunes 30; el lunes 7, fuera del rango, no cuenta.
        $this->assertSame([1, 2, 0, 1, 0, 0, 0], array_column($periodos, 'pedidos'));
        $this->assertSame([1, 1, 2, 0, 1, 0, 0], array_column($periodos, 'pedidos_anterior'));
        $this->assertSame(
            ['inicio' => '2026-11-30', 'periodo' => 'lun. 30/11/2026', 'pedidos' => 1, 'periodo_anterior' => 'dom. 29/11/2026', 'pedidos_anterior' => 1],
            $periodos[0],
        );
        $this->assertSame(['activos' => 3, 'completados' => 1], $respuesta->json('indicadores.pedidos_por_estado'));

        // Las mismas cifras que dan las clases de dominio para ese rango.
        $rango = Rango::deLosFiltros(self::SEMANA);
        $this->assertSame($this->conSuClave(app(PedidosTotales::class)->porPeriodo($rango), 'inicio'), $periodos);
        $this->assertSame(app(PedidosPorEstado::class)->delRango($rango), $respuesta->json('indicadores.pedidos_por_estado'));

        // Y las del panel, con su sesión web, para los mismos filtros.
        $this->comoAdministracion();
        $this->assertSame($this->sinSuClave($periodos, 'inicio'), $this->filasDeLaTabla(PedidosTotalesTabla::class, self::SEMANA));
        $this->assertSame(
            [$respuesta->json('indicadores.pedidos_por_estado.activos'), $respuesta->json('indicadores.pedidos_por_estado.completados')],
            $this->datosDeLaGrafica($this->widget(PedidosPorEstadoGrafica::class, self::SEMANA))['datasets'][0]['data'],
        );
    }

    #[TestDox('administracion-app-E47, administracion-app-E52 y administracion-app-E11: con usuarios con actividad, registros de noviembre con sus pedidos y pedidos con monto en la semana y en la anterior, la pestaña Usuarios da los usuarios activos de cada día, los que vuelven a pedir por mes de registro y el monto promedio con su tendencia, los mismos de App\Indicadores y del panel')]
    public function test_administracion_app_e47_los_indicadores_de_usuarios(): void
    {
        $this->sembrarUsuarios();
        $token = $this->tokenDeLaApp($this->cuentaDeAdministracion());

        $respuesta = $this->indicadores($token, 'usuarios', self::SEMANA)->assertOk();

        $this->assertSame(['usuarios_activos', 'vuelven_a_pedir', 'monto_promedio'], array_keys($respuesta->json('indicadores')));

        // F-I03: una persona cuenta una vez por día.
        $activos = $respuesta->json('indicadores.usuarios_activos.periodos');
        $this->assertSame([1, 2, 0, 1, 0, 0, 0], array_column($activos, 'personas'));
        $this->assertSame(['inicio' => '2026-11-30', 'periodo' => 'lun. 30/11/2026', 'personas' => 1], $activos[0]);

        // F-I04: los tres de noviembre pidieron y uno volvió; en diciembre nadie se registró y pidió.
        $this->assertSame([
            ['inicio' => '2026-11-01', 'mes' => 'noviembre 2026', 'con_primer_pedido' => 3, 'porcentaje' => 33.3],
            ['inicio' => '2026-12-01', 'mes' => 'diciembre 2026', 'con_primer_pedido' => 0, 'porcentaje' => null],
        ], $respuesta->json('indicadores.vuelven_a_pedir.meses'));

        // F-I05: 250 y 300 en la semana, 200 y 200 en la anterior.
        $this->assertSame([
            'promedio' => '275.00',
            'promedio_texto' => '$275.00',
            'promedio_anterior' => '200.00',
            'promedio_anterior_texto' => '$200.00',
            'diferencia' => '75.00',
            'tendencia' => 'subio',
            'tendencia_texto' => 'Subió',
        ], $respuesta->json('indicadores.monto_promedio'));

        // Las mismas cifras que dan las clases de dominio para ese rango.
        $rango = Rango::deLosFiltros(self::SEMANA);
        $this->assertSame($this->conSuClave(app(UsuariosActivos::class)->porPeriodo($rango), 'inicio'), $activos);
        $this->assertSame(
            $this->conSuClave(app(RetencionPorMesDeRegistro::class)->porMes($rango), 'inicio'),
            $respuesta->json('indicadores.vuelven_a_pedir.meses'),
        );
        $monto = app(MontoPromedio::class)->delRango($rango);
        $this->assertSame($monto['promedio'], $respuesta->json('indicadores.monto_promedio.promedio'));
        $this->assertSame($monto['promedio_anterior'], $respuesta->json('indicadores.monto_promedio.promedio_anterior'));
        $this->assertSame($monto['diferencia'], $respuesta->json('indicadores.monto_promedio.diferencia'));
        $this->assertSame(Tendencia::Subio, $monto['tendencia']);
        $this->assertSame($monto['tendencia']->getLabel(), $respuesta->json('indicadores.monto_promedio.tendencia_texto'));

        // Y las del panel, con su sesión web, para los mismos filtros.
        $this->comoAdministracion();
        $this->assertSame(
            array_column($activos, 'personas'),
            $this->datosDeLaGrafica($this->widget(UsuariosActivosGrafica::class, self::SEMANA))['datasets'][0]['data'],
        );
        $this->assertSame(
            $this->sinSuClave($respuesta->json('indicadores.vuelven_a_pedir.meses'), 'inicio'),
            $this->filasDeLaTabla(RetencionTabla::class, self::SEMANA),
        );
        $destacado = $this->widget(MontoPromedioIndicador::class, self::SEMANA)->instance();
        [$stat] = (fn (): array => $this->getStats())->call($destacado);
        $this->assertSame($respuesta->json('indicadores.monto_promedio.promedio_texto'), $stat->getValue());
        $this->assertSame(
            "Subió \$75.00 frente al periodo anterior ({$respuesta->json('indicadores.monto_promedio.promedio_anterior_texto')})",
            $stat->getDescription(),
        );
    }

    #[TestDox('administracion-app-E47: sin pedidos con monto, el monto promedio va sin cifra ni tendencia, como lo da App\Indicadores\MontoPromedio, sin inventar un cero')]
    public function test_administracion_app_e47_sin_pedidos_con_monto_no_hay_promedio_ni_tendencia(): void
    {
        $token = $this->tokenDeLaApp($this->cuentaDeAdministracion());

        $this->assertSame([
            'promedio' => null,
            'promedio_texto' => null,
            'promedio_anterior' => null,
            'promedio_anterior_texto' => null,
            'diferencia' => null,
            'tendencia' => null,
            'tendencia_texto' => null,
        ], $this->indicadores($token, 'usuarios', self::SEMANA)->assertOk()->json('indicadores.monto_promedio'));
    }

    #[TestDox('administracion-app-E48, administracion-app-E52 y administracion-app-E11: con lavanderías con calificaciones y una sin ellas, dos cortes del semáforo en la configuración y repartidores con entregas en la semana, la pestaña Socios da el ranking con su semáforo, cada color con su texto, y las entregas de cada repartidor, los mismos de App\Indicadores y del panel')]
    public function test_administracion_app_e48_los_indicadores_de_socios(): void
    {
        $this->cortes('4.5', '4.0');
        $lavanderias = $this->sembrarElRanking();
        [$dos, $una] = $this->sembrarLasEntregas();
        $token = $this->tokenDeLaApp($this->cuentaDeAdministracion());

        $respuesta = $this->indicadores($token, 'socios', self::SEMANA)->assertOk();

        $this->assertSame(
            ['activas_frente_a_registradas', 'calificacion_por_lavanderia', 'entregas_por_repartidor'],
            array_keys($respuesta->json('indicadores')),
        );

        // F-I07: el ranking, con el semáforo de los dos cortes; cada color con su texto. Las cifras de los cortes no van:
        // las fija EL CLIENTE (DD-114) y la app no las muestra (imágenes 46 y 47 de la Propuesta 6).
        $ranking = $respuesta->json('indicadores.calificacion_por_lavanderia');
        $this->assertSame(['semaforo_configurado', 'lavanderias'], array_keys($ranking));
        $this->assertNotNull(CortesDelSemaforo::deLaConfiguracion());
        $this->assertTrue($ranking['semaforo_configurado']);
        $this->assertSame([
            ['id_publico' => $lavanderias['alta']->id_publico, 'lugar' => 1, 'lavanderia' => 'Lavandería de cuatro ocho', 'promedio' => 4.8, 'semaforo' => 'verde', 'semaforo_texto' => 'Verde'],
            ['id_publico' => $lavanderias['media']->id_publico, 'lugar' => 2, 'lavanderia' => 'Lavandería de cuatro dos', 'promedio' => 4.2, 'semaforo' => 'amarillo', 'semaforo_texto' => 'Amarillo'],
            ['id_publico' => $lavanderias['baja']->id_publico, 'lugar' => 3, 'lavanderia' => 'Lavandería de tres nueve', 'promedio' => 3.9, 'semaforo' => 'rojo', 'semaforo_texto' => 'Rojo'],
        ], array_slice($ranking['lavanderias'], 0, 3));
        // Las que no tienen calificaciones van al final, sin lugar, sin promedio y sin color.
        foreach (array_slice($ranking['lavanderias'], 3) as $sinCalificar) {
            $this->assertSame([null, null, null, null], [$sinCalificar['lugar'], $sinCalificar['promedio'], $sinCalificar['semaforo'], $sinCalificar['semaforo_texto']]);
        }

        // F-I08: cada repartidor con sus entregas de la semana, lado a lado.
        $this->assertSame([
            ['id_publico' => $dos->id_publico, 'repartidor' => 'Repartidor de prueba con dos', 'plaza' => $this->plaza->nombre, 'entregas' => 2],
            ['id_publico' => $una->id_publico, 'repartidor' => 'Repartidor de prueba con una', 'plaza' => $this->plaza->nombre, 'entregas' => 1],
        ], $respuesta->json('indicadores.entregas_por_repartidor.repartidores'));

        // Las mismas cifras que dan las clases de dominio.
        $this->assertSame($this->rankingComoElDominio(app(RankingDeLavanderias::class)->calcular()), $ranking['lavanderias']);
        $this->assertSame(
            $this->conSuClave(app(EntregasPorRepartidor::class)->delRango(Rango::deLosFiltros(self::SEMANA)), 'id_publico'),
            $respuesta->json('indicadores.entregas_por_repartidor.repartidores'),
        );

        // Y las del panel, con su sesión web.
        $this->comoAdministracion();
        $panel = $this->widget(RankingDeLavanderiasTabla::class)->instance()->getTableRecords();
        $this->assertSame(array_column($ranking['lavanderias'], 'id_publico'), array_map('strval', $panel->keys()->all()));
        $this->assertSame(array_column($ranking['lavanderias'], 'promedio'), $panel->pluck('promedio')->all());
        $this->assertSame(
            array_column($ranking['lavanderias'], 'semaforo'),
            $panel->pluck('semaforo')->map(fn (?Semaforo $color): ?string => $color?->value)->all(),
        );
        $this->assertSame(
            $this->sinSuClave($respuesta->json('indicadores.entregas_por_repartidor.repartidores'), 'id_publico'),
            $this->filasDeLaTabla(EntregasPorRepartidorTabla::class, self::SEMANA),
        );
    }

    #[TestDox('administracion-app-E48: sin los dos cortes del semáforo en la configuración (DD-114), el ranking va sin semáforo configurado y sin color, como el "Sin cortes configurados" del panel')]
    public function test_administracion_app_e48_sin_cortes_el_ranking_va_sin_semaforo(): void
    {
        $this->cortes(null, null);
        $lavanderias = $this->sembrarElRanking();
        $token = $this->tokenDeLaApp($this->cuentaDeAdministracion());

        $ranking = $this->indicadores($token, 'socios', self::SEMANA)->assertOk()->json('indicadores.calificacion_por_lavanderia');

        $this->assertNull(CortesDelSemaforo::deLaConfiguracion());
        $this->assertFalse($ranking['semaforo_configurado']);
        $this->assertSame($lavanderias['alta']->id_publico, $ranking['lavanderias'][0]['id_publico']);
        $this->assertSame(4.8, $ranking['lavanderias'][0]['promedio']);
        $this->assertSame([null], array_values(array_unique(array_column($ranking['lavanderias'], 'semaforo'))));
        $this->assertSame([null], array_values(array_unique(array_column($ranking['lavanderias'], 'semaforo_texto'))));
    }

    #[TestDox('administracion-app-E49, administracion-app-E52 y administracion-app-E11: con pagos confirmados en enero y en febrero, la pestaña Financiero por mes da los ingresos brutos de cada mes, y cada cifra es la misma de App\Finanzas\Ingresos::porPeriodo(), la clase de la pantalla de ingresos, y la del panel')]
    public function test_administracion_app_e49_los_ingresos_brutos(): void
    {
        $this->sembrarEneroYFebrero();
        $token = $this->tokenDeLaApp($this->cuentaDeAdministracion());

        $respuesta = $this->indicadores($token, 'financiero', self::ENERO_Y_FEBRERO)->assertOk();

        $this->assertSame(['ingresos_brutos', 'comisiones'], array_keys($respuesta->json('indicadores')));
        $this->assertSame([
            ['inicio' => '2027-01-01', 'periodo' => 'enero 2027', 'pagos' => 2, 'ingresos' => '350.00', 'ingresos_texto' => '$350.00'],
            ['inicio' => '2027-02-01', 'periodo' => 'febrero 2027', 'pagos' => 1, 'ingresos' => '125.50', 'ingresos_texto' => '$125.50'],
        ], $respuesta->json('indicadores.ingresos_brutos.periodos'));

        // La misma cifra que da App\Finanzas\Ingresos::porPeriodo() para el mismo rango, la clase que usará
        // GET /api/administracion/ingresos (tarea 13.1, que no está en esta rama). La comparación entre las dos rutas
        // entra con esa tarea.
        $porPeriodo = app(Ingresos::class)->porPeriodo(Rango::deLosFiltros(self::ENERO_Y_FEBRERO));
        $comoElDominio = [];
        foreach ($porPeriodo as $inicio => $periodo) {
            $comoElDominio[] = ['inicio' => $inicio, ...$periodo, 'ingresos_texto' => Pesos::texto($periodo['ingresos'])];
        }
        $this->assertSame($comoElDominio, $respuesta->json('indicadores.ingresos_brutos.periodos'));

        // Cada mes por separado da la misma cifra que su barra.
        foreach ([['2027-01-01', '2027-01-31', 0], ['2027-02-01', '2027-02-28', 1]] as [$desde, $hasta, $barra]) {
            $mes = $this->indicadores($token, 'financiero', $this->filtros($desde, $hasta, 'mes'))->json('indicadores.ingresos_brutos.periodos');
            $this->assertSame([$respuesta->json("indicadores.ingresos_brutos.periodos.{$barra}")], $mes, "{$desde} a {$hasta}");
        }

        // Y las barras del panel, con su sesión web.
        $this->comoAdministracion();
        $this->assertSame(
            [350.0, 125.5],
            $this->datosDeLaGrafica($this->widget(IngresosBrutosGrafica::class, self::ENERO_Y_FEBRERO))['datasets'][0]['data'],
        );
    }

    #[TestDox('administracion-app-E51: la respuesta trae el intervalo de indicadores.refresco, el mismo del panel: 30 s por omisión, el que cargue la configuración, y ninguno si no sirve')]
    public function test_administracion_app_e51_la_respuesta_trae_el_intervalo_de_refresco(): void
    {
        $token = $this->tokenDeLaApp($this->cuentaDeAdministracion());

        // El de la configuración de la prueba, el mismo que lee el panel.
        $this->assertSame(Refresco::intervalo(), $this->indicadores($token, 'operaciones')->assertOk()->json('refresco'));

        foreach (['30s' => '30s', '17s' => '17s', '1500ms' => '1500ms', 'no' => null, '0s' => null] as $configurado => $esperado) {
            config(['indicadores.refresco' => $configurado]);
            $this->assertSame($esperado, Refresco::intervalo(), $configurado);

            foreach (self::CATEGORIAS as $categoria) {
                $this->assertSame($esperado, $this->indicadores($token, $categoria)->assertOk()->json('refresco'), "{$configurado} · {$categoria}");
            }
        }
    }

    #[TestDox('administracion-app-E51: con la pestaña Operaciones abierta, se confirma un pedido nuevo y la siguiente consulta lo cuenta en los pedidos totales y en los pedidos por estado, sin que la administración haga nada más que volver a consultar (indicadores-E21)')]
    public function test_administracion_app_e51_la_siguiente_consulta_cuenta_el_pedido_nuevo(): void
    {
        $this->pedidoConfirmadoEl(self::JUEVES_DE_PRUEBA.' 08:00:00');
        $token = $this->tokenDeLaApp($this->cuentaDeAdministracion());
        $jueves = $this->filtros(self::JUEVES_DE_PRUEBA, self::JUEVES_DE_PRUEBA);

        $antes = $this->indicadores($token, 'operaciones', $jueves)->assertOk();
        $this->assertSame(1, $antes->json('indicadores.pedidos_totales.periodos.0.pedidos'));
        $this->assertSame(['activos' => 1, 'completados' => 0], $antes->json('indicadores.pedidos_por_estado'));

        $this->pedidoConfirmadoEl(self::JUEVES_DE_PRUEBA.' 08:30:00');

        $despues = $this->indicadores($token, 'operaciones', $jueves)->assertOk();
        $this->assertSame(2, $despues->json('indicadores.pedidos_totales.periodos.0.pedidos'));
        $this->assertSame(['activos' => 2, 'completados' => 0], $despues->json('indicadores.pedidos_por_estado'));
    }

    #[TestDox('administracion-app-E52: con una base sin pedidos, pagos, actividad ni calificaciones, cada categoría da ceros y listas vacías que salen de esa base, nunca cifras de ejemplo')]
    public function test_administracion_app_e52_sin_datos_las_cifras_salen_de_la_base_vacia(): void
    {
        $token = $this->tokenDeLaApp($this->cuentaDeAdministracion());

        $operaciones = $this->indicadores($token, 'operaciones', self::SEMANA)->assertOk();
        $this->assertSame([0], array_values(array_unique(array_column($operaciones->json('indicadores.pedidos_totales.periodos'), 'pedidos'))));
        $this->assertSame(['activos' => 0, 'completados' => 0], $operaciones->json('indicadores.pedidos_por_estado'));

        $usuarios = $this->indicadores($token, 'usuarios', self::SEMANA)->assertOk();
        $this->assertSame([0], array_values(array_unique(array_column($usuarios->json('indicadores.usuarios_activos.periodos'), 'personas'))));
        $this->assertSame([0], array_values(array_unique(array_column($usuarios->json('indicadores.vuelven_a_pedir.meses'), 'con_primer_pedido'))));

        $socios = $this->indicadores($token, 'socios', self::SEMANA)->assertOk();
        $this->assertSame([], $socios->json('indicadores.entregas_por_repartidor.repartidores'));
        // La lavandería de prueba no tiene calificaciones: sale sin lugar ni promedio.
        $this->assertSame([[null, null]], array_map(
            fn (array $fila): array => [$fila['lugar'], $fila['promedio']],
            $socios->json('indicadores.calificacion_por_lavanderia.lavanderias'),
        ));

        $financiero = $this->indicadores($token, 'financiero', self::SEMANA)->assertOk();
        $this->assertSame(['0.00'], array_values(array_unique(array_column($financiero->json('indicadores.ingresos_brutos.periodos'), 'ingresos'))));
    }

    #[TestDox('administracion-app-E53: con lavanderías registradas y pagos confirmados, las lavanderías activas frente a registradas (F-I06) y las comisiones por socio y en total (F-I10) van en su lugar, en espera de su respuesta, sin ninguna cifra')]
    public function test_administracion_app_e53_f_i06_y_f_i10_van_en_espera_sin_cifra(): void
    {
        Lavanderia::factory()->count(2)->create();
        $this->pagadoEl('2026-12-01 10:00:00', '250.00');
        $token = $this->tokenDeLaApp($this->cuentaDeAdministracion());

        $socios = $this->indicadores($token, 'socios', self::SEMANA)->assertOk();
        $financiero = $this->indicadores($token, 'financiero', self::SEMANA)->assertOk();

        $this->assertSame(['en_espera' => ['DD-083']], $socios->json('indicadores.activas_frente_a_registradas'));
        $this->assertSame(['en_espera' => ['DD-020', 'DD-013']], $financiero->json('indicadores.comisiones'));
        // Las demás cifras de la categoría sí salen: la espera es solo de esos dos.
        $this->assertSame('250.00', $financiero->json('indicadores.ingresos_brutos.periodos.1.ingresos'));
        $this->assertCount(3, $socios->json('indicadores.calificacion_por_lavanderia.lavanderias'));
    }

    #[TestDox('administracion-app-E11: una categoría que no es del A.5 responde 404 sin datos; las cinco del A.5, en minúsculas y sin acento, responden')]
    public function test_administracion_app_e11_una_categoria_que_no_existe_responde_404(): void
    {
        $this->pagadoEl('2026-12-01 10:00:00', '250.00');
        $token = $this->tokenDeLaApp($this->cuentaDeAdministracion());

        foreach (['comisiones', 'Operaciones', 'geográfico', 'descargas', (string) Pedido::query()->sole()->id_publico] as $categoria) {
            $this->indicadores($token, $categoria, self::SEMANA)
                ->assertNotFound()
                ->assertExactJson(['message' => 'No existe la categoría.']);
        }

        foreach (self::CATEGORIAS as $categoria) {
            $this->indicadores($token, $categoria, self::SEMANA)->assertOk();
        }
    }

    #[TestDox('administracion-app-E11: un filtro que no se entiende se toma como no dado y un rango al revés se voltea, como en el panel (App\Indicadores\Rango): la API no tiene reglas propias y nunca responde 422')]
    public function test_administracion_app_e11_los_filtros_son_los_del_panel(): void
    {
        $this->pedidoConfirmadoEl('2026-12-01 10:00:00');
        $this->travelTo(CarbonImmutable::parse('2026-12-18 12:00:00', (string) config('app.zona_horaria_sin_plaza')));
        $token = $this->tokenDeLaApp($this->cuentaDeAdministracion());

        $alReves = ['desde' => '2026-12-06', 'hasta' => '2026-11-30', 'granularidad' => 'dia'];
        $respuesta = $this->indicadores($token, 'operaciones', $alReves)->assertOk();
        $this->assertSame($this->indicadores($token, 'operaciones', self::SEMANA)->json(), $respuesta->json());
        $this->assertEsElRango($respuesta, Rango::deLosFiltros($alReves));

        // Una granularidad que no existe es por día; un día que no se entiende, o que no llega como texto, no se da.
        $sinEntender = ['desde' => 'no es una fecha', 'hasta' => ['2026-12-10'], 'granularidad' => 'anio'];
        $respuesta = $this->indicadores($token, 'operaciones', $sinEntender)->assertOk();
        $this->assertSame(['2026-12-01', '2026-12-18', 'dia'], [
            $respuesta->json('rango.desde'),
            $respuesta->json('rango.hasta'),
            $respuesta->json('rango.granularidad'),
        ]);
        $this->assertEsElRango($respuesta, Rango::deLosFiltros(null));
        $this->assertSame('dia', $this->indicadores($token, 'usuarios', ['granularidad' => ['mes']])->assertOk()->json('rango.granularidad'));
        // Lo que no es un filtro del rango no cuenta, aunque su nombre sea un número.
        $this->assertSame(
            $this->indicadores($token, 'operaciones', self::SEMANA)->json(),
            $this->indicadores($token, 'operaciones', [...self::SEMANA, 'otro' => 'mes', 0 => 'semana'])->assertOk()->json(),
        );
    }

    #[TestDox('administracion-app-E06 y administracion-app-E07: los indicadores de cada categoría solo los ve la administración con su token: las cuentas de usuario, de lavandería y de repartidor, y un token de administración sin la habilidad, reciben 403 sin ninguna cifra; sin sesión o con el token vencido, 401')]
    public function test_administracion_app_e06_y_e07_los_indicadores_solo_los_ve_la_administracion(): void
    {
        $this->pagadoEl('2026-12-01 10:00:00', '250.00');

        foreach (self::CATEGORIAS as $categoria) {
            $this->assertSoloEntraLaAdministracion('GET', self::INDICADORES.$categoria, self::SEMANA);
        }
    }

    #[TestDox('administracion-app-E46: el contrato de la pestaña Operaciones con la app: el rango, el intervalo y los pedidos totales y por estado')]
    public function test_administracion_app_e46_el_contrato_de_operaciones(): void
    {
        $this->sembrarOperaciones();
        $token = $this->tokenDeLaApp($this->cuentaDeAdministracion());

        $this->assertCumpleSuContrato('indicadores.operaciones', self::PETICION, $this->indicadores($token, 'operaciones', self::SEMANA));
    }

    #[TestDox('administracion-app-E47: el contrato de la pestaña Usuarios con la app: con pedidos con monto, el promedio y su tendencia; sin pedidos, el promedio, la tendencia y el porcentaje en null')]
    public function test_administracion_app_e47_el_contrato_de_usuarios(): void
    {
        $token = $this->tokenDeLaApp($this->cuentaDeAdministracion());
        $this->assertCumpleSuContrato('indicadores.usuarios-sin-pedidos', self::PETICION, $this->indicadores($token, 'usuarios', self::SEMANA));

        $this->sembrarUsuarios();
        $token = $this->tokenDeLaApp($this->cuentaDeAdministracion());

        $this->assertCumpleSuContrato('indicadores.usuarios', self::PETICION, $this->indicadores($token, 'usuarios', self::SEMANA));
    }

    #[TestDox('administracion-app-E48 y administracion-app-E53: el contrato de la pestaña Socios con la app: F-I06 en espera de su DD y sin cifra, el ranking con semaforo_configurado y sin las cifras de los cortes (DD-114), y las entregas; sin cortes, semaforo_configurado en false y sin colores')]
    public function test_administracion_app_e48_el_contrato_de_socios(): void
    {
        $this->cortes('4.5', '4.0');
        $this->sembrarElRanking();
        $this->sembrarLasEntregas();
        $token = $this->tokenDeLaApp($this->cuentaDeAdministracion());

        $socios = $this->indicadores($token, 'socios', self::SEMANA);
        $this->assertCumpleSuContrato('indicadores.socios', self::PETICION, $socios);
        $socios->assertJsonPath('indicadores.activas_frente_a_registradas', ['en_espera' => ['DD-083']])
            ->assertJsonMissingPath('indicadores.calificacion_por_lavanderia.cortes_del_semaforo');

        $this->cortes(null, null);
        $this->assertCumpleSuContrato('indicadores.socios-sin-cortes', self::PETICION, $this->indicadores($token, 'socios', self::SEMANA));
    }

    #[TestDox('administracion-app-E49 y administracion-app-E53: el contrato de la pestaña Financiero con la app: los ingresos brutos de cada periodo y F-I10 en espera de sus DD, sin cifra')]
    public function test_administracion_app_e49_el_contrato_de_financiero(): void
    {
        $this->sembrarEneroYFebrero();
        $token = $this->tokenDeLaApp($this->cuentaDeAdministracion());

        $this->assertCumpleSuContrato('indicadores.financiero', self::PETICION, $this->indicadores($token, 'financiero', self::ENERO_Y_FEBRERO));
    }

    #[TestDox('administracion-app-E45: el contrato de una categoría que no existe con la app: el 404 con su mensaje')]
    public function test_administracion_app_e45_el_contrato_de_una_categoria_que_no_existe(): void
    {
        $token = $this->tokenDeLaApp($this->cuentaDeAdministracion());

        $this->assertCumpleSuContrato('indicadores.no-existe', self::PETICION, $this->indicadores($token, 'comisiones'), 404);
    }

    /**
     * La llamada a los indicadores de una categoría con ese token y esos filtros.
     *
     * @param  array<string, mixed>  $filtros
     */
    private function indicadores(string $token, string $categoria, array $filtros = []): TestResponse
    {
        return $this->llamarConToken($token, 'GET', self::INDICADORES.$categoria, $filtros);
    }

    /**
     * El rango de la respuesta es el de App\Indicadores\Rango para esos filtros, con su descripción.
     */
    private function assertEsElRango(TestResponse $respuesta, Rango $rango): void
    {
        $this->assertSame([
            'desde' => $rango->desde->toDateString(),
            'hasta' => $rango->hasta->toDateString(),
            'granularidad' => $rango->granularidad->value,
            'descripcion' => $rango->descripcion(),
        ], $respuesta->json('rango'));
    }

    /**
     * Las filas de una clase de dominio, en su orden, con su clave en un campo con ese nombre: como las da la API.
     *
     * @param  array<string, array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    private function conSuClave(array $filas, string $campo): array
    {
        $lista = [];

        foreach ($filas as $clave => $fila) {
            $lista[] = [$campo => (string) $clave, ...$fila];
        }

        return $lista;
    }

    /**
     * Las filas de la API sin el campo de su clave: como las tiene la tabla del panel.
     *
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    private function sinSuClave(array $filas, string $campo): array
    {
        return array_map(fn (array $fila): array => array_diff_key($fila, [$campo => true]), $filas);
    }

    /**
     * El ranking de App\Indicadores\RankingDeLavanderias como lo da la API: cada color con su valor y su texto.
     *
     * @param  array<string, array{lugar: int|null, lavanderia: string, promedio: float|null, semaforo: Semaforo|null}>  $ranking
     * @return list<array<string, mixed>>
     */
    private function rankingComoElDominio(array $ranking): array
    {
        return array_map(fn (array $fila): array => [
            ...array_diff_key($fila, ['semaforo' => true]),
            'semaforo' => $fila['semaforo']?->value,
            'semaforo_texto' => $fila['semaforo']?->getLabel(),
        ], $this->conSuClave($ranking, 'id_publico'));
    }

    /**
     * Las filas de una tabla de indicadores del panel, montada con esos filtros, en su orden.
     *
     * @param  class-string<TableWidget>  $tabla
     * @param  array<string, string>  $filtros
     * @return list<array<string, mixed>>
     */
    private function filasDeLaTabla(string $tabla, array $filtros): array
    {
        return $this->widget($tabla, $filtros)->instance()->getTableRecords()
            ->map(fn (array $fila): array => array_diff_key($fila, ['__key' => true]))
            ->values()
            ->all();
    }

    /**
     * Pedidos confirmados el domingo 29 (el día anterior a la semana), uno el lunes 30, dos el martes 1 y uno el jueves 3,
     * y uno el lunes 7, fuera de la semana. Uno de los del martes se entrega ese mismo día.
     */
    private function sembrarOperaciones(): void
    {
        $this->pedidoConfirmadoEl('2026-11-29 10:00:00');
        $this->pedidoConfirmadoEl('2026-11-30 10:00:00');
        $this->entregaDe($this->repartidorLlamado('Repartidor de prueba'), $this->pedidoConfirmadoEl('2026-12-01 08:00:00'), '2026-12-01 12:00');
        $this->pedidoConfirmadoEl('2026-12-01 13:00:00');
        $this->pedidoConfirmadoEl('2026-12-03 10:00:00');
        $this->pedidoConfirmadoEl('2026-12-07 10:00:00');
    }

    /**
     * Tres cuentas de usuario registradas en noviembre: la primera pide el martes 24 de noviembre por 200 y el martes 1
     * de diciembre por 250; la segunda, el jueves 26 de noviembre por 200; la tercera, el miércoles 2 de diciembre por 300.
     * La primera tiene actividad el lunes 30, el martes 1 y el jueves 3; la segunda, el martes 1.
     */
    private function sembrarUsuarios(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-10 12:00:00', self::ZONA_HORARIA));
        [$primera, $segunda, $tercera] = Usuario::factory()->count(3)->conRol(Rol::USUARIO)->create()->all();

        $this->pedidoConMonto('2026-11-24 10:00:00', '200.00', $primera);
        $this->pedidoConMonto('2026-12-01 10:00:00', '250.00', $primera);
        $this->pedidoConMonto('2026-11-26 10:00:00', '200.00', $segunda);
        $this->pedidoConMonto('2026-12-02 10:00:00', '300.00', $tercera);

        foreach (['2026-11-30', '2026-12-01', '2026-12-03'] as $dia) {
            DB::table('actividad_usuarios')->insert(['id_usuarios' => $primera->getKey(), 'fecha' => $dia]);
        }

        DB::table('actividad_usuarios')->insert(['id_usuarios' => $segunda->getKey(), 'fecha' => '2026-12-01']);
    }

    /**
     * Un pedido de la cuenta confirmado a esa hora de la plaza, con ese monto. Nada calcula todavía el monto de un pedido
     * (DD-012 y DD-010): la prueba lo escribe en la base, y en los dos campos, para no afirmar cuál cuenta
     * (indicadores-E10), como MontoPromedioTest.
     */
    private function pedidoConMonto(string $fechaYHora, string $monto, Usuario $cuenta): Pedido
    {
        $pedido = $this->pedidoConfirmadoEl($fechaYHora, $cuenta);
        DB::table('pedidos')->where('id_pedidos', $pedido->getKey())->update(['monto_estimado' => $monto, 'monto_final' => $monto]);

        return $pedido;
    }

    /**
     * Los dos cortes del semáforo de la configuración (DD-114): aquí, los de la prueba, ficticios.
     */
    private function cortes(?string $alto, ?string $bajo): void
    {
        config(['indicadores.semaforo.corte_alto' => $alto, 'indicadores.semaforo.corte_bajo' => $bajo]);
    }

    /**
     * Tres lavanderías con promedios de 4.8, 3.9 y 4.2, y una sin calificaciones, como IndicadoresDeSociosTest. La
     * lavandería de prueba de prepararPedido() tampoco tiene calificaciones.
     *
     * @return array<string, Lavanderia>
     */
    private function sembrarElRanking(): array
    {
        $lavanderias = [
            'alta' => Lavanderia::factory()->create(['nombre' => 'Lavandería de cuatro ocho']),
            'baja' => Lavanderia::factory()->create(['nombre' => 'Lavandería de tres nueve']),
            'media' => Lavanderia::factory()->create(['nombre' => 'Lavandería de cuatro dos']),
            'sin' => Lavanderia::factory()->create(['nombre' => 'Lavandería sin calificar']),
        ];

        foreach (['alta' => [5, 5, 5, 5, 4], 'baja' => [4, 4, 4, 4, 4, 4, 4, 4, 4, 3], 'media' => [5, 4, 4, 4, 4]] as $cual => $estrellas) {
            foreach ($estrellas as $numero) {
                Calificacion::create([
                    'id_usuarios' => Usuario::factory()->conRol(Rol::USUARIO)->create()->getKey(),
                    'id_lavanderias' => $lavanderias[$cual]->getKey(),
                    'estrellas' => $numero,
                ]);
            }
        }

        return $lavanderias;
    }

    /**
     * Un repartidor con dos entregas en la semana y otro con una, marcadas Entregado con la operación real.
     *
     * @return array{Repartidor, Repartidor}
     */
    private function sembrarLasEntregas(): array
    {
        $dos = $this->repartidorLlamado('Repartidor de prueba con dos');
        $una = $this->repartidorLlamado('Repartidor de prueba con una');

        foreach (['2026-11-30', '2026-12-02'] as $dia) {
            $this->entregaDe($dos, $this->pedidoConfirmadoEl("{$dia} 08:00:00"), "{$dia} 12:00");
        }

        $this->entregaDe($una, $this->pedidoConfirmadoEl('2026-12-04 08:00:00'), '2026-12-04 12:00');

        return [$dos, $una];
    }

    /**
     * Un repartidor activo de la plaza de prueba, con ese nombre en su cuenta.
     */
    private function repartidorLlamado(string $nombre): Repartidor
    {
        return Repartidor::factory()
            ->en($this->plaza)
            ->deLaCuenta(RepartidorFactory::cuentaDeRepartidor(['nombre' => $nombre]))
            ->create();
    }

    /**
     * Enero: un pago de 150 el día 10 y otro de 200 a las 23:45 del día 31, en la hora de la plaza; una orden de 900 el
     * día 20 que la pasarela nunca confirmó. Febrero: un pago de 125.50 el día 14. Como IngresosYComisionesTest.
     */
    private function sembrarEneroYFebrero(): void
    {
        $this->pagadoEl('2027-01-10 10:00:00', '150.00');
        $this->pagadoEl('2027-01-31 23:45:00', '200.00');
        $this->pagadoEl('2027-02-14 10:00:00', '125.50');
        $this->ordenSinConfirmarEl('2027-01-20 10:00:00', '900.00');
    }
}
