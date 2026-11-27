<?php

namespace Tests\Feature\AdministracionEnLaApp;

use App\Cobro\PasarelaSimulada;
use App\Exportacion\CreacionDeDescarga;
use App\Exportacion\EscribirArchivoDelReporte;
use App\Exportacion\EstadoDeLaDescarga;
use App\Exportacion\Exportacion;
use App\Exportacion\Exportadores\PedidosTotalesExportador;
use App\Finanzas\ConciliacionConLaPasarela;
use App\Indicadores\Rango;
use App\Models\MovimientoPasarela;
use App\Models\Pedido;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use League\Csv\Reader as LectorDeCsv;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Reader\XLSX\Reader as LectorDeXlsx;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\AdministracionEnLaApp\Soporte\ContratosDeLaApp;
use Tests\Feature\AdministracionEnLaApp\Soporte\SesionesDeLaApp;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\AltaLavanderias\Soporte\PanelDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Exportacion\Soporte\DescargasDePrueba;
use Tests\Feature\Indicadores\Soporte\IndicadoresDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;
use ZipArchive;

/**
 * La descarga de los reportes en CSV y en Excel desde la app de la administración (NSV-2026-001-v8.pdf:22:ANEXO A,
 * Sección A.5, "Descarga de reportes en formato CSV y Excel para cualquier rango de fechas"; :21:ANEXO A, Sección
 * A.4.4; openspec/changes/administracion-en-la-app/, tareas 19.1 a 19.4): POST, GET y GET por id_publico de
 * /api/administracion/descargas, y el archivo de cada una en csv o xlsx.
 *
 * - La descarga la crea App\Exportacion\CreacionDeDescarga, la clase que llama también el botón del panel, y la escribe
 *   la misma cola de la exportación de Filament: la prueba compara el archivo de la app con el del panel para los mismos
 *   datos (ADR-0022, decisiones 9 y 15). Las reglas del archivo las fijan exportacion-E01 a E09, E11 y E12, que prueban
 *   sin cambios tests/Feature/Exportacion/.
 * - La descarga se nombra por su id_publico, un ULID, nunca por el id de exports, que sigue siendo la llave de la ruta
 *   de descarga de Filament.
 * - Su estado sale de la cola y de sus archivos: generándose sin completed_at; lista con completed_at y sus dos CSV en
 *   su disco; no se pudo con completed_at sin alguno de los dos.
 *
 * Las pruebas corren la cola en la misma petición (QUEUE_CONNECTION=sync, phpunit.xml), salvo las del estado, que usan
 * la cola de la base, como GeneracionEnSegundoPlanoTest. Los tokens son los reales de la app (Soporte\SesionesDeLaApp),
 * nunca Sanctum::actingAs(). Todo es ficticio: las colonias, los montos y las fechas son de prueba.
 *
 * La descarga de F-I06, F-I10 y las comisiones del periodo (administracion-app-E63) espera DD-083, DD-020 y DD-013: no
 * tiene código de reporte aquí.
 *
 * Escenarios: administracion-app-E56, administracion-app-E57, administracion-app-E59, administracion-app-E60,
 * administracion-app-E61, administracion-app-E62, administracion-app-E11, administracion-app-E12,
 * administracion-app-E06 y administracion-app-E07 · Spec:
 * openspec/changes/administracion-en-la-app/specs/administracion-en-la-app/spec.md · Tareas 19.1, 19.2, 19.3 y 19.4
 */
class DescargasTest extends TestCase
{
    use AgendaDePrueba;
    use ContratosDeLaApp;
    use DescargasDePrueba;
    use IndicadoresDePrueba;
    use LavanderiasDePrueba;
    use PanelDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;
    use SesionesDeLaApp;

    private const DESCARGAS = '/api/administracion/descargas';

    /** La semana del lunes 30 de noviembre al domingo 6 de diciembre de 2026, por día. */
    private const SEMANA = ['desde' => '2026-11-30', 'hasta' => '2026-12-06', 'granularidad' => 'dia'];

    private const ENCABEZADO_DE_PEDIDOS_TOTALES = ['Periodo', 'Pedidos', 'Periodo anterior', 'Pedidos del periodo anterior'];

    private const NO_EXISTE_LA_DESCARGA = ['message' => 'No existe la descarga.'];

    private const NO_EXISTE_EL_ARCHIVO = ['message' => 'No existe ese archivo.'];

    private const NO_ES_UN_REPORTE = 'Ese no es un reporte que se pueda descargar.';

    private const FIN_ANTERIOR = 'La fecha de fin no puede ser anterior a la de inicio.';

    /** La marca de orden de bytes de UTF-8, con la que Excel lee los acentos y las eñes del CSV (exportacion-E07). */
    private const BOM = "\xEF\xBB\xBF";

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();
        Storage::fake((string) config('exportacion.disco'));
    }

    #[TestDox('administracion-app-E56: con pedidos en la semana, la administración pide en la app la descarga de los pedidos totales, con su rango y su granularidad: el servidor crea la descarga de su cuenta, pone en cola su archivo y responde 202 con la descarga; el CSV y el XLSX traen una fila por día con las cifras del reporte (exportacion-E01 a E03)')]
    public function test_administracion_app_e56_pedir_la_descarga_de_un_reporte(): void
    {
        $this->sembrarLaSemana();
        $administracion = $this->cuentaDeAdministracion();
        $token = $this->tokenDeLaApp($administracion);

        $respuesta = $this->pedirPorLaApp($token, PedidosTotalesExportador::NOMBRE)->assertStatus(202);

        $exportacion = Exportacion::sole();
        $this->assertSame($administracion->getKey(), $exportacion->id_usuarios);
        $this->assertSame(PedidosTotalesExportador::class, $exportacion->exporter);
        $this->assertSame(['descarga'], array_keys($respuesta->json()));
        // La cola corre en la misma petición: la descarga ya llega lista.
        $this->assertSame([
            'id_publico' => $exportacion->id_publico,
            'reporte' => 'pedidos-totales',
            'titulo' => 'Pedidos totales',
            'desde' => '2026-11-30',
            'hasta' => '2026-12-06',
            'pedida_en' => $exportacion->created_at->utc()->toIso8601ZuluString(),
            'estado' => 'lista',
            'archivos' => [
                'csv' => 'pedidos-totales-2026-11-30-al-2026-12-06.csv',
                'xlsx' => 'pedidos-totales-2026-11-30-al-2026-12-06.xlsx',
            ],
        ], $respuesta->json('descarga'));

        $csv = $this->archivoPorLaApp($token, $exportacion->id_publico, 'csv')->assertOk();
        $this->assertStringContainsString('text/csv', (string) $csv->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', (string) $csv->headers->get('Content-Disposition'));
        $this->assertStringContainsString('pedidos-totales-2026-11-30-al-2026-12-06.csv', (string) $csv->headers->get('Content-Disposition'));

        $filas = $this->filasDe($csv->streamedContent());
        $this->assertSame(self::ENCABEZADO_DE_PEDIDOS_TOTALES, $filas[0]);
        $this->assertCount(8, $filas);
        // Las cifras de la semana: 3 el lunes, 0 el martes y 2 el miércoles.
        $this->assertSame(['lun. 30/11/2026', '3'], array_slice($filas[1], 0, 2));
        $this->assertSame(['mar. 01/12/2026', '0'], array_slice($filas[2], 0, 2));
        $this->assertSame(['mié. 02/12/2026', '2'], array_slice($filas[3], 0, 2));

        // El XLSX, las mismas filas y cifras, con las cifras como números.
        $xlsx = $this->archivoPorLaApp($token, $exportacion->id_publico, 'xlsx')->assertOk();
        $this->assertStringContainsString('pedidos-totales-2026-11-30-al-2026-12-06.xlsx', (string) $xlsx->headers->get('Content-Disposition'));
        $celdas = $this->celdasDe($xlsx->streamedContent());
        $this->assertSame($filas, array_map(fn (array $fila): array => array_map(fn (Cell $celda): string => (string) $celda->getValue(), $fila), $celdas));
        $this->assertInstanceOf(NumericCell::class, $celdas[1][1]);
        $this->assertInstanceOf(StringCell::class, $celdas[1][0]);
    }

    #[TestDox('administracion-app-E56: los doce reportes de los exportadores del panel se piden por su código, el que lista el contrato del controlador, y cada uno responde 202 con su descarga lista; F-I06, F-I10 y las comisiones del periodo no tienen código (administracion-app-E63)')]
    public function test_administracion_app_e56_cada_uno_de_los_doce_reportes_se_pide_por_su_codigo(): void
    {
        $token = $this->tokenDeLaApp($this->cuentaDeAdministracion());

        // Los doce exportadores de App\Exportacion\Exportadores\, ni uno más ni uno menos.
        $exportadores = array_map(
            fn (string $archivo): string => 'App\\Exportacion\\Exportadores\\'.basename($archivo, '.php'),
            glob(app_path('Exportacion/Exportadores/*.php')) ?: [],
        );
        $this->assertCount(12, CreacionDeDescarga::REPORTES);
        $this->assertEqualsCanonicalizing($exportadores, CreacionDeDescarga::REPORTES);

        // La app copia los códigos del contrato del controlador: ahí está cada uno.
        $contrato = (string) file_get_contents(app_path('Http/Controllers/Api/Administracion/DescargasController.php'));

        foreach (CreacionDeDescarga::REPORTES as $exportador) {
            $this->assertStringContainsString(' '.$exportador::NOMBRE.' ', $contrato, $exportador);

            $this->pedirPorLaApp($token, $exportador::NOMBRE)
                ->assertStatus(202)
                ->assertJsonPath('descarga.reporte', $exportador::NOMBRE)
                ->assertJsonPath('descarga.titulo', $exportador::titulo())
                ->assertJsonPath('descarga.estado', 'lista');
        }

        $this->assertEqualsCanonicalizing(CreacionDeDescarga::REPORTES, Exportacion::query()->pluck('exporter')->all());
    }

    #[TestDox('administracion-app-E56: un reporte que no es uno de los exportadores, como las comisiones, el nombre de una clase o ninguno, responde 422 en reporte con su mensaje y no crea ninguna descarga; la granularidad que no es día, semana ni mes, o que falta, también')]
    public function test_administracion_app_e56_un_reporte_que_no_es_de_los_exportadores_responde_422_sin_crear_nada(): void
    {
        $token = $this->tokenDeLaApp($this->cuentaDeAdministracion());

        foreach (['comisiones', 'lavanderias-activas', PedidosTotalesExportador::class, 'PEDIDOS-TOTALES', ''] as $reporte) {
            $this->pedirPorLaApp($token, $reporte)
                ->assertStatus(422)
                ->assertJsonPath('errors.reporte', [self::NO_ES_UN_REPORTE]);
        }

        $this->llamarConToken($token, 'POST', self::DESCARGAS, self::SEMANA)
            ->assertStatus(422)
            ->assertJsonPath('errors.reporte', [self::NO_ES_UN_REPORTE]);
        $this->llamarConToken($token, 'POST', self::DESCARGAS, ['reporte' => ['pedidos-totales'], ...self::SEMANA])
            ->assertStatus(422)
            ->assertJsonPath('errors.reporte', [self::NO_ES_UN_REPORTE]);

        foreach ([['granularidad' => 'anio'], ['granularidad' => null], []] as $granularidad) {
            $this->llamarConToken($token, 'POST', self::DESCARGAS, [
                'reporte' => PedidosTotalesExportador::NOMBRE,
                'desde' => self::SEMANA['desde'],
                'hasta' => self::SEMANA['hasta'],
                ...$granularidad,
            ])
                ->assertStatus(422)
                ->assertJsonPath('errors.granularidad', ['Elige ver el reporte por día, por semana o por mes.']);
        }

        $this->assertSame(0, Exportacion::count());
    }

    #[TestDox('administracion-app-E56: después de conciliar un periodo, la descarga de la conciliación desde la app trae las filas de los movimientos ya guardados, sin volver a leer la pasarela: un pago que la pasarela acreditó después no aparece; lleva solo su periodo, como en el panel, aunque llegue una granularidad')]
    public function test_administracion_app_e56_la_descarga_de_la_conciliacion_usa_los_movimientos_ya_guardados(): void
    {
        $periodo = ['desde' => '2026-12-07', 'hasta' => '2026-12-13'];
        $this->pedidoConfirmadoEl('2026-12-08 10:00:00');
        $pasarela = app(PasarelaSimulada::class);
        $pasarela->acreditar('orden-de-otro-sistema', '80.00', CarbonImmutable::parse('2026-12-09 16:00:00', 'UTC'));
        $conciliacion = app(ConciliacionConLaPasarela::class);
        $rango = Rango::deLosFiltros($periodo);
        $conciliacion->conciliar($rango);
        $guardados = MovimientoPasarela::count();
        $this->assertSame(2, $guardados);

        // La pasarela acredita otro pago del periodo después de conciliar: la descarga no lo lee.
        $pasarela->acreditar('orden-acreditada-despues', '95.00', CarbonImmutable::parse('2026-12-10 16:00:00', 'UTC'));
        $token = $this->tokenDeLaApp($this->cuentaDeAdministracion());

        $descarga = $this->llamarConToken($token, 'POST', self::DESCARGAS, [
            'reporte' => 'conciliacion-con-la-pasarela',
            ...$periodo,
            // No cuenta: con ella, el periodo se ajustaría al mes.
            'granularidad' => 'mes',
        ])->assertStatus(202)->json('descarga');

        $this->assertSame('conciliacion-con-la-pasarela-2026-12-07-al-2026-12-13.csv', $descarga['archivos']['csv']);
        $this->assertSame($guardados, MovimientoPasarela::count());

        $filas = $this->filasDe($this->archivoPorLaApp($token, $descarga['id_publico'], 'csv')->assertOk()->streamedContent());
        $this->assertSame(['Resultado', 'Orden en la pasarela', 'Folio', 'Plaza', 'Fecha del pago', 'Monto en la plataforma', 'Monto en la pasarela'], $filas[0]);
        $this->assertSame(
            array_map(fn (array $fila): string => $fila['resultado']->getLabel(), array_values($conciliacion->reporte($rango))),
            array_column(array_slice($filas, 1), 0),
        );
        $this->assertSame(['Conciliado', 'Movimiento sin pedido'], array_column(array_slice($filas, 1), 0));
        $this->assertContains('orden-de-otro-sistema', array_column($filas, 1));
        $this->assertNotContains('orden-acreditada-despues', array_column($filas, 1));
    }

    #[TestDox('administracion-app-E11 y administracion-app-E62: la descarga nueva tiene su id_publico, un ULID distinto en cada una, también la del botón del panel; la llave de la ruta de descarga de Filament sigue siendo su id, y el panel la baja con su enlace como antes')]
    public function test_administracion_app_e11_y_e62_la_descarga_nueva_tiene_su_ulid_y_filament_sigue_con_su_id(): void
    {
        $administracion = $this->cuentaDeAdministracion();
        $token = $this->tokenDeLaApp($administracion);

        $deLaApp = $this->pedirPorLaApp($token, PedidosTotalesExportador::NOMBRE)->assertStatus(202)->json('descarga.id_publico');
        $this->comoElPanel($administracion);
        $this->pedirDescarga('descargarPedidosTotales', self::SEMANA)->assertHasNoActionErrors();

        [$app, $panel] = Exportacion::query()->orderBy('id')->get()->all();
        $this->assertSame($deLaApp, $app->id_publico);

        foreach ([$app, $panel] as $exportacion) {
            $this->assertTrue(Str::isUlid((string) $exportacion->id_publico));
            $this->assertSame(Str::lower((string) $exportacion->id_publico), $exportacion->id_publico);
            $this->assertSame('id', $exportacion->getRouteKeyName());
            $this->assertSame($exportacion->id, $exportacion->getRouteKey());
            $this->assertStringContainsString("/filament/exports/{$exportacion->id}/download", $this->enlaceDeDescarga($exportacion, 'csv'));
        }

        $this->assertNotSame($app->id_publico, $panel->id_publico);

        // El enlace del panel sigue bajando el archivo por su id, también el de la descarga de la app.
        $this->comoElPanel($administracion);
        $this->abrirEnlace($panel, 'csv')->assertOk();
        $this->abrirEnlace($app, 'xlsx')->assertOk();
    }

    #[TestDox('administracion-app-E11: para el mismo reporte y el mismo rango, la descarga de la app es la que crea el botón del panel: el mismo exportador, el mismo disco, el mismo nombre y las mismas filas')]
    public function test_administracion_app_e11_la_api_crea_la_descarga_como_el_boton_del_panel(): void
    {
        $this->sembrarLaSemana();
        $administracion = $this->cuentaDeAdministracion();

        $this->pedirPorLaApp($this->tokenDeLaApp($administracion), PedidosTotalesExportador::NOMBRE)->assertStatus(202);
        $this->comoElPanel($administracion);
        $this->pedirDescarga('descargarPedidosTotales', self::SEMANA)->assertHasNoActionErrors();

        $columnas = ['exporter', 'file_disk', 'file_name', 'total_rows', 'processed_rows', 'successful_rows', 'id_usuarios'];
        [$app, $panel] = Exportacion::query()->orderBy('id')->get()->all();
        $this->assertSame($panel->only($columnas), $app->only($columnas));
        $this->assertSame(7, $app->successful_rows);
        $this->assertNotNull($app->completed_at);
    }

    #[TestDox('administracion-app-E57: con la cola de la base, la descarga recién pedida desde la app responde que se está generando, sin archivo; cuando la cola la termina con sus dos CSV en su disco, está lista y baja en CSV y en Excel')]
    public function test_administracion_app_e57_mientras_se_genera_y_cuando_esta_lista(): void
    {
        config(['queue.default' => 'database']);
        $token = $this->tokenDeLaApp($this->cuentaDeAdministracion());

        $id = $this->pedirPorLaApp($token, PedidosTotalesExportador::NOMBRE)
            ->assertStatus(202)
            ->assertJsonPath('descarga.estado', 'generandose')
            ->json('descarga.id_publico');

        $this->assertNull(Exportacion::sole()->completed_at);
        $this->consultarPorLaApp($token, $id)->assertOk()->assertJsonPath('descarga.estado', 'generandose');

        foreach (['csv', 'xlsx'] as $formato) {
            $this->archivoPorLaApp($token, $id, $formato)->assertNotFound()->assertExactJson(self::NO_EXISTE_EL_ARCHIVO);
        }

        $this->correrLaCola();

        $this->consultarPorLaApp($token, $id)->assertOk()->assertJsonPath('descarga.estado', 'lista');
        $exportacion = Exportacion::sole();
        $this->assertNotNull($exportacion->completed_at);
        $disco = Storage::disk($exportacion->file_disk);
        $this->assertTrue($disco->exists($exportacion->getFileDirectory().DIRECTORY_SEPARATOR.EscribirArchivoDelReporte::ARCHIVO_DEL_ENCABEZADO));
        $this->assertTrue($disco->exists($exportacion->getFileDirectory().DIRECTORY_SEPARATOR.EscribirArchivoDelReporte::ARCHIVO_DE_LAS_FILAS));

        $this->assertStringContainsString('Periodo,Pedidos', $this->archivoPorLaApp($token, $id, 'csv')->assertOk()->streamedContent());
        $this->archivoPorLaApp($token, $id, 'xlsx')->assertOk();
    }

    #[TestDox('administracion-app-E57: si la cola la da por terminada sin escribir su archivo, porque la cuenta ya no podía bajar reportes cuando corrió, la descarga tiene completed_at pero no se pudo, y su archivo responde 404 en los dos formatos')]
    public function test_administracion_app_e57_terminada_sin_su_archivo_no_se_pudo(): void
    {
        config(['queue.default' => 'database']);
        $administracion = $this->cuentaDeAdministracion();
        $token = $this->tokenDeLaApp($administracion);
        $id = $this->pedirPorLaApp($token, PedidosTotalesExportador::NOMBRE)->assertStatus(202)->json('descarga.id_publico');

        // Mientras la cola corre, la cuenta no puede bajar reportes: el trabajo no escribe nada.
        $administracion->forceFill(['estado' => Usuario::BLOQUEADO])->save();
        $this->correrLaCola();
        $administracion->forceFill(['estado' => Usuario::ACTIVO])->save();

        $exportacion = Exportacion::sole();
        $this->assertNotNull($exportacion->completed_at);
        $this->assertSame(EstadoDeLaDescarga::NoSePudo, $exportacion->estado());
        $this->consultarPorLaApp($token, $id)->assertOk()->assertJsonPath('descarga.estado', 'no_se_pudo');

        foreach (['csv', 'xlsx'] as $formato) {
            $this->archivoPorLaApp($token, $id, $formato)->assertNotFound()->assertExactJson(self::NO_EXISTE_EL_ARCHIVO);
        }
    }

    #[TestDox('administracion-app-E57: una descarga terminada a la que le falta el CSV del encabezado o el de las filas no se pudo, y su archivo responde 404; con los dos, está lista')]
    public function test_administracion_app_e57_sin_alguno_de_sus_dos_archivos_no_se_pudo(): void
    {
        $token = $this->tokenDeLaApp($this->cuentaDeAdministracion());

        foreach ([EscribirArchivoDelReporte::ARCHIVO_DEL_ENCABEZADO, EscribirArchivoDelReporte::ARCHIVO_DE_LAS_FILAS] as $archivo) {
            $id = $this->pedirPorLaApp($token, PedidosTotalesExportador::NOMBRE)
                ->assertStatus(202)
                ->assertJsonPath('descarga.estado', 'lista')
                ->json('descarga.id_publico');
            $exportacion = Exportacion::porIdPublico($id);
            $this->assertNotNull($exportacion);

            $exportacion->getFileDisk()->delete($exportacion->getFileDirectory().DIRECTORY_SEPARATOR.$archivo);

            $this->assertNotNull($exportacion->completed_at);
            $this->consultarPorLaApp($token, $id)->assertOk()->assertJsonPath('descarga.estado', 'no_se_pudo', $archivo);
            $this->archivoPorLaApp($token, $id, 'csv')->assertNotFound()->assertExactJson(self::NO_EXISTE_EL_ARCHIVO);
            $this->archivoPorLaApp($token, $id, 'xlsx')->assertNotFound()->assertExactJson(self::NO_EXISTE_EL_ARCHIVO);
        }
    }

    #[TestDox('administracion-app-E59: con tres descargas de la cuenta, una más vieja que los días de vida de la configuración y ya borrada, una de otra cuenta de administración y una de antes de que existiera el id_publico, la lista de Descargas trae solo las dos vivas de la cuenta, de la más nueva a la más vieja, cada una con su reporte, cuándo se pidió y su estado')]
    public function test_administracion_app_e59_las_descargas_de_la_cuenta(): void
    {
        config(['exportacion.dias_de_vida' => 7]);
        $administracion = $this->cuentaDeAdministracion();
        $token = $this->tokenDeLaApp($administracion);
        $deLaOtra = $this->tokenDeLaApp($this->cuentaDeAdministracion());

        $vieja = $this->pedirPorLaApp($token, PedidosTotalesExportador::NOMBRE)->assertStatus(202)->json('descarga.id_publico');
        $this->travel(6)->days();
        $intermedia = $this->pedirPorLaApp($token, 'usuarios-activos')->assertStatus(202)->json('descarga.id_publico');
        $ajena = $this->pedirPorLaApp($deLaOtra, PedidosTotalesExportador::NOMBRE)->assertStatus(202)->json('descarga.id_publico');
        // Una fila de antes de la migración del id_publico: la API no la nombra.
        $sinIdPublico = app(CreacionDeDescarga::class)->crear($administracion, PedidosTotalesExportador::class, self::SEMANA);
        DB::table('exports')->where('id', $sinIdPublico->id)->update(['id_publico' => null]);
        $this->travel(1)->days();
        $this->travel(1)->minutes();
        $nueva = $this->pedirPorLaApp($token, 'ingresos-por-periodo')->assertStatus(202)->json('descarga.id_publico');

        // La vieja ya cumplió sus días de vida: no está en la lista aunque el borrado diario todavía no corra.
        $this->assertSame([$nueva, $intermedia], $this->idsDeLaLista($token));

        $this->artisan('model:prune', ['--model' => [Exportacion::class]])->assertSuccessful();
        $this->assertNull(Exportacion::porIdPublico($vieja));
        $this->assertNotNull(Exportacion::porIdPublico($ajena));

        $lista = $this->llamarConToken($token, 'GET', self::DESCARGAS)->assertOk();
        $this->assertSame(['descargas', 'dias_de_vida'], array_keys($lista->json()));
        $this->assertSame(7, $lista->json('dias_de_vida'));
        $this->assertSame([$nueva, $intermedia], array_column($lista->json('descargas'), 'id_publico'));

        $primera = Exportacion::porIdPublico($nueva);
        $this->assertNotNull($primera);
        $this->assertSame([
            'id_publico' => $nueva,
            'reporte' => 'ingresos-por-periodo',
            'titulo' => 'Ingresos por periodo',
            'desde' => '2026-11-30',
            'hasta' => '2026-12-06',
            'pedida_en' => $primera->created_at->utc()->toIso8601ZuluString(),
            'estado' => 'lista',
            'archivos' => ['csv' => "{$primera->file_name}.csv", 'xlsx' => "{$primera->file_name}.xlsx"],
        ], $lista->json('descargas.0'));
        $this->assertSame('usuarios-activos', $lista->json('descargas.1.reporte'));

        // La otra cuenta ve solo la suya.
        $this->assertSame([$ajena], $this->idsDeLaLista($deLaOtra));
    }

    #[TestDox('administracion-app-E59 y administracion-app-E57: cada descarga trae el rango que cubre su archivo, ya ajustado a periodos completos, igual al responder POST, en la lista y al consultarla: por semana, del lunes de la primera semana al domingo de la última; la conciliación, su periodo tal cual; la granularidad no llega, porque la fila de exports no guarda las opciones de la descarga (ADR-0022, decisión 15)')]
    public function test_administracion_app_e59_y_e57_cada_descarga_trae_el_rango_de_su_archivo(): void
    {
        $token = $this->tokenDeLaApp($this->cuentaDeAdministracion());

        // Del miércoles 4 al miércoles 18 de noviembre, por semana: del lunes 2 al domingo 22.
        $porSemana = $this->pedirPorLaApp($token, PedidosTotalesExportador::NOMBRE, ['desde' => '2026-11-04', 'hasta' => '2026-11-18', 'granularidad' => 'semana'])
            ->assertStatus(202)
            ->json('descarga');
        // La conciliación no ajusta su periodo, aunque llegue una granularidad.
        $conciliacion = $this->pedirPorLaApp($token, 'conciliacion-con-la-pasarela', ['desde' => '2026-11-01', 'hasta' => '2026-11-15', 'granularidad' => 'mes'])
            ->assertStatus(202)
            ->json('descarga');

        $this->assertSame(['desde' => '2026-11-02', 'hasta' => '2026-11-22'], array_intersect_key($porSemana, ['desde' => 0, 'hasta' => 0]));
        $this->assertSame('pedidos-totales-2026-11-02-al-2026-11-22.csv', $porSemana['archivos']['csv']);
        $this->assertSame(['desde' => '2026-11-01', 'hasta' => '2026-11-15'], array_intersect_key($conciliacion, ['desde' => 0, 'hasta' => 0]));

        foreach ([$porSemana, $conciliacion] as $descarga) {
            $this->assertArrayNotHasKey('granularidad', $descarga);
            $this->assertSame($descarga, $this->consultarPorLaApp($token, $descarga['id_publico'])->assertOk()->json('descarga'));
        }

        $this->assertSame([$conciliacion, $porSemana], $this->llamarConToken($token, 'GET', self::DESCARGAS)->assertOk()->json('descargas'));
    }

    #[TestDox('administracion-app-E59: la lista de Descargas trae cuántos días vive cada descarga, los de EXPORTACION_DIAS_DE_VIDA de la configuración (exportacion-E12), al menos uno, para que la app los diga')]
    public function test_administracion_app_e59_la_lista_trae_los_dias_de_vida_de_la_configuracion(): void
    {
        $token = $this->tokenDeLaApp($this->cuentaDeAdministracion());

        foreach ([7 => 7, 3 => 3, 0 => 1] as $configurados => $dias) {
            config(['exportacion.dias_de_vida' => $configurados]);

            $this->llamarConToken($token, 'GET', self::DESCARGAS)->assertOk()->assertJsonPath('dias_de_vida', $dias);
        }
    }

    #[TestDox('administracion-app-E60: la descarga del 10 al 20 de marzo trae esos días, incluidos los dos; la de un rango de dos años se acepta; y la de un rango al revés responde 422 en hasta con el mensaje de exportacion-E06, sin crear ninguna descarga')]
    public function test_administracion_app_e60_el_rango_de_la_descarga(): void
    {
        foreach (['2027-03-09', '2027-03-10', '2027-03-20', '2027-03-21'] as $dia) {
            $this->pedidoConfirmadoEl("{$dia} 10:00:00");
        }
        $token = $this->tokenDeLaApp($this->cuentaDeAdministracion());

        $marzo = $this->pedirPorLaApp($token, PedidosTotalesExportador::NOMBRE, ['desde' => '2027-03-10', 'hasta' => '2027-03-20', 'granularidad' => 'dia'])
            ->assertStatus(202)
            ->json('descarga');
        $filas = array_slice($this->filasDe($this->archivoPorLaApp($token, $marzo['id_publico'], 'csv')->assertOk()->streamedContent()), 1);
        $this->assertCount(11, $filas);
        $this->assertStringEndsWith('10/03/2027', $filas[0][0]);
        $this->assertStringEndsWith('20/03/2027', $filas[10][0]);
        // Los pedidos del 10 y del 20 cuentan; los del 9 y del 21, no.
        $this->assertSame(['1', '0', '0', '0', '0', '0', '0', '0', '0', '0', '1'], array_column($filas, 1));

        $dosAnios = $this->pedirPorLaApp($token, PedidosTotalesExportador::NOMBRE, ['desde' => '2025-01-01', 'hasta' => '2026-12-31', 'granularidad' => 'dia'])
            ->assertStatus(202)
            ->json('descarga');
        $this->assertSame('pedidos-totales-2025-01-01-al-2026-12-31.csv', $dosAnios['archivos']['csv']);
        $this->assertCount(731, $this->filasDe($this->archivoPorLaApp($token, $dosAnios['id_publico'], 'csv')->assertOk()->streamedContent()));

        $antes = Exportacion::count();
        $this->pedirPorLaApp($token, PedidosTotalesExportador::NOMBRE, ['desde' => '2027-03-20', 'hasta' => '2027-03-10', 'granularidad' => 'dia'])
            ->assertStatus(422)
            ->assertJsonPath('errors.hasta', [self::FIN_ANTERIOR]);
        $this->assertSame($antes, Exportacion::count());

        // Sin fechas, también 422, en las dos: la regla del rango (App\Validacion\RangoDeFechas).
        $this->llamarConToken($token, 'POST', self::DESCARGAS, ['reporte' => PedidosTotalesExportador::NOMBRE, 'granularidad' => 'dia'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['desde', 'hasta']);
        $this->assertSame($antes, Exportacion::count());
    }

    #[TestDox('administracion-app-E60: una fecha que no viene en AAAA-MM-DD, aunque se lea como fecha, como "December 5, 2026", "05/12/2026" o con su hora, responde 422 en su campo y no crea ninguna descarga: el archivo no sale de otro rango sin avisar')]
    public function test_administracion_app_e60_una_fecha_que_no_viene_en_aaaa_mm_dd_responde_422_sin_crear_nada(): void
    {
        $token = $this->tokenDeLaApp($this->cuentaDeAdministracion());

        foreach (['December 5, 2026', '05/12/2026', '2026-12-05 00:00:00'] as $fecha) {
            $this->pedirPorLaApp($token, PedidosTotalesExportador::NOMBRE, ['desde' => '2026-12-01', 'hasta' => $fecha, 'granularidad' => 'dia'])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['hasta']);
            $this->pedirPorLaApp($token, PedidosTotalesExportador::NOMBRE, ['desde' => $fecha, 'hasta' => '2026-12-31', 'granularidad' => 'dia'])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['desde']);
        }

        $this->assertSame(0, Exportacion::count());

        // En AAAA-MM-DD, el mismo rango sí se pide, y su archivo es del 1 al 5 de diciembre.
        $this->pedirPorLaApp($token, PedidosTotalesExportador::NOMBRE, ['desde' => '2026-12-01', 'hasta' => '2026-12-05', 'granularidad' => 'dia'])
            ->assertStatus(202)
            ->assertJsonPath('descarga.archivos.csv', 'pedidos-totales-2026-12-01-al-2026-12-05.csv');
    }

    #[TestDox('administracion-app-E61: con una colonia con acento y eñe, otra capturada como "=1+1" y una cifra negativa, el CSV y el XLSX que baja la app son los que baja el panel para el mismo reporte y el mismo rango, y cumplen exportacion-E07, E08 y E09')]
    public function test_administracion_app_e61_el_archivo_es_el_del_panel(): void
    {
        // El monto promedio baja de 300 a 200: la diferencia es -100.00.
        $this->conMonto($this->pedidoConfirmadoEl('2026-11-24 10:00:00'), '300.00');
        $this->conMonto($this->pedidoConfirmadoEl('2026-12-01 10:00:00', recogida: ['colonia' => 'Peñón de Asunción']), '200.00');
        $this->conMonto($this->pedidoConfirmadoEl('2026-12-02 10:00:00', recogida: ['colonia' => '=1+1']), '200.00');
        $administracion = $this->cuentaDeAdministracion();
        $token = $this->tokenDeLaApp($administracion);

        $deLaApp = [];

        foreach (['pedidos-por-colonia' => 'descargarPedidosPorColonia', 'monto-promedio-por-pedido' => 'descargarMontoPromedioPorPedido'] as $reporte => $accion) {
            $id = $this->pedirPorLaApp($token, $reporte)->assertStatus(202)->json('descarga.id_publico');
            $this->comoElPanel($administracion);
            $this->pedirDescarga($accion, self::SEMANA)->assertHasNoActionErrors();
            $panel = $this->ultimaExportacion();

            $csv = $this->archivoPorLaApp($token, $id, 'csv')->assertOk()->streamedContent();
            $bytesDelXlsx = $this->archivoPorLaApp($token, $id, 'xlsx')->assertOk()->streamedContent();
            $xlsx = $this->celdasDe($bytesDelXlsx);

            // El CSV, byte por byte; el XLSX, celda por celda, con su tipo y su valor.
            $this->comoElPanel($administracion);
            $this->assertSame($this->contenidoDelCsv($panel), $csv, $reporte);
            $this->assertSame($this->tiposYValores($this->celdasDelXlsx($panel)), $this->tiposYValores($xlsx), $reporte);
            $this->assertStringStartsWith(self::BOM, $csv, $reporte);
            $this->assertSame(1, substr_count($csv, self::BOM), $reporte);

            $deLaApp[$reporte] = [$this->filasDe($csv), $xlsx, $this->hojaDe($bytesDelXlsx)];
        }

        // exportacion-E07 y E08: el acento y la eñe como se capturaron; "=1+1", texto, con apóstrofo en el CSV. En el
        // XLSX, celda de texto sin fórmula, como lo revisa EscrituraDelArchivoTest: el lector de OpenSpout da por fórmula
        // cualquier texto que empieza con "=", así que se mira la hoja.
        [$csv, $xlsx, $hoja] = $deLaApp['pedidos-por-colonia'];
        $this->assertEqualsCanonicalizing(['Peñón de Asunción', "'=1+1"], array_column(array_slice($csv, 1), 0));
        $enElXlsx = array_column(array_slice($xlsx, 1), 0);
        $this->assertEqualsCanonicalizing(['Peñón de Asunción', '=1+1'], array_map(fn (Cell $celda): string => (string) $celda->getValue(), $enElXlsx));
        $this->assertStringNotContainsString('<f>', $hoja);
        $this->assertStringContainsString('>=1+1<', $hoja);

        // exportacion-E09: la cifra negativa sigue siendo número.
        [$csv, $xlsx] = $deLaApp['monto-promedio-por-pedido'];
        $this->assertSame('-100.00', $csv[1][4]);
        $this->assertInstanceOf(NumericCell::class, $xlsx[1][4]);
        $this->assertEquals(-100.0, $xlsx[1][4]->getValue());
    }

    #[TestDox('administracion-app-E62: las rutas del estado y del archivo nombran la descarga por su campo id_publico, {descarga:id_publico}, y su dirección queda descargas/{descarga}; el controlador la recibe como texto, sin el enlace implícito de Laravel, y la dirección de una descarga lleva su ULID, nunca su número de fila')]
    public function test_administracion_app_e62_las_rutas_nombran_la_descarga_por_su_id_publico(): void
    {
        $exportacion = (new Exportacion)->forceFill(['id' => 41, 'id_publico' => strtolower((string) Str::ulid())]);
        $rutas = [
            'administracion.descargas.mostrar' => ['api/administracion/descargas/{descarga}', []],
            'administracion.descargas.archivo' => ['api/administracion/descargas/{descarga}/{formato}', ['formato' => 'csv']],
        ];

        foreach ($rutas as $nombre => [$direccion, $demas]) {
            $ruta = Route::getRoutes()->getByName($nombre);
            $this->assertNotNull($ruta, $nombre);
            $this->assertSame($direccion, $ruta->uri(), $nombre);
            $this->assertSame('id_publico', $ruta->bindingFieldFor('descarga'), $nombre);
            $this->assertSame([], $ruta->signatureParameters(['subClass' => UrlRoutable::class]), $nombre);

            $enlace = route($nombre, ['descarga' => $exportacion, ...$demas], absolute: false);
            $this->assertStringStartsWith("/api/administracion/descargas/{$exportacion->id_publico}", $enlace, $nombre);
            $this->assertStringNotContainsString('/descargas/41', $enlace, $nombre);
        }
    }

    #[TestDox('administracion-app-E62: con una descarga lista de una cuenta de administración, otra cuenta de administración que pide su estado o su archivo recibe 404 sin datos (exportacion-E11), y la ruta la nombra por su ULID: su número de fila, un valor que no es ULID y un formato que no es csv ni xlsx responden 404; la dueña sí la ve y la baja')]
    public function test_administracion_app_e62_solo_la_cuenta_que_la_pidio_baja_el_archivo(): void
    {
        $duena = $this->cuentaDeAdministracion();
        $token = $this->tokenDeLaApp($duena);
        $id = $this->pedirPorLaApp($token, PedidosTotalesExportador::NOMBRE)->assertStatus(202)->json('descarga.id_publico');
        $otra = $this->tokenDeLaApp($this->cuentaDeAdministracion());
        $exportacion = Exportacion::sole();

        $this->consultarPorLaApp($otra, $id)->assertNotFound()->assertExactJson(self::NO_EXISTE_LA_DESCARGA);

        foreach (['csv', 'xlsx', 'pdf'] as $formato) {
            $this->archivoPorLaApp($otra, $id, $formato)->assertNotFound()->assertExactJson(self::NO_EXISTE_LA_DESCARGA);
        }

        // Por su número de fila, o con un valor que no es ULID, no llega nadie, tampoco la dueña.
        foreach ([(string) $exportacion->id, 'no-es-un-ulid', $id.'0'] as $valor) {
            $this->consultarPorLaApp($token, $valor)->assertNotFound()->assertExactJson(self::NO_EXISTE_LA_DESCARGA);
            $this->archivoPorLaApp($token, $valor, 'csv')->assertNotFound()->assertExactJson(self::NO_EXISTE_LA_DESCARGA);
        }

        foreach (['pdf', 'json', 'CSV'] as $formato) {
            $this->archivoPorLaApp($token, $id, $formato)->assertNotFound()->assertExactJson(self::NO_EXISTE_EL_ARCHIVO);
        }

        // La dueña sí, también con el ULID en mayúsculas.
        $this->consultarPorLaApp($token, $id)->assertOk()->assertJsonPath('descarga.id_publico', $id);
        $this->consultarPorLaApp($token, Str::upper($id))->assertOk()->assertJsonPath('descarga.id_publico', $id);
        $this->archivoPorLaApp($token, $id, 'csv')->assertOk();
        $this->archivoPorLaApp($token, $id, 'xlsx')->assertOk();
    }

    #[TestDox('administracion-app-E62, administracion-app-E06 y administracion-app-E07: las cuatro rutas de las descargas responden 403 sin datos a las cuentas de usuario, de lavandería y de repartidor y a un token de administración sin la habilidad, 401 sin sesión o con el token vencido, y ninguna de esas llamadas crea una descarga ni da el archivo')]
    public function test_administracion_app_e62_otro_rol_recibe_403_y_sin_sesion_401(): void
    {
        $id = $this->pedirPorLaApp($this->tokenDeLaApp($this->cuentaDeAdministracion()), PedidosTotalesExportador::NOMBRE)
            ->assertStatus(202)
            ->json('descarga.id_publico');

        $this->assertSoloEntraLaAdministracion('POST', self::DESCARGAS, ['reporte' => PedidosTotalesExportador::NOMBRE, ...self::SEMANA]);
        $this->assertSoloEntraLaAdministracion('GET', self::DESCARGAS);
        $this->assertSoloEntraLaAdministracion('GET', self::DESCARGAS."/{$id}");

        foreach (['csv', 'xlsx'] as $formato) {
            $this->assertSoloEntraLaAdministracion('GET', self::DESCARGAS."/{$id}/{$formato}");
        }

        $this->assertSame(1, Exportacion::count());
    }

    #[TestDox('administracion-app-E12: un identificador que no es de ninguna descarga responde 404 sin datos, en su estado y en su archivo')]
    public function test_administracion_app_e12_una_descarga_que_no_existe_responde_404(): void
    {
        $token = $this->tokenDeLaApp($this->cuentaDeAdministracion());
        $ninguna = (string) Str::ulid();

        $this->consultarPorLaApp($token, $ninguna)->assertNotFound()->assertExactJson(self::NO_EXISTE_LA_DESCARGA);
        $this->archivoPorLaApp($token, $ninguna, 'csv')->assertNotFound()->assertExactJson(self::NO_EXISTE_LA_DESCARGA);
        $this->llamarConToken($token, 'GET', self::DESCARGAS)->assertOk()->assertExactJson(['descargas' => [], 'dias_de_vida' => Exportacion::diasDeVida()]);
    }

    #[TestDox('administracion-app-E56 y administracion-app-E60: el contrato de pedir una descarga con la app: el 202 con la descarga, y el 422 con el error de cada campo')]
    public function test_administracion_app_e56_el_contrato_de_pedir_una_descarga(): void
    {
        $this->sembrarLaSemana();
        $token = $this->tokenDeLaApp($this->cuentaDeAdministracion());

        $this->assertCumpleSuContrato('descargas.nueva', 'POST /api/administracion/descargas', $this->pedirPorLaApp($token, PedidosTotalesExportador::NOMBRE), 202);
        $this->assertCumpleSuContrato(
            'descargas.nueva-invalida',
            'POST /api/administracion/descargas',
            $this->pedirPorLaApp($token, PedidosTotalesExportador::NOMBRE, ['desde' => '2026-12-06', 'hasta' => '2026-11-30', 'granularidad' => 'dia']),
            422,
        );
    }

    #[TestDox('administracion-app-E59: el contrato de la lista de descargas con la app: cada descarga y sus días de vida; sin descargas, la lista vacía')]
    public function test_administracion_app_e59_el_contrato_de_la_lista(): void
    {
        $token = $this->tokenDeLaApp($this->cuentaDeAdministracion());
        $this->assertCumpleSuContrato('descargas.lista-vacia', 'GET /api/administracion/descargas', $this->llamarConToken($token, 'GET', self::DESCARGAS));

        $this->sembrarLaSemana();
        $this->pedirPorLaApp($token, PedidosTotalesExportador::NOMBRE)->assertStatus(202);

        $this->assertCumpleSuContrato('descargas.lista', 'GET /api/administracion/descargas', $this->llamarConToken($token, 'GET', self::DESCARGAS));
    }

    #[TestDox('administracion-app-E57, administracion-app-E58 y administracion-app-E62: el contrato de una descarga con la app: su estado, el 404 de la que no existe, y el 404 del archivo en un formato que no hay')]
    public function test_administracion_app_e57_el_contrato_de_una_descarga(): void
    {
        $this->sembrarLaSemana();
        $token = $this->tokenDeLaApp($this->cuentaDeAdministracion());
        $idPublico = $this->pedirPorLaApp($token, PedidosTotalesExportador::NOMBRE)->assertStatus(202)->json('descarga.id_publico');

        $this->assertCumpleSuContrato('descargas.descarga', 'GET /api/administracion/descargas/{descarga}', $this->consultarPorLaApp($token, $idPublico));
        $this->assertCumpleSuContrato(
            'descargas.no-existe',
            'GET /api/administracion/descargas/{descarga}',
            $this->consultarPorLaApp($token, (string) Str::ulid()),
            404,
        );
        $this->assertCumpleSuContrato(
            'descargas.archivo-que-no-existe',
            'GET /api/administracion/descargas/{descarga}/{formato}',
            $this->archivoPorLaApp($token, $idPublico, 'pdf'),
            404,
        );
    }

    /**
     * La administración pide en la app la descarga de ese reporte, con ese rango o con el de la semana.
     *
     * @param  array<string, string>  $rango
     */
    private function pedirPorLaApp(string $token, string $reporte, array $rango = self::SEMANA): TestResponse
    {
        return $this->llamarConToken($token, 'POST', self::DESCARGAS, ['reporte' => $reporte, ...$rango]);
    }

    /**
     * La app consulta el estado de la descarga, como cada 5 s mientras se genera.
     */
    private function consultarPorLaApp(string $token, string $idPublico): TestResponse
    {
        return $this->llamarConToken($token, 'GET', self::DESCARGAS."/{$idPublico}");
    }

    /**
     * La app baja el archivo de la descarga en ese formato.
     */
    private function archivoPorLaApp(string $token, string $idPublico, string $formato): TestResponse
    {
        return $this->llamarConToken($token, 'GET', self::DESCARGAS."/{$idPublico}/{$formato}");
    }

    /**
     * Los id_publico de la lista de descargas de la cuenta, en su orden.
     *
     * @return list<string>
     */
    private function idsDeLaLista(string $token): array
    {
        return array_column($this->llamarConToken($token, 'GET', self::DESCARGAS)->assertOk()->json('descargas'), 'id_publico');
    }

    /**
     * La cuenta en el panel de respaldo, con su sesión web, sin volver a preparar el disco de las descargas.
     */
    private function comoElPanel(Usuario $administracion): void
    {
        $this->app['auth']->forgetGuards();
        Filament::setCurrentPanel('admin');
        $this->actingAs($administracion, 'web');
    }

    /**
     * El proceso de colas, en este mismo proceso, hasta vaciarla. Su tope de memoria va por encima de lo que ya usa la
     * suite, como en GeneracionEnSegundoPlanoTest.
     */
    private function correrLaCola(): void
    {
        $this->artisan('queue:work', [
            '--stop-when-empty' => true,
            '--memory' => (int) ceil(memory_get_usage(true) / 1048576) + 256,
        ])->assertSuccessful();
    }

    /**
     * Las filas de un CSV, con el encabezado primero, cada celda como texto.
     *
     * @return list<list<string>>
     */
    private function filasDe(string $csv): array
    {
        return array_values(array_map('array_values', iterator_to_array(LectorDeCsv::fromString($csv)->getRecords(), false)));
    }

    /**
     * Las celdas de un XLSX, fila por fila, con el encabezado primero.
     *
     * @return list<list<Cell>>
     */
    private function celdasDe(string $xlsx): array
    {
        $archivo = (string) tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($archivo, $xlsx);

        $lector = new LectorDeXlsx;
        $lector->open($archivo);
        $filas = [];

        foreach ($lector->getSheetIterator() as $hoja) {
            foreach ($hoja->getRowIterator() as $fila) {
                $filas[] = array_values($fila->getCells());
            }

            break;
        }

        $lector->close();
        unlink($archivo);

        return $filas;
    }

    /**
     * La hoja de un XLSX y sus textos compartidos, como XML, para ver si alguna celda lleva fórmula.
     */
    private function hojaDe(string $xlsx): string
    {
        $archivo = (string) tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($archivo, $xlsx);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($archivo));
        $hoja = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $compartidas = (string) $zip->getFromName('xl/sharedStrings.xml');
        $zip->close();
        unlink($archivo);

        return $hoja.$compartidas;
    }

    /**
     * Cada celda de un XLSX como su tipo y su valor, para comparar dos archivos.
     *
     * @param  list<list<Cell>>  $celdas
     * @return list<list<array{0: class-string<Cell>, 1: mixed}>>
     */
    private function tiposYValores(array $celdas): array
    {
        return array_map(
            fn (array $fila): array => array_map(fn (Cell $celda): array => [$celda::class, $celda->getValue()], $fila),
            $celdas,
        );
    }

    /**
     * Tres pedidos confirmados el lunes 30 de noviembre y dos el miércoles 2 de diciembre.
     */
    private function sembrarLaSemana(): void
    {
        foreach (['08:00', '09:00', '10:00'] as $hora) {
            $this->pedidoConfirmadoEl("2026-11-30 {$hora}:00");
        }

        foreach (['08:00', '09:00'] as $hora) {
            $this->pedidoConfirmadoEl("2026-12-02 {$hora}:00");
        }
    }

    /**
     * Nada calcula todavía el monto de un pedido (DD-012): la prueba lo escribe en los dos campos.
     */
    private function conMonto(Pedido $pedido, string $monto): Pedido
    {
        DB::table('pedidos')->where('id_pedidos', $pedido->getKey())->update(['monto_estimado' => $monto, 'monto_final' => $monto]);

        return $pedido;
    }
}
