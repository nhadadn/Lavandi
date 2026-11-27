<?php

namespace Tests\Feature\AdministracionEnLaApp;

use App\Autorizacion\Autorizacion;
use App\Comisiones\PorcentajesDeComision;
use App\Models\Comision;
use App\Models\Lavanderia;
use App\Models\Plaza;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\AdministracionEnLaApp\Soporte\ContratosDeLaApp;
use Tests\Feature\AdministracionEnLaApp\Soporte\SesionesDeLaApp;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * F-D05, las comisiones en la API de la administración: el porcentaje global y el propio de cada lavandería, de todas
 * las plazas, que se fijan y se quitan con las reglas del panel (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D
 * #5, "Porcentaje de comisión por socio o global."; ADR-0022, decisión 9; design.md, sección 4, F-D05).
 *
 * Cada ruta llama a la misma clase de dominio que el panel, App\Comisiones\PorcentajesDeComision, y a las lavanderías
 * del componente, Autorizacion::lavanderiasDelPanel(): la prueba compara su respuesta con esa clase para los mismos
 * datos (administracion-app-E11). Que el panel no cambió lo prueba, sin tocarla,
 * tests/Feature/Administracion/ConfiguracionDeComisionesTest.php (administracion-E11 a E13).
 *
 * Ningún porcentaje viene de fábrica: los de estas pruebas son de prueba, y los de producción los captura EL CLIENTE. La
 * comisión del repartidor espera DD-019 (administracion-app-E35) y la de cada pedido, DD-020 y DD-013
 * (administracion-app-E36): aquí no se prueban.
 *
 * Escenarios: administracion-app-E06, administracion-app-E07, administracion-app-E11, administracion-app-E12,
 * administracion-app-E32, administracion-app-E33 y administracion-app-E34 · Spec:
 * openspec/changes/administracion-en-la-app/specs/administracion-en-la-app/spec.md · Tarea 11.1
 */
class ComisionesTest extends TestCase
{
    use ContratosDeLaApp;
    use PlazasDePrueba;
    use RefreshDatabase;
    use SesionesDeLaApp;

    private const COMISIONES = '/api/administracion/comisiones';

    private const GLOBAL = '/api/administracion/comisiones/global';

    /** El mensaje del porcentaje fuera de rango, el de administracion-E13. */
    private const FUERA_DE_RANGO = 'El porcentaje va de 0 a 100.';

    private Plaza $plazaUno;

    private Plaza $plazaDos;

    /** Activa, en la plaza Uno. */
    private Lavanderia $primera;

    /** Suspendida, en la plaza Dos. */
    private Lavanderia $segunda;

    /** De baja, en la plaza Uno: la lista del panel la trae también. */
    private Lavanderia $tercera;

    private Usuario $administracion;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-12-01 12:00:00', 'UTC'));
        $this->plazaUno = $this->plazaDePrueba('Plaza de prueba Uno');
        $this->plazaDos = $this->plazaDePrueba('Plaza de prueba Dos');
        $this->primera = Lavanderia::factory()->en($this->plazaUno)->create(['nombre' => 'Lavandería de prueba A']);
        $this->segunda = Lavanderia::factory()->en($this->plazaDos)->suspendida()->create(['nombre' => 'Lavandería de prueba B']);
        $this->tercera = Lavanderia::factory()->en($this->plazaUno)->deBaja()->create(['nombre' => 'Lavandería de prueba C']);
        $this->administracion = $this->cuentaDeAdministracion();
        $this->token = $this->tokenDeLaApp($this->administracion);
    }

    #[TestDox('administracion-app-E32: sin porcentaje global guardado, GET /api/administracion/comisiones dice que no hay global y no trae ningún número: ninguna lavandería tiene propio ni le aplica ninguno, y la consulta no guarda ningún porcentaje de fábrica')]
    public function test_administracion_app_e32_sin_porcentaje_global_la_respuesta_no_trae_ningun_numero(): void
    {
        $respuesta = $this->llamarConToken($this->token, 'GET', self::COMISIONES)->assertOk();

        $respuesta->assertExactJson([
            'global' => null,
            'lavanderias' => [
                $this->fila($this->primera, $this->plazaUno, propio: null, aplica: null),
                $this->fila($this->segunda, $this->plazaDos, propio: null, aplica: null),
                $this->fila($this->tercera, $this->plazaUno, propio: null, aplica: null),
            ],
        ]);
        $this->assertSame(0, Comision::count());
    }

    #[TestDox('administracion-app-E32: sin porcentaje global, la lavandería con porcentaje propio dice el suyo, y a las demás no les aplica ninguno: la respuesta no inventa un global')]
    public function test_administracion_app_e32_sin_global_solo_la_del_porcentaje_propio_trae_numero(): void
    {
        $this->porcentajes()->fijarPropio($this->segunda, '6');

        $this->llamarConToken($this->token, 'GET', self::COMISIONES)
            ->assertOk()
            ->assertExactJson([
                'global' => null,
                'lavanderias' => [
                    $this->fila($this->primera, $this->plazaUno, propio: null, aplica: null),
                    $this->fila($this->segunda, $this->plazaDos, propio: '6.00', aplica: ['porcentaje' => '6.00', 'origen' => 'propio']),
                    $this->fila($this->tercera, $this->plazaUno, propio: null, aplica: null),
                ],
            ]);
    }

    #[TestDox('administracion-app-E32: con un porcentaje global guardado y una lavandería con porcentaje propio, en plazas distintas, la respuesta trae el global y desde cuándo rige, y cada lavandería de todas las plazas y en cualquier estado, con su propio si lo tiene y el que le aplica con de dónde sale')]
    public function test_administracion_app_e32_el_porcentaje_global_y_el_de_cada_lavanderia(): void
    {
        $this->porcentajes()->fijarGlobal('10');
        $this->travelTo(CarbonImmutable::parse('2026-12-02 15:30:00', 'UTC'));
        $this->porcentajes()->fijarPropio($this->segunda, '7.25');

        $this->llamarConToken($this->token, 'GET', self::COMISIONES)
            ->assertOk()
            ->assertExactJson([
                'global' => ['porcentaje' => '10.00', 'vigente_desde' => '2026-12-01T12:00:00Z'],
                'lavanderias' => [
                    $this->fila($this->primera, $this->plazaUno, propio: null, aplica: ['porcentaje' => '10.00', 'origen' => 'global']),
                    $this->fila($this->segunda, $this->plazaDos, propio: '7.25', aplica: ['porcentaje' => '7.25', 'origen' => 'propio']),
                    $this->fila($this->tercera, $this->plazaUno, propio: null, aplica: ['porcentaje' => '10.00', 'origen' => 'global']),
                ],
            ]);
    }

    #[TestDox('administracion-app-E11: GET /api/administracion/comisiones da lo que dan, para los mismos datos, las comisiones del panel: el global de globalVigente(), y por cada lavandería de lavanderiasDelPanel() su propioVigente() y su queAplica(); una lavandería borrada no sale en ninguno de los dos')]
    public function test_administracion_app_e11_la_api_da_lo_que_dan_las_comisiones_del_panel(): void
    {
        $this->porcentajes()->fijarGlobal('11.5');
        $this->travelTo(CarbonImmutable::parse('2026-12-03 09:00:00', 'UTC'));
        $this->porcentajes()->fijarPropio($this->primera, '4');
        $this->porcentajes()->fijarPropio($this->tercera, '0');
        $borrada = Lavanderia::factory()->en($this->plazaDos)->create(['nombre' => 'Lavandería de prueba borrada']);
        $borrada->delete();

        $respuesta = $this->llamarConToken($this->token, 'GET', self::COMISIONES)->assertOk();

        $global = $this->porcentajes()->globalVigente();
        $this->assertSame(
            ['porcentaje' => $global?->porcentaje, 'vigente_desde' => $global?->vigente_desde->toIso8601ZuluString()],
            $respuesta->json('global'),
        );

        $delPanel = app(Autorizacion::class)->lavanderiasDelPanel($this->administracion)->orderBy('nombre')->get();
        $this->assertNotContains($borrada->id_publico, $delPanel->pluck('id_publico'));
        $this->assertSame($delPanel->pluck('id_publico')->all(), array_column($respuesta->json('lavanderias'), 'id_publico'));

        foreach ($respuesta->json('lavanderias') as $lugar => $fila) {
            $lavanderia = $delPanel[$lugar];
            $aplica = $this->porcentajes()->queAplica($lavanderia);

            $this->assertSame($this->porcentajes()->propioVigente($lavanderia)?->porcentaje, $fila['propio'], $lavanderia->nombre);
            $this->assertSame(
                ['porcentaje' => $aplica?->porcentaje, 'origen' => $aplica?->id_lavanderias === null ? 'global' : 'propio'],
                $fila['aplica'],
                $lavanderia->nombre,
            );
        }
    }

    #[TestDox('administracion-app-E33: PUT /api/administracion/comisiones/global guarda el porcentaje global con la regla de administracion-E11: le aplica a toda lavandería sin porcentaje propio, de cualquier plaza, y la respuesta trae los porcentajes nuevos y el que aplica a cada lavandería')]
    public function test_administracion_app_e33_fijar_el_porcentaje_global(): void
    {
        $respuesta = $this->llamarConToken($this->token, 'PUT', self::GLOBAL, ['porcentaje' => '12.5'])->assertOk();

        $this->assertSame($this->llamarConToken($this->token, 'GET', self::COMISIONES)->json(), $respuesta->json());
        $respuesta->assertJsonPath('global', ['porcentaje' => '12.50', 'vigente_desde' => '2026-12-01T12:00:00Z']);
        foreach ($respuesta->json('lavanderias') as $fila) {
            $this->assertSame(['porcentaje' => '12.50', 'origen' => 'global'], $fila['aplica'], $fila['nombre']);
        }

        $global = Comision::sole();
        $this->assertSame([null, null, '12.50'], [$global->id_plazas, $global->id_lavanderias, $global->porcentaje]);
        $this->assertTrue($global->is($this->porcentajes()->globalVigente()));

        // Otro global, más tarde: el anterior queda como historial, como en el panel.
        $this->travelTo(CarbonImmutable::parse('2026-12-04 08:00:00', 'UTC'));
        $this->llamarConToken($this->token, 'PUT', self::GLOBAL, ['porcentaje' => 9])
            ->assertOk()
            ->assertJsonPath('global', ['porcentaje' => '9.00', 'vigente_desde' => '2026-12-04T08:00:00Z']);

        $this->assertSame(2, Comision::count());
        $this->assertSame('2026-12-04T08:00:00Z', $global->refresh()->vigente_hasta?->toIso8601ZuluString());
        $this->assertSame('12.50', $this->porcentajes()->globalVigente(CarbonImmutable::parse('2026-12-02 00:00:00', 'UTC'))?->porcentaje);
    }

    #[TestDox('administracion-app-E33: PUT /api/administracion/comisiones/lavanderias/{lavanderia} guarda el porcentaje propio con la regla de administracion-E12: a esa le aplica el suyo y a las demás el global; DELETE lo quita, le vuelve a aplicar el global y el propio queda como historial; quitar uno que no tiene no cambia nada')]
    public function test_administracion_app_e33_fijar_y_quitar_el_porcentaje_propio(): void
    {
        $this->porcentajes()->fijarGlobal('10');
        $this->travelTo(CarbonImmutable::parse('2026-12-02 12:00:00', 'UTC'));
        $deLaPrimera = self::COMISIONES.'/lavanderias/'.$this->primera->id_publico;

        $fijado = $this->llamarConToken($this->token, 'PUT', $deLaPrimera, ['porcentaje' => '7.25'])->assertOk();

        $this->assertSame($this->llamarConToken($this->token, 'GET', self::COMISIONES)->json(), $fijado->json());
        $this->assertSame(
            [
                $this->fila($this->primera, $this->plazaUno, propio: '7.25', aplica: ['porcentaje' => '7.25', 'origen' => 'propio']),
                $this->fila($this->segunda, $this->plazaDos, propio: null, aplica: ['porcentaje' => '10.00', 'origen' => 'global']),
                $this->fila($this->tercera, $this->plazaUno, propio: null, aplica: ['porcentaje' => '10.00', 'origen' => 'global']),
            ],
            $fijado->json('lavanderias'),
        );
        $propio = $this->porcentajes()->propioVigente($this->primera);
        $this->assertSame(['7.25', $this->plazaUno->getKey()], [$propio?->porcentaje, $propio?->id_plazas]);

        $this->travelTo(CarbonImmutable::parse('2026-12-03 12:00:00', 'UTC'));
        $quitado = $this->llamarConToken($this->token, 'DELETE', $deLaPrimera)->assertOk();

        $this->assertSame($this->llamarConToken($this->token, 'GET', self::COMISIONES)->json(), $quitado->json());
        $quitado->assertJsonPath('lavanderias.0', $this->fila($this->primera, $this->plazaUno, propio: null, aplica: ['porcentaje' => '10.00', 'origen' => 'global']));
        $this->assertNull($this->porcentajes()->propioVigente($this->primera));
        $this->assertSame('2026-12-03T12:00:00Z', $propio->refresh()->vigente_hasta?->toIso8601ZuluString());

        // Sin propio vigente, quitarlo no hace nada, como en el panel.
        $antes = $this->fotoDeLaBase();
        $this->llamarConToken($this->token, 'DELETE', $deLaPrimera)->assertOk()->assertJsonPath('lavanderias.0.propio', null);
        $this->assertSame($antes, $this->fotoDeLaBase());
    }

    #[TestDox('administracion-app-E33: el ULID de la lavandería escrito en mayúsculas es el mismo: PUT fija su porcentaje propio y DELETE lo quita, y las demás lavanderías no cambian')]
    public function test_administracion_app_e33_el_ulid_de_la_lavanderia_en_mayusculas(): void
    {
        $this->porcentajes()->fijarGlobal('10');
        $this->travelTo(CarbonImmutable::parse('2026-12-02 12:00:00', 'UTC'));
        $enMayusculas = self::COMISIONES.'/lavanderias/'.Str::upper($this->primera->id_publico);
        $this->assertNotSame($this->primera->id_publico, Str::upper($this->primera->id_publico));

        $this->llamarConToken($this->token, 'PUT', $enMayusculas, ['porcentaje' => '6.5'])
            ->assertOk()
            ->assertJsonPath('lavanderias.0', $this->fila($this->primera, $this->plazaUno, propio: '6.50', aplica: ['porcentaje' => '6.50', 'origen' => 'propio']));

        $this->assertSame('6.50', $this->porcentajes()->propioVigente($this->primera)?->porcentaje);
        $this->assertNull($this->porcentajes()->propioVigente($this->segunda));
        $this->assertNull($this->porcentajes()->propioVigente($this->tercera));

        $this->travelTo(CarbonImmutable::parse('2026-12-03 12:00:00', 'UTC'));
        $this->llamarConToken($this->token, 'DELETE', $enMayusculas)
            ->assertOk()
            ->assertJsonPath('lavanderias.0', $this->fila($this->primera, $this->plazaUno, propio: null, aplica: ['porcentaje' => '10.00', 'origen' => 'global']));

        $this->assertNull($this->porcentajes()->propioVigente($this->primera));
    }

    #[TestDox('administracion-app-E11: el porcentaje va en el cuerpo: uno que llega solo en la consulta (?porcentaje=) no cuenta y responde 422 con el error que da PorcentajesDeComision sin porcentaje, "Escribe el porcentaje.", en el global y en el propio, y el vigente no cambia')]
    public function test_administracion_app_e11_el_porcentaje_solo_en_la_consulta_no_cuenta(): void
    {
        $this->porcentajes()->fijarGlobal('10');
        $this->porcentajes()->fijarPropio($this->primera, '8');
        $antes = $this->fotoDeLaBase();
        $this->travelTo(CarbonImmutable::parse('2026-12-02 12:00:00', 'UTC'));
        $sinPorcentaje = $this->erroresDeLasComisiones(null);
        $this->assertSame(['porcentaje' => ['Escribe el porcentaje.']], $sinPorcentaje);

        foreach ([self::GLOBAL, self::COMISIONES.'/lavanderias/'.$this->primera->id_publico] as $url) {
            $this->llamarConToken($this->token, 'PUT', $url.'?porcentaje=7')
                ->assertUnprocessable()
                ->assertExactJson(['message' => 'Escribe el porcentaje.', 'errors' => $sinPorcentaje]);
        }

        $this->assertSame($antes, $this->fotoDeLaBase());
        $this->assertSame('10.00', $this->porcentajes()->globalVigente()?->porcentaje);
        $this->assertSame('8.00', $this->porcentajes()->propioVigente($this->primera)?->porcentaje);
    }

    #[TestDox('administracion-app-E34: con un porcentaje vigente, uno menor que 0 o mayor que 100 responde 422 con el mensaje de administracion-E13 en porcentaje, en el global y en el propio, y el vigente no cambia')]
    public function test_administracion_app_e34_un_porcentaje_fuera_de_rango(): void
    {
        $this->porcentajes()->fijarGlobal('10');
        $this->porcentajes()->fijarPropio($this->primera, '8');
        $antes = $this->fotoDeLaBase();
        $this->travelTo(CarbonImmutable::parse('2026-12-02 12:00:00', 'UTC'));

        foreach (['-1', '-0.01', '100.01', '150', -5, 101] as $porcentaje) {
            foreach ([self::GLOBAL, self::COMISIONES.'/lavanderias/'.$this->primera->id_publico] as $url) {
                $this->llamarConToken($this->token, 'PUT', $url, ['porcentaje' => $porcentaje])
                    ->assertUnprocessable()
                    ->assertExactJson(['message' => self::FUERA_DE_RANGO, 'errors' => ['porcentaje' => [self::FUERA_DE_RANGO]]]);
            }
        }

        $this->assertSame($antes, $this->fotoDeLaBase());
        $this->assertSame('10.00', $this->porcentajes()->globalVigente()?->porcentaje);
        $this->assertSame('8.00', $this->porcentajes()->propioVigente($this->primera)?->porcentaje);
    }

    #[TestDox('administracion-app-E11: la API rechaza con las reglas y los mensajes de las comisiones, los mismos del panel: sin porcentaje, vacío, con texto, con más de dos decimales o en una lista responde 422 con los errores que da PorcentajesDeComision para el mismo valor, sin guardar nada; 0 y 100 entran')]
    public function test_administracion_app_e11_la_api_rechaza_con_las_reglas_de_las_comisiones(): void
    {
        $mensajes = PorcentajesDeComision::mensajes();
        $casos = [
            'sin porcentaje' => [[], null, $mensajes['porcentaje.required']],
            'vacío' => [['porcentaje' => ''], '', $mensajes['porcentaje.required']],
            'con texto' => [['porcentaje' => 'diez'], 'diez', $mensajes['porcentaje.numeric']],
            'con tres decimales' => [['porcentaje' => '12.345'], '12.345', $mensajes['porcentaje.decimal']],
            'en una lista' => [['porcentaje' => ['10']], ['10'], $mensajes['porcentaje.numeric']],
        ];

        foreach ($casos as $caso => [$cuerpo, $valor, $mensaje]) {
            $errores = $this->erroresDeLasComisiones($valor);
            $this->assertContains($mensaje, $errores['porcentaje'], $caso);

            foreach ([self::GLOBAL, self::COMISIONES.'/lavanderias/'.$this->primera->id_publico] as $url) {
                $respuesta = $this->llamarConToken($this->token, 'PUT', $url, $cuerpo)->assertUnprocessable();
                $this->assertSame($errores, $respuesta->json('errors'), "{$caso} · {$url}");
            }
        }

        $this->assertSame(0, Comision::count());

        $this->llamarConToken($this->token, 'PUT', self::GLOBAL, ['porcentaje' => '0'])->assertOk()->assertJsonPath('global.porcentaje', '0.00');
        $this->travelTo(CarbonImmutable::parse('2026-12-02 12:00:00', 'UTC'));
        $this->llamarConToken($this->token, 'PUT', self::COMISIONES.'/lavanderias/'.$this->primera->id_publico, ['porcentaje' => 100])
            ->assertOk()
            ->assertJsonPath('lavanderias.0.aplica', ['porcentaje' => '100.00', 'origen' => 'propio']);
    }

    #[TestDox('administracion-app-E12: una lavandería que no existe, una borrada o un valor que no es ULID responden 404 con solo su mensaje al fijar y al quitar el porcentaje propio, antes de validar, y nada cambia')]
    public function test_administracion_app_e12_una_lavanderia_que_no_existe_responde_404(): void
    {
        $this->porcentajes()->fijarGlobal('10');
        $borrada = Lavanderia::factory()->en($this->plazaDos)->create();
        $borrada->delete();
        $antes = $this->fotoDeLaBase();

        foreach ([(string) Str::ulid(), $borrada->id_publico, 'no-es-un-ulid'] as $lavanderia) {
            $url = self::COMISIONES.'/lavanderias/'.$lavanderia;

            foreach ([['PUT', ['porcentaje' => '5']], ['PUT', ['porcentaje' => '500']], ['DELETE', []]] as [$metodo, $cuerpo]) {
                $this->llamarConToken($this->token, $metodo, $url, $cuerpo)
                    ->assertNotFound()
                    ->assertExactJson(['message' => 'No existe la lavandería.']);
            }
        }

        $this->assertSame($antes, $this->fotoDeLaBase());
    }

    #[TestDox('administracion-app-E06 y administracion-app-E07: cada ruta de las comisiones la abre solo la administración con su token: las cuentas de usuario, de lavandería y de repartidor y un token sin la habilidad reciben 403 sin datos, sin token o con el token vencido 401, y ningún porcentaje cambia')]
    public function test_administracion_app_e06_y_e07_cada_ruta_de_las_comisiones_solo_la_abre_la_administracion(): void
    {
        $deLaPrimera = self::COMISIONES.'/lavanderias/'.$this->primera->id_publico;
        $rutas = [
            ['GET', self::COMISIONES, []],
            ['PUT', self::GLOBAL, ['porcentaje' => '10']],
            ['PUT', $deLaPrimera, ['porcentaje' => '10']],
            ['DELETE', $deLaPrimera, []],
        ];

        $this->porcentajes()->fijarPropio($this->primera, '8');

        foreach ($rutas as [$metodo, $url, $cuerpo]) {
            $this->assertSoloEntraLaAdministracion($metodo, $url, $cuerpo);
        }

        $this->assertNull($this->porcentajes()->globalVigente());
        $this->assertSame('8.00', $this->porcentajes()->propioVigente($this->primera)?->porcentaje);

        // La administración, con su token, sí: el rechazo no es de toda llamada.
        foreach ($rutas as [$metodo, $url, $cuerpo]) {
            $this->llamarConToken($this->token, $metodo, $url, $cuerpo)->assertOk();
        }

        $this->assertSame('10.00', $this->porcentajes()->globalVigente()?->porcentaje);
        $this->assertNull($this->porcentajes()->propioVigente($this->primera));
    }

    #[TestDox('administracion-app-E32: el contrato de las comisiones con la app: el global con desde cuándo rige y cada lavandería con su propio y el que le aplica; sin ningún porcentaje, el global en null y sin número')]
    public function test_administracion_app_e32_el_contrato_de_las_comisiones(): void
    {
        $this->assertCumpleSuContrato('comisiones.sin-global', 'GET /api/administracion/comisiones', $this->llamarConToken($this->token, 'GET', self::COMISIONES));

        $this->porcentajes()->fijarGlobal('10');
        $this->porcentajes()->fijarPropio($this->segunda, '7.25');

        $this->assertCumpleSuContrato('comisiones', 'GET /api/administracion/comisiones', $this->llamarConToken($this->token, 'GET', self::COMISIONES));
    }

    #[TestDox('administracion-app-E33 y administracion-app-E34: el contrato del porcentaje global con la app: las comisiones con el global nuevo, y el 422 en porcentaje del que no vale')]
    public function test_administracion_app_e33_el_contrato_del_global(): void
    {
        $peticion = 'PUT /api/administracion/comisiones/global';

        $this->assertCumpleSuContrato('comisiones.global', $peticion, $this->llamarConToken($this->token, 'PUT', self::GLOBAL, ['porcentaje' => '12.5']));
        $this->assertCumpleSuContrato(
            'comisiones.global-invalido',
            $peticion,
            $this->llamarConToken($this->token, 'PUT', self::GLOBAL, ['porcentaje' => '120']),
            422,
        );
    }

    #[TestDox('administracion-app-E33 y administracion-app-E12: el contrato del porcentaje propio con la app: las comisiones con el propio nuevo, sin él al quitarlo, y el 404 de la lavandería que no existe')]
    public function test_administracion_app_e33_el_contrato_del_propio(): void
    {
        $this->porcentajes()->fijarGlobal('10');
        $url = self::COMISIONES."/lavanderias/{$this->primera->id_publico}";

        $this->assertCumpleSuContrato(
            'comisiones.propio',
            'PUT /api/administracion/comisiones/lavanderias/{lavanderia}',
            $this->llamarConToken($this->token, 'PUT', $url, ['porcentaje' => '7.25']),
        );
        $this->assertCumpleSuContrato(
            'comisiones.quitar-propio',
            'DELETE /api/administracion/comisiones/lavanderias/{lavanderia}',
            $this->llamarConToken($this->token, 'DELETE', $url),
        );
        $this->assertCumpleSuContrato(
            'comisiones.no-existe',
            'PUT /api/administracion/comisiones/lavanderias/{lavanderia}',
            $this->llamarConToken($this->token, 'PUT', self::COMISIONES.'/lavanderias/'.Str::ulid(), ['porcentaje' => '7.25']),
            404,
        );
    }

    /**
     * La fila de una lavandería en la respuesta.
     *
     * @param  array{porcentaje: string, origen: string}|null  $aplica
     * @return array<string, mixed>
     */
    private function fila(Lavanderia $lavanderia, Plaza $plaza, ?string $propio, ?array $aplica): array
    {
        return [
            'id_publico' => $lavanderia->id_publico,
            'nombre' => $lavanderia->nombre,
            'plaza' => ['nombre' => $plaza->nombre],
            'propio' => $propio,
            'aplica' => $aplica,
        ];
    }

    /**
     * Los errores que da la clase de dominio de las comisiones al fijar ese porcentaje, por campo.
     *
     * @return array<string, list<string>>
     */
    private function erroresDeLasComisiones(mixed $porcentaje): array
    {
        try {
            $this->porcentajes()->fijarGlobal($porcentaje);
        } catch (ValidationException $error) {
            return $error->errors();
        }

        $this->fail('Las comisiones guardaron un porcentaje que no vale: '.var_export($porcentaje, true));
    }

    private function porcentajes(): PorcentajesDeComision
    {
        return app(PorcentajesDeComision::class);
    }
}
