<?php

namespace Tests\Feature\AdministracionEnLaApp;

use App\Filament\Widgets\Finanzas\IngresosDelPeriodo;
use App\Filament\Widgets\Finanzas\IngresosPorPeriodoTabla;
use App\Finanzas\ConciliacionConLaPasarela;
use App\Finanzas\Ingresos;
use App\Finanzas\Pesos;
use App\Finanzas\ResultadoDeConciliacion;
use App\Indicadores\Rango;
use App\Models\MovimientoPasarela;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Administracion\Soporte\PagosDelPeriodoDePrueba;
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
 * Los reportes financieros en la API de la administración (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #6,
 * "Ingresos por periodo, comisiones y conciliación con Conekta"; openspec/changes/administracion-en-la-app/, tareas 13.1
 * y 13.2): los ingresos del periodo, GET /api/administracion/ingresos, y la conciliación con la pasarela,
 * POST /api/administracion/conciliaciones.
 *
 * Cada ruta llama a la clase de dominio que usa el panel, App\Finanzas\Ingresos y App\Finanzas\ConciliacionConLaPasarela,
 * con el rango de App\Indicadores\Rango y la regla del rango de App\Validacion\RangoDeFechas, y la prueba compara su
 * respuesta con esa clase, y con el panel, para los mismos datos (ADR-0022, decisión 9). Las reglas las fijan
 * administracion-E17, E20 y E21, que prueban sin cambios tests/Feature/Administracion/ReportesFinancierosTest.php y
 * ConciliacionTest.php.
 *
 * Cada pago de prueba pasa por el camino real: la pasarela simulada lo acredita con su aviso, por el webhook
 * (Tests\Feature\Administracion\Soporte\PagosDelPeriodoDePrueba). Los tokens son los reales de la app
 * (Soporte\SesionesDeLaApp), nunca Sanctum::actingAs(). Todo es ficticio: los montos, las fechas y las órdenes son de
 * prueba.
 *
 * Las comisiones del periodo (administracion-app-E40, DD-020), los reembolsos y ajustes (E39, DD-012 y DD-016) y la
 * comisión de la pasarela (E41, DD-013) esperan su respuesta: no tienen ruta ni cifra aquí.
 *
 * Escenarios: administracion-app-E37, administracion-app-E38, administracion-app-E11, administracion-app-E06 y
 * administracion-app-E07 · Spec: openspec/changes/administracion-en-la-app/specs/administracion-en-la-app/spec.md ·
 * Tareas 13.1 y 13.2
 */
class ReportesFinancierosTest extends TestCase
{
    use AgendaDePrueba;
    use ContratosDeLaApp;
    use IndicadoresDePrueba;
    use LavanderiasDePrueba;
    use PagosDelPeriodoDePrueba;
    use PanelDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;
    use SesionesDeLaApp;

    private const INGRESOS = '/api/administracion/ingresos';

    private const CONCILIACIONES = '/api/administracion/conciliaciones';

    /** Marzo de 2027, por semana: de lunes a domingo, así que el rango llega al domingo 4 de abril. */
    private const MARZO_POR_SEMANA = ['desde' => '2027-03-01', 'hasta' => '2027-03-31', 'granularidad' => 'semana'];

    /** Marzo de 2027, el periodo de la conciliación. */
    private const MARZO = ['desde' => '2027-03-01', 'hasta' => '2027-03-31'];

    private const FIN_ANTERIOR = 'La fecha de fin no puede ser anterior a la de inicio.';

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();
    }

    #[TestDox('administracion-app-E37 y administracion-app-E11: con pagos confirmados por la pasarela en marzo y en abril, y una orden de marzo sin confirmar, la administración pide en la app los ingresos de marzo por semana y ve el total de los pagos confirmados en marzo, cuántos son y el ingreso de cada semana, los mismos que da App\Finanzas\Ingresos para ese rango')]
    public function test_administracion_app_e37_los_ingresos_de_marzo_por_semana(): void
    {
        $this->sembrarMarzoYAbril();
        $token = $this->tokenDeLaApp($this->cuentaDeAdministracion());

        $respuesta = $this->llamarConToken($token, 'GET', self::INGRESOS, self::MARZO_POR_SEMANA)->assertOk();

        $this->assertSame(['rango', 'total', 'periodos'], array_keys($respuesta->json()));
        // El rango ajustado a semanas completas, de lunes a domingo, como en el panel.
        $this->assertSame([
            'desde' => '2027-03-01',
            'hasta' => '2027-04-04',
            'granularidad' => 'semana',
            'descripcion' => 'Del 01/03/2027 al 04/04/2027, por semana',
        ], $respuesta->json('rango'));

        // Los dos pagos confirmados de marzo: ni la orden sin confirmar del día 10 ni los pagos de abril.
        $this->assertSame(['pagos' => 2, 'ingresos' => '350.00', 'ingresos_texto' => '$350.00'], $respuesta->json('total'));
        $this->assertSame([
            ['inicio' => '2027-03-01', 'periodo' => 'Semana del 01/03/2027', 'pagos' => 1, 'ingresos' => '150.00', 'ingresos_texto' => '$150.00'],
            ['inicio' => '2027-03-08', 'periodo' => 'Semana del 08/03/2027', 'pagos' => 0, 'ingresos' => '0.00', 'ingresos_texto' => '$0.00'],
            ['inicio' => '2027-03-15', 'periodo' => 'Semana del 15/03/2027', 'pagos' => 0, 'ingresos' => '0.00', 'ingresos_texto' => '$0.00'],
            ['inicio' => '2027-03-22', 'periodo' => 'Semana del 22/03/2027', 'pagos' => 1, 'ingresos' => '200.00', 'ingresos_texto' => '$200.00'],
            ['inicio' => '2027-03-29', 'periodo' => 'Semana del 29/03/2027', 'pagos' => 0, 'ingresos' => '0.00', 'ingresos_texto' => '$0.00'],
        ], $respuesta->json('periodos'));

        $this->assertSonLasDeIngresos($respuesta, Rango::deLosFiltros(self::MARZO_POR_SEMANA));
    }

    #[TestDox('administracion-app-E37 y administracion-app-E11: cada cifra de la app es la misma que muestra el panel para ese rango: el total y la tabla de los ingresos de marzo y de abril, por mes')]
    public function test_administracion_app_e37_cada_cifra_es_la_misma_que_da_el_panel(): void
    {
        $this->sembrarMarzoYAbril();
        $token = $this->tokenDeLaApp($this->cuentaDeAdministracion());
        $meses = [
            'marzo' => ['desde' => '2027-03-01', 'hasta' => '2027-03-31', 'granularidad' => 'mes'],
            'abril' => ['desde' => '2027-04-01', 'hasta' => '2027-04-30', 'granularidad' => 'mes'],
        ];

        $respuestas = array_map(fn (array $filtros): TestResponse => $this->llamarConToken($token, 'GET', self::INGRESOS, $filtros)->assertOk(), $meses);

        $this->assertSame(['pagos' => 2, 'ingresos' => '350.00', 'ingresos_texto' => '$350.00'], $respuestas['marzo']->json('total'));
        $this->assertSame(['pagos' => 2, 'ingresos' => '700.00', 'ingresos_texto' => '$700.00'], $respuestas['abril']->json('total'));

        // El panel, con su sesión web, para los mismos filtros.
        $this->comoAdministracion();

        foreach ($meses as $mes => $filtros) {
            $total = $respuestas[$mes]->json('total');
            $this->widget(IngresosDelPeriodo::class, $filtros)
                ->assertOk()
                ->assertSee($total['ingresos_texto'])
                ->assertSee("{$total['pagos']} pagos confirmados");

            $tabla = $this->widget(IngresosPorPeriodoTabla::class, $filtros)->instance()->getTableRecords()
                ->map(fn (array $fila): array => array_diff_key($fila, ['__key' => true]))
                ->all();
            $this->assertSame($tabla, $this->periodosComoElDominio($respuestas[$mes]), $mes);
        }
    }

    #[TestDox('administracion-app-E37: sin rango elegido, la administración ve el mes en curso hasta hoy, por día, como el panel')]
    public function test_administracion_app_e37_sin_rango_elegido_es_el_mes_en_curso_por_dia(): void
    {
        $this->pagadoEl('2027-02-27 10:00:00', '90.00');
        $this->pagadoEl('2027-03-05 10:00:00', '150.00');
        $this->pagadoEl('2027-03-17 10:00:00', '200.00');
        // Hoy es el 18 de marzo, en la zona horaria de referencia de lo que no tiene plaza (indicadores-E24).
        $this->travelTo(CarbonImmutable::parse('2027-03-18 12:00:00', (string) config('app.zona_horaria_sin_plaza')));
        $token = $this->tokenDeLaApp($this->cuentaDeAdministracion());

        $respuesta = $this->llamarConToken($token, 'GET', self::INGRESOS)->assertOk();

        $this->assertSame([
            'desde' => '2027-03-01',
            'hasta' => '2027-03-18',
            'granularidad' => 'dia',
            'descripcion' => 'Del 01/03/2027 al 18/03/2027, por día',
        ], $respuesta->json('rango'));
        $this->assertSame(['pagos' => 2, 'ingresos' => '350.00', 'ingresos_texto' => '$350.00'], $respuesta->json('total'));
        $this->assertCount(18, $respuesta->json('periodos'));
        $this->assertSame(
            ['inicio' => '2027-03-05', 'periodo' => 'vie. 05/03/2027', 'pagos' => 1, 'ingresos' => '150.00', 'ingresos_texto' => '$150.00'],
            $respuesta->json('periodos.4'),
        );

        $this->assertSonLasDeIngresos($respuesta, Rango::deLosFiltros(null));
    }

    #[TestDox('administracion-app-E11: en los ingresos, un filtro que no se entiende se toma como no dado y un rango al revés se voltea, como en el panel (App\Indicadores\Rango): la API no tiene reglas propias')]
    public function test_administracion_app_e11_los_filtros_de_los_ingresos_son_los_del_panel(): void
    {
        $this->pagadoEl('2027-03-05 10:00:00', '150.00');
        $this->travelTo(CarbonImmutable::parse('2027-03-18 12:00:00', (string) config('app.zona_horaria_sin_plaza')));
        $token = $this->tokenDeLaApp($this->cuentaDeAdministracion());

        $alReves = ['desde' => '2027-03-31', 'hasta' => '2027-03-01', 'granularidad' => 'semana'];
        $respuesta = $this->llamarConToken($token, 'GET', self::INGRESOS, $alReves)->assertOk();
        $this->assertSame($this->llamarConToken($token, 'GET', self::INGRESOS, self::MARZO_POR_SEMANA)->json(), $respuesta->json());
        $this->assertSonLasDeIngresos($respuesta, Rango::deLosFiltros($alReves));

        // Una granularidad que no existe es por día; un día que no se entiende, o que no llega como texto, no se da.
        $sinEntender = ['desde' => 'no es una fecha', 'hasta' => ['2027-03-10'], 'granularidad' => 'anio'];
        $respuesta = $this->llamarConToken($token, 'GET', self::INGRESOS, $sinEntender)->assertOk();
        $this->assertSame('dia', $respuesta->json('rango.granularidad'));
        $this->assertSame(['2027-03-01', '2027-03-18'], [$respuesta->json('rango.desde'), $respuesta->json('rango.hasta')]);
        $this->assertSonLasDeIngresos($respuesta, Rango::deLosFiltros(null));

        $respuesta = $this->llamarConToken($token, 'GET', self::INGRESOS, ['granularidad' => ['mes']])->assertOk();
        $this->assertSame('dia', $respuesta->json('rango.granularidad'));
    }

    #[TestDox('administracion-app-E06 y administracion-app-E07: los ingresos solo los ve la administración con su token: las cuentas de usuario, de lavandería y de repartidor, y un token de administración sin la habilidad, reciben 403 sin ninguna cifra; sin sesión o con el token vencido, 401')]
    public function test_administracion_app_e06_y_e07_los_ingresos_solo_los_ve_la_administracion(): void
    {
        $this->sembrarMarzoYAbril();

        $this->assertSoloEntraLaAdministracion('GET', self::INGRESOS, self::MARZO_POR_SEMANA);
    }

    #[TestDox('administracion-app-E38 y administracion-app-E11: con movimientos de la pasarela simulada que cuadran, uno sin pedido, un pedido pagado sin movimiento y un monto que no coincide, la administración concilia marzo en la app: el servidor lee la pasarela, guarda sus movimientos y responde el reporte fila por fila, con cuántos hay de cada resultado, el mismo de App\Finanzas\ConciliacionConLaPasarela')]
    public function test_administracion_app_e38_conciliar_un_periodo(): void
    {
        $conciliado = $this->cobroDe($this->pagadoEl('2027-03-05 10:00:00', '100.00'));

        // La pasarela acreditó un pago cuya orden no es de ningún pedido de la plataforma.
        $this->simulada()->acreditar('orden-de-otro-sistema', '80.00', CarbonImmutable::parse('2027-03-10 16:00:00', 'UTC'));

        // La plataforma tiene el pedido pagado y la pasarela no tiene su movimiento.
        $sinMovimiento = $this->cobroDe($this->pagadoEl('2027-03-12 10:00:00', '120.00'));
        $this->simulada()->quitarPago($sinMovimiento->id_pasarela_orden);

        // La misma orden, con 150 en la plataforma y 175 en la pasarela.
        $otroMonto = $this->cobroDe($this->pagadoEl('2027-03-20 10:00:00', '150.00'));
        $this->simulada()->acreditar($otroMonto->id_pasarela_orden, '175.00', $otroMonto->pagado_en);

        $token = $this->tokenDeLaApp($this->cuentaDeAdministracion());
        $this->assertSame(0, MovimientoPasarela::query()->count());

        $respuesta = $this->llamarConToken($token, 'POST', self::CONCILIACIONES, self::MARZO)->assertOk();

        // Leyó la pasarela una vez y guardó sus tres movimientos del periodo.
        $this->assertCount(1, $this->lecturasDeLaPasarela());
        $this->assertSame(3, MovimientoPasarela::query()->count());

        $this->assertSame(['rango', 'resumen', 'filas'], array_keys($respuesta->json()));
        $this->assertSame(
            ['desde' => '2027-03-01', 'hasta' => '2027-03-31', 'descripcion' => 'Del 01/03/2027 al 31/03/2027'],
            $respuesta->json('rango'),
        );

        // Cada diferencia con su tipo y su texto, de la más antigua a la más nueva.
        $folio = fn ($cobro): string => $cobro->pedido->folio;
        $plaza = $this->plaza->nombre;
        $this->assertSame([
            [
                'resultado' => 'conciliado', 'resultado_texto' => 'Conciliado',
                'orden' => $conciliado->id_pasarela_orden, 'folio' => $folio($conciliado), 'plaza' => $plaza,
                'fecha' => '05/03/2027 10:00',
                'monto_plataforma' => '100.00', 'monto_plataforma_texto' => '$100.00',
                'monto_pasarela' => '100.00', 'monto_pasarela_texto' => '$100.00',
            ],
            [
                'resultado' => 'movimiento_sin_pedido', 'resultado_texto' => 'Movimiento sin pedido',
                'orden' => 'orden-de-otro-sistema', 'folio' => null, 'plaza' => null,
                // Las 16:00 en UTC son las 10:00 en la zona de referencia de lo que no tiene plaza (indicadores-E24).
                'fecha' => '10/03/2027 10:00',
                'monto_plataforma' => null, 'monto_plataforma_texto' => null,
                'monto_pasarela' => '80.00', 'monto_pasarela_texto' => '$80.00',
            ],
            [
                'resultado' => 'pedido_sin_movimiento', 'resultado_texto' => 'Pedido pagado sin movimiento',
                'orden' => $sinMovimiento->id_pasarela_orden, 'folio' => $folio($sinMovimiento), 'plaza' => $plaza,
                'fecha' => '12/03/2027 10:00',
                'monto_plataforma' => '120.00', 'monto_plataforma_texto' => '$120.00',
                'monto_pasarela' => null, 'monto_pasarela_texto' => null,
            ],
            [
                'resultado' => 'monto_distinto', 'resultado_texto' => 'Monto que no coincide',
                'orden' => $otroMonto->id_pasarela_orden, 'folio' => $folio($otroMonto), 'plaza' => $plaza,
                'fecha' => '20/03/2027 10:00',
                'monto_plataforma' => '150.00', 'monto_plataforma_texto' => '$150.00',
                'monto_pasarela' => '175.00', 'monto_pasarela_texto' => '$175.00',
            ],
        ], $respuesta->json('filas'));

        $this->assertSame([
            ['resultado' => 'conciliado', 'resultado_texto' => 'Conciliado', 'cuantos' => 1],
            ['resultado' => 'movimiento_sin_pedido', 'resultado_texto' => 'Movimiento sin pedido', 'cuantos' => 1],
            ['resultado' => 'pedido_sin_movimiento', 'resultado_texto' => 'Pedido pagado sin movimiento', 'cuantos' => 1],
            ['resultado' => 'monto_distinto', 'resultado_texto' => 'Monto que no coincide', 'cuantos' => 1],
        ], $respuesta->json('resumen'));

        $this->assertEsLaDeLaConciliacion($respuesta, Rango::deLosFiltros(self::MARZO));

        // Conciliar otra vez el periodo vuelve a leer la pasarela y no repite ningún movimiento.
        $otraVez = $this->llamarConToken($token, 'POST', self::CONCILIACIONES, self::MARZO)->assertOk();
        $this->assertCount(2, $this->lecturasDeLaPasarela());
        $this->assertSame(3, MovimientoPasarela::query()->count());
        $this->assertSame($respuesta->json(), $otraVez->json());
    }

    #[TestDox('administracion-app-E38: con la fecha de fin anterior a la de inicio, la conciliación responde 422 en la fecha de fin con "La fecha de fin no puede ser anterior a la de inicio."; sin las fechas, 422 en las dos; con fechas que se entienden pero no vienen en AAAA-MM-DD, 422 en cada una (App\Validacion\RangoDeFechas); y en ningún caso lee la pasarela ni guarda nada')]
    public function test_administracion_app_e38_un_rango_al_reves_responde_422(): void
    {
        $this->pagadoEl('2027-03-05 10:00:00', '150.00');
        $token = $this->tokenDeLaApp($this->cuentaDeAdministracion());
        $antes = $this->fotoDeLaBase();

        $alReves = $this->llamarConToken($token, 'POST', self::CONCILIACIONES, ['desde' => '2027-03-31', 'hasta' => '2027-03-01'])
            ->assertUnprocessable();
        $this->assertSame(['hasta' => [self::FIN_ANTERIOR]], $alReves->json('errors'));

        $sinFechas = $this->llamarConToken($token, 'POST', self::CONCILIACIONES)->assertUnprocessable();
        $this->assertEqualsCanonicalizing(['desde', 'hasta'], array_keys($sinFechas->json('errors')));

        // Una fecha que se entiende pero no viene en AAAA-MM-DD no se toma como otro periodo: la regla del rango la
        // rechaza en su campo (date_format:Y-m-d).
        $enOtroFormato = $this->llamarConToken($token, 'POST', self::CONCILIACIONES, ['desde' => '03/01/2027', 'hasta' => '1 March 2027'])
            ->assertUnprocessable();
        $this->assertEqualsCanonicalizing(['desde', 'hasta'], array_keys($enOtroFormato->json('errors')));

        $this->assertSame([], $this->lecturasDeLaPasarela());
        $this->assertSame($antes, $this->fotoDeLaBase());
    }

    #[TestDox('administracion-app-E38: si la pasarela no responde, la conciliación dice "No se pudo leer la pasarela" y no guarda nada')]
    public function test_administracion_app_e38_sin_pasarela_no_se_guarda_nada(): void
    {
        $this->pagadoEl('2027-03-05 10:00:00', '150.00');
        $token = $this->tokenDeLaApp($this->cuentaDeAdministracion());
        config(['pasarela.simulada.llave' => '']);
        $antes = $this->fotoDeLaBase();

        $this->llamarConToken($token, 'POST', self::CONCILIACIONES, self::MARZO)
            ->assertServiceUnavailable()
            ->assertExactJson(['message' => 'No se pudo leer la pasarela']);

        $this->assertSame(0, MovimientoPasarela::query()->count());
        $this->assertSame($antes, $this->fotoDeLaBase());
    }

    #[TestDox('administracion-app-E06 y administracion-app-E07: solo la administración con su token concilia: las cuentas de usuario, de lavandería y de repartidor, y un token de administración sin la habilidad, reciben 403 sin ningún pago; sin sesión o con el token vencido, 401; y ninguna de esas llamadas lee la pasarela')]
    public function test_administracion_app_e06_y_e07_solo_la_administracion_concilia(): void
    {
        $this->pagadoEl('2027-03-05 10:00:00', '150.00');

        $this->assertSoloEntraLaAdministracion('POST', self::CONCILIACIONES, self::MARZO);

        $this->assertSame([], $this->lecturasDeLaPasarela());
        $this->assertSame(0, MovimientoPasarela::query()->count());
    }

    #[TestDox('administracion-app-E37: el contrato de los ingresos con la app: el rango ajustado, el total y cada periodo, con sus montos también como los lee el panel')]
    public function test_administracion_app_e37_el_contrato_de_los_ingresos(): void
    {
        $this->sembrarMarzoYAbril();
        $token = $this->tokenDeLaApp($this->cuentaDeAdministracion());

        $this->assertCumpleSuContrato('ingresos', 'GET /api/administracion/ingresos', $this->llamarConToken($token, 'GET', self::INGRESOS, self::MARZO_POR_SEMANA));
    }

    #[TestDox('administracion-app-E38: el contrato de la conciliación con la app: el rango, el resumen y una fila de cada resultado; el 422 del rango al revés en hasta; y el 503 de la pasarela que no responde')]
    public function test_administracion_app_e38_el_contrato_de_la_conciliacion(): void
    {
        $peticion = 'POST /api/administracion/conciliaciones';
        $this->pagadoEl('2027-03-05 10:00:00', '100.00');
        $this->simulada()->acreditar('orden-de-otro-sistema', '80.00', CarbonImmutable::parse('2027-03-10 16:00:00', 'UTC'));
        $sinMovimiento = $this->cobroDe($this->pagadoEl('2027-03-12 10:00:00', '120.00'));
        $this->simulada()->quitarPago($sinMovimiento->id_pasarela_orden);
        $token = $this->tokenDeLaApp($this->cuentaDeAdministracion());

        $this->assertCumpleSuContrato('conciliaciones', $peticion, $this->llamarConToken($token, 'POST', self::CONCILIACIONES, self::MARZO));
        $this->assertCumpleSuContrato(
            'conciliaciones.rango-al-reves',
            $peticion,
            $this->llamarConToken($token, 'POST', self::CONCILIACIONES, ['desde' => '2027-03-31', 'hasta' => '2027-03-01']),
            422,
        );

        config(['pasarela.simulada.llave' => '']);
        $this->assertCumpleSuContrato('conciliaciones.sin-pasarela', $peticion, $this->llamarConToken($token, 'POST', self::CONCILIACIONES, self::MARZO), 503);
    }

    /**
     * Marzo: un pago de 150 el día 5 y otro de 200 el día 24, y una orden de 500 el día 10 que la pasarela creó y nunca
     * confirmó. Abril: un pago de 300 el día 15 y otro de 400 el día 20, después de la última semana de marzo.
     */
    private function sembrarMarzoYAbril(): void
    {
        $this->pagadoEl('2027-03-05 10:00:00', '150.00');
        $this->ordenSinConfirmarEl('2027-03-10 10:00:00', '500.00');
        $this->pagadoEl('2027-03-24 10:00:00', '200.00');
        $this->pagadoEl('2027-04-15 10:00:00', '300.00');
        $this->pagadoEl('2027-04-20 10:00:00', '400.00');
    }

    /**
     * Que la respuesta de los ingresos trae las cifras de App\Finanzas\Ingresos para ese rango, con cada monto también
     * como lo lee el panel (App\Finanzas\Pesos).
     */
    private function assertSonLasDeIngresos(TestResponse $respuesta, Rango $rango): void
    {
        $ingresos = app(Ingresos::class);
        $total = $respuesta->json('total');

        $this->assertSame($ingresos->delRango($rango), ['pagos' => $total['pagos'], 'ingresos' => $total['ingresos']]);
        $this->assertSame(Pesos::texto($total['ingresos']), $total['ingresos_texto']);
        $this->assertSame($ingresos->porPeriodo($rango), $this->periodosComoElDominio($respuesta));

        foreach ($respuesta->json('periodos') as $periodo) {
            $this->assertSame(Pesos::texto($periodo['ingresos']), $periodo['ingresos_texto'], $periodo['inicio']);
        }

        $this->assertSame(
            [$rango->desde->toDateString(), $rango->hasta->toDateString(), $rango->granularidad->value, $rango->descripcion()],
            array_values($respuesta->json('rango')),
        );
    }

    /**
     * Los periodos de la respuesta de los ingresos como los da App\Finanzas\Ingresos::porPeriodo(): por su primer día,
     * sin los textos de los montos.
     *
     * @return array<string, array{periodo: string, pagos: int, ingresos: string}>
     */
    private function periodosComoElDominio(TestResponse $respuesta): array
    {
        $periodos = [];

        foreach ($respuesta->json('periodos') as $periodo) {
            $periodos[$periodo['inicio']] = ['periodo' => $periodo['periodo'], 'pagos' => $periodo['pagos'], 'ingresos' => $periodo['ingresos']];
        }

        return $periodos;
    }

    /**
     * Que la respuesta de la conciliación trae el reporte y el resumen de App\Finanzas\ConciliacionConLaPasarela para ese
     * rango, con lo que ya se leyó de la pasarela, en el mismo orden; cada resultado y cada monto también con su texto.
     */
    private function assertEsLaDeLaConciliacion(TestResponse $respuesta, Rango $rango): void
    {
        $reporte = app(ConciliacionConLaPasarela::class)->reporte($rango);
        $filas = $respuesta->json('filas');

        $this->assertCount(count($reporte), $filas);

        foreach (array_values($reporte) as $posicion => $fila) {
            $api = $filas[$posicion];

            $this->assertSame([...$fila, 'resultado' => $fila['resultado']->value], array_diff_key($api, array_flip([
                'resultado_texto', 'monto_plataforma_texto', 'monto_pasarela_texto',
            ])));
            $this->assertSame($fila['resultado']->getLabel(), $api['resultado_texto']);

            foreach (['monto_plataforma', 'monto_pasarela'] as $monto) {
                $this->assertSame($fila[$monto] === null ? null : Pesos::texto($fila[$monto]), $api["{$monto}_texto"]);
            }
        }

        $this->assertSame(
            ConciliacionConLaPasarela::resumen($reporte),
            array_column($respuesta->json('resumen'), 'cuantos', 'resultado'),
        );

        foreach ($respuesta->json('resumen') as $resultado) {
            $this->assertSame(ResultadoDeConciliacion::from($resultado['resultado'])->getLabel(), $resultado['resultado_texto']);
        }
    }

    /**
     * Las veces que el servidor le pidió a la pasarela simulada sus pagos del periodo.
     *
     * @return list<array<string, string>>
     */
    private function lecturasDeLaPasarela(): array
    {
        return array_values(array_filter(
            $this->simulada()->peticiones(),
            fn (array $peticion): bool => $peticion['operacion'] === 'pagos_del_periodo',
        ));
    }
}
