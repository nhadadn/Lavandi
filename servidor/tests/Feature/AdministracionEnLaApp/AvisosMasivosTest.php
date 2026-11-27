<?php

namespace Tests\Feature\AdministracionEnLaApp;

use App\Autorizacion\Autorizacion;
use App\Avisos\CreacionDeAvisoMasivo;
use App\Avisos\MandarAvisoMasivo;
use App\Avisos\MandarAvisoMasivoAlTelefono;
use App\Filament\Resources\AvisosMasivos\Pages\CreateAvisoMasivo;
use App\Models\AvisoMasivo;
use App\Models\Dispositivo;
use App\Models\Repartidor;
use App\Models\Rol;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Validator;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\AdministracionEnLaApp\Soporte\ContratosDeLaApp;
use Tests\Feature\AdministracionEnLaApp\Soporte\SesionesDeLaApp;
use Tests\Feature\AltaLavanderias\Soporte\PanelDePrueba;
use Tests\Feature\Avisos\Soporte\FcmSimulado;
use Tests\TestCase;

/**
 * Los avisos masivos en la app: la administración escribe un aviso con título y texto, lo manda a todos los teléfonos
 * registrados y ve los enviados, con la regla del panel (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #7;
 * administracion-E23; ADR-0008; ADR-0022, decisiones 9 y 16).
 *
 * - POST /api/administracion/avisos-masivos crea el aviso con la misma clase que el panel, App\Avisos\
 *   CreacionDeAvisoMasivo: qué se guarda, que sale en cola después de guardarlo (App\Avisos\MandarAvisoMasivo) y las
 *   reglas del título y del texto, con sus mensajes. La API no valida por su cuenta: el 422 es el de esa clase.
 * - GET /api/administracion/avisos-masivos lista los de Autorizacion::avisosMasivosDelPanel(), del más reciente al más
 *   antiguo, con a cuántos teléfonos salió cada uno, o nulo mientras sigue en la cola.
 * - Quién lo mandó dice si fue la cuenta de la sesión. El correo de otra cuenta de administración no sale en ninguna
 *   respuesta (delta de autorizacion-E14).
 *
 * Las sesiones son reales: el token sale de App\Identidad\TokensDeLaApp y va con withToken() (Soporte\SesionesDeLaApp).
 * FCM está simulado (Tests\Feature\Avisos\Soporte\FcmSimulado): ninguna prueba sale a la red. El aviso a un segmento
 * espera DD-113 (administracion-app-E44). La prueba del panel, tests/Feature/Administracion/AvisosMasivosTest.php,
 * sigue sin cambios.
 *
 * Escenarios: administracion-app-E42, administracion-app-E43, administracion-app-E11, administracion-app-E06 y
 * administracion-app-E07 · Spec: openspec/changes/administracion-en-la-app/specs/administracion-en-la-app/spec.md ·
 * Tareas 15.1 y 15.2
 */
class AvisosMasivosTest extends TestCase
{
    use ContratosDeLaApp;
    use FcmSimulado;
    use PanelDePrueba;
    use RefreshDatabase;
    use SesionesDeLaApp;

    private const RUTA = '/api/administracion/avisos-masivos';

    private const TITULO = 'Mantenimiento de prueba';

    private const MENSAJE = 'La app no estará disponible el domingo de 2 a 4 de la mañana. Gracias, eñe.';

    #[TestDox('administracion-app-E42: con tres cuentas con un teléfono registrado y una sin teléfono, el aviso que manda la administración desde la app se guarda como global y sale por FCM a los tres teléfonos y a ninguno más')]
    public function test_administracion_app_e42_el_aviso_global_sale_a_los_tres_telefonos_y_a_ninguno_mas(): void
    {
        $this->simularFcm();
        $telefonos = $this->tresCuentasConTelefono();
        Usuario::factory()->conRol(Rol::USUARIO)->create();
        $administracion = $this->cuentaDeAdministracion();

        $this->mandar($administracion)
            ->assertCreated()
            ->assertJsonPath('aviso.titulo', self::TITULO)
            ->assertJsonPath('aviso.mensaje', self::MENSAJE)
            ->assertJsonPath('aviso.enviado_por_esta_cuenta', true);

        $mensajes = $this->mensajesAFcm();
        $this->assertCount(3, $mensajes, 'Un envío por teléfono, ninguno por la cuenta sin teléfono.');
        $this->assertEqualsCanonicalizing(
            array_map(fn (Dispositivo $telefono): string => $telefono->token_fcm, $telefonos),
            array_column($mensajes, 'token'),
        );

        foreach ($mensajes as $mensaje) {
            $this->assertSame(['title' => self::TITULO, 'body' => self::MENSAJE], $mensaje['android']['notification']);
            $this->assertSame(['title' => self::TITULO, 'body' => self::MENSAJE], $mensaje['apns']['payload']['aps']['alert']);
        }

        $aviso = AvisoMasivo::sole();
        $this->assertSame(
            [self::TITULO, self::MENSAJE, $administracion->getKey(), null, null, 3],
            [$aviso->titulo, $aviso->mensaje, $aviso->id_usuarios, $aviso->id_plazas, $aviso->segmento, $aviso->destinatarios],
        );
        $this->assertNotNull($aviso->enviado_en);
    }

    #[TestDox('administracion-app-E42: el aviso sale en cola después de guardarlo: la respuesta no espera a FCM y dice que sigue en la cola, y el trabajo pone en cola un envío por teléfono y guarda cuántos son')]
    public function test_administracion_app_e42_el_aviso_sale_en_cola_despues_de_guardarlo(): void
    {
        $this->simularFcm();
        Queue::fake();
        $telefonos = $this->tresCuentasConTelefono();
        $administracion = $this->cuentaDeAdministracion();

        $this->mandar($administracion)->assertCreated()->assertJsonPath('aviso.destinatarios', null);

        Http::assertNothingSent();
        $aviso = AvisoMasivo::sole();
        $this->assertNull($aviso->destinatarios, 'Se cuenta al salir, no al guardar.');
        Queue::assertPushed(MandarAvisoMasivo::class, 1);
        Queue::assertPushed(MandarAvisoMasivo::class, fn (MandarAvisoMasivo $trabajo): bool => $trabajo->aviso->is($aviso));

        app()->call([new MandarAvisoMasivo($aviso), 'handle']);

        Queue::assertPushed(MandarAvisoMasivoAlTelefono::class, 3);
        $this->assertEqualsCanonicalizing(
            array_map(fn (Dispositivo $telefono): int => $telefono->getKey(), $telefonos),
            Queue::pushed(MandarAvisoMasivoAlTelefono::class)->map(fn (MandarAvisoMasivoAlTelefono $envio): int => $envio->telefono)->all(),
        );
        $this->assertSame(3, $aviso->refresh()->destinatarios);
    }

    #[TestDox('administracion-app-E42: sin título o sin texto, con solo espacios, con un título de más de 255 caracteres o un texto de más de 1000, o sin texto en el campo, responde 422 con el error de cada campo y no guarda ni manda nada; sin título, el error de titulo dice "Escribe el título."')]
    public function test_administracion_app_e42_un_aviso_incompleto_o_que_pasa_sus_topes_responde_422_y_no_sale(): void
    {
        $this->simularFcm();
        Queue::fake();
        $this->tresCuentasConTelefono();
        $administracion = $this->cuentaDeAdministracion();
        $mensajes = CreacionDeAvisoMasivo::mensajes();

        $casos = [
            'sin los dos campos' => [[], [
                'titulo' => [$mensajes['titulo.required']],
                'mensaje' => [$mensajes['mensaje.required']],
            ]],
            'vacíos o con solo espacios' => [['titulo' => '', 'mensaje' => '   '], [
                'titulo' => [$mensajes['titulo.required']],
                'mensaje' => [$mensajes['mensaje.required']],
            ]],
            // El texto lo fija la spec (imagen 38 de la Propuesta 6), con el estilo de CambiarPerfilRequest.
            'sin título' => [['titulo' => null, 'mensaje' => self::MENSAJE], ['titulo' => ['Escribe el título.']]],
            'sin texto' => [['titulo' => self::TITULO], ['mensaje' => [$mensajes['mensaje.required']]]],
            'un título de 256 caracteres' => [
                ['titulo' => str_repeat('ñ', CreacionDeAvisoMasivo::TOPE_DEL_TITULO + 1), 'mensaje' => self::MENSAJE],
                ['titulo' => [$mensajes['titulo.max']]],
            ],
            'un texto de 1001 caracteres' => [
                ['titulo' => self::TITULO, 'mensaje' => str_repeat('ñ', CreacionDeAvisoMasivo::TOPE_DEL_MENSAJE + 1)],
                ['mensaje' => [$mensajes['mensaje.max']]],
            ],
            'sin texto en los campos' => [['titulo' => ['un', 'título'], 'mensaje' => 12345], [
                'titulo' => [$mensajes['titulo.string']],
                'mensaje' => [$mensajes['mensaje.string']],
            ]],
        ];

        foreach ($casos as $caso => [$cuerpo, $errores]) {
            $respuesta = $this->llamarConToken($this->tokenDeLaApp($administracion), 'POST', self::RUTA, $cuerpo);

            $this->assertSame(422, $respuesta->status(), "{$caso}: {$respuesta->getContent()}");
            $this->assertSame($errores, $respuesta->json('errors'), $caso);
        }

        $this->assertSame(0, AvisoMasivo::count());
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    #[TestDox('administracion-app-E42: un título de 255 caracteres y un texto de 1000, contados como caracteres y no como bytes, sí salen')]
    public function test_administracion_app_e42_un_aviso_justo_en_sus_topes_si_sale(): void
    {
        Queue::fake();
        $administracion = $this->cuentaDeAdministracion();
        $titulo = str_repeat('ñ', CreacionDeAvisoMasivo::TOPE_DEL_TITULO);
        $mensaje = str_repeat('é', CreacionDeAvisoMasivo::TOPE_DEL_MENSAJE);

        $this->mandar($administracion, ['titulo' => $titulo, 'mensaje' => $mensaje])->assertCreated();

        $this->assertSame([$titulo, $mensaje], [AvisoMasivo::sole()->titulo, AvisoMasivo::sole()->mensaje]);
        Queue::assertPushed(MandarAvisoMasivo::class, 1);
    }

    #[TestDox('administracion-app-E11: el panel y la API crean el aviso con la misma clase: guardan lo mismo para los mismos datos, los dos lo ponen en cola, y rechazan lo mismo, con las reglas y los mensajes que da la clase y ninguna lista propia del panel')]
    public function test_administracion_app_e11_el_panel_y_la_api_crean_el_aviso_con_la_misma_clase(): void
    {
        Queue::fake();
        $administracion = $this->comoAdministracion();
        $demasiadoLargo = ['titulo' => str_repeat('a', CreacionDeAvisoMasivo::TOPE_DEL_TITULO + 1), 'mensaje' => ''];
        $deLaClase = Validator::make($demasiadoLargo, CreacionDeAvisoMasivo::reglas(), CreacionDeAvisoMasivo::mensajes())
            ->errors()
            ->toArray();

        // El panel, con la sesión web de la cuenta: rechaza con los mensajes de la clase, bajo data.<campo>, y luego manda.
        $panel = Livewire::test(CreateAvisoMasivo::class)
            ->fillForm($demasiadoLargo)
            ->call('create')
            ->assertHasFormErrors(['titulo' => 'max', 'mensaje' => 'required']);
        $this->assertSame($deLaClase['titulo'], $panel->errors()->get('data.titulo'));
        $this->assertSame($deLaClase['mensaje'], $panel->errors()->get('data.mensaje'));
        $this->assertSame(0, AvisoMasivo::count());

        // Los campos del panel validan con las reglas de la clase, ni una más ni una menos: no tienen una lista suya.
        $campos = $panel->instance()->form->getFlatFields();
        foreach (CreacionDeAvisoMasivo::reglas() as $campo => $reglas) {
            $this->assertEqualsCanonicalizing($reglas, $campos[$campo]->getValidationRules(), "Las reglas de {$campo}");
        }

        Livewire::test(CreateAvisoMasivo::class)
            ->fillForm(['titulo' => self::TITULO, 'mensaje' => self::MENSAJE])
            ->call('create')
            ->assertHasNoFormErrors();

        // La API, con el token de la misma cuenta: el mismo rechazo y el mismo aviso.
        $token = $this->tokenDeLaApp($administracion);
        $this->llamarConToken($token, 'POST', self::RUTA, $demasiadoLargo)
            ->assertUnprocessable()
            ->assertJsonPath('errors', $deLaClase);
        $this->assertSame(1, AvisoMasivo::count());
        $this->llamarConToken($token, 'POST', self::RUTA, ['titulo' => self::TITULO, 'mensaje' => self::MENSAJE])
            ->assertCreated();

        [$delPanel, $deLaApi] = AvisoMasivo::query()->orderBy('id_avisos_masivos')->get()->all();
        $loGuardado = fn (AvisoMasivo $aviso): array => $aviso->only(
            ['titulo', 'mensaje', 'id_plazas', 'segmento', 'id_usuarios', 'destinatarios'],
        );
        $this->assertSame($loGuardado($delPanel), $loGuardado($deLaApi));
        $this->assertSame($administracion->getKey(), $deLaApi->id_usuarios);
        Queue::assertPushed(MandarAvisoMasivo::class, 2);
        Queue::assertPushed(MandarAvisoMasivo::class, fn (MandarAvisoMasivo $trabajo): bool => $trabajo->aviso->is($delPanel));
        Queue::assertPushed(MandarAvisoMasivo::class, fn (MandarAvisoMasivo $trabajo): bool => $trabajo->aviso->is($deLaApi));
    }

    #[TestDox('administracion-app-E11: con espacios en los extremos, el panel y la API guardan lo mismo, sin esos espacios, y cuentan los topes igual: un título de 255 caracteres con un espacio al final y un texto de 1000 con uno al principio salen por los dos; la clase, llamada sola, recorta igual')]
    public function test_administracion_app_e11_el_panel_y_la_api_recortan_igual_los_espacios_de_los_extremos(): void
    {
        Queue::fake();
        $administracion = $this->comoAdministracion();
        $token = $this->tokenDeLaApp($administracion);
        $enSusTopes = [
            str_repeat('ñ', CreacionDeAvisoMasivo::TOPE_DEL_TITULO),
            str_repeat('é', CreacionDeAvisoMasivo::TOPE_DEL_MENSAJE),
        ];
        $casos = [
            'con espacios en los extremos' => [
                ['titulo' => '  '.self::TITULO.'  ', 'mensaje' => "\n ".self::MENSAJE."\t "],
                [self::TITULO, self::MENSAJE],
            ],
            'justo en sus topes, más un espacio' => [
                ['titulo' => $enSusTopes[0].' ', 'mensaje' => ' '.$enSusTopes[1]],
                $enSusTopes,
            ],
        ];

        foreach ($casos as $caso => [$datos, $esperado]) {
            // La llamada con token olvida la sesión web: el panel vuelve a entrar con la misma cuenta.
            $this->actingAs($administracion, 'web');
            Livewire::test(CreateAvisoMasivo::class)
                ->fillForm($datos)
                ->call('create')
                ->assertHasNoFormErrors();
            $this->llamarConToken($token, 'POST', self::RUTA, $datos)->assertCreated();

            [$delPanel, $deLaApi] = AvisoMasivo::query()->latest('id_avisos_masivos')->take(2)->get()->reverse()->values()->all();
            $this->assertSame($esperado, [$delPanel->titulo, $delPanel->mensaje], "{$caso}: el panel");
            $this->assertSame($esperado, [$deLaApi->titulo, $deLaApi->mensaje], "{$caso}: la API");
        }

        $sola = app(CreacionDeAvisoMasivo::class)->crear($administracion, ' '.self::TITULO.' ', ' '.self::MENSAJE.' ');
        $this->assertSame([self::TITULO, self::MENSAJE], [$sola->titulo, $sola->mensaje]);
        Queue::assertPushed(MandarAvisoMasivo::class, 5);
    }

    #[TestDox('administracion-app-E43: con un aviso ya contado y otro todavía en la cola, la administración los ve del más reciente al más antiguo, con su título, su texto, cuándo y quién lo mandó, y a cuántos teléfonos salió, o nulo el que sigue en la cola; son los avisos del componente que usa el panel')]
    public function test_administracion_app_e43_los_avisos_enviados_del_mas_reciente_al_mas_antiguo(): void
    {
        $this->simularFcm();
        Queue::fake();
        $this->tresCuentasConTelefono();
        $administracion = $this->cuentaDeAdministracion();
        $creacion = app(CreacionDeAvisoMasivo::class);

        $this->travelTo(CarbonImmutable::parse('2027-03-14 18:30:00', 'UTC'));
        $contado = $creacion->crear($administracion, 'Promoción de marzo', 'Lavado con 10% menos esta semana.');
        app()->call([new MandarAvisoMasivo($contado), 'handle']);
        $this->travelTo(CarbonImmutable::parse('2027-03-15 09:05:00', 'UTC'));
        $enCola = $creacion->crear($administracion, 'Mantenimiento', 'La app no estará disponible el domingo.');
        $this->assertSame([3, null], [$contado->refresh()->destinatarios, $enCola->refresh()->destinatarios]);

        $avisos = $this->llamarConToken($this->tokenDeLaApp($administracion), 'GET', self::RUTA)->assertOk()->json('avisos');

        $this->assertSame([
            [
                'titulo' => 'Mantenimiento',
                'mensaje' => 'La app no estará disponible el domingo.',
                'enviado_en' => '2027-03-15T09:05:00Z',
                'enviado_por_esta_cuenta' => true,
                'destinatarios' => null,
            ],
            [
                'titulo' => 'Promoción de marzo',
                'mensaje' => 'Lavado con 10% menos esta semana.',
                'enviado_en' => '2027-03-14T18:30:00Z',
                'enviado_por_esta_cuenta' => true,
                'destinatarios' => 3,
            ],
        ], $avisos);
        $this->assertEqualsCanonicalizing(
            app(Autorizacion::class)->avisosMasivosDelPanel($administracion)->pluck('titulo')->all(),
            array_column($avisos, 'titulo'),
        );
    }

    #[TestDox('administracion-app-E43 y autorizacion-E14: la lista trae también el aviso que mandó otra cuenta de administración, como el panel, pero dice que no fue la cuenta de la sesión y no trae el correo de esa otra cuenta')]
    public function test_administracion_app_e43_el_aviso_de_otra_cuenta_de_administracion_sale_sin_su_correo(): void
    {
        Queue::fake();
        $administracion = $this->cuentaDeAdministracion(['correo' => 'duenio@ejemplo.com']);
        $otra = $this->cuentaDeAdministracion(['correo' => 'otra.administracion@ejemplo.com']);
        $creacion = app(CreacionDeAvisoMasivo::class);

        $this->travelTo(CarbonImmutable::parse('2027-03-14 18:30:00', 'UTC'));
        $creacion->crear($otra, 'De la otra cuenta', 'Aviso de la otra cuenta de administración.');
        $this->travelTo(CarbonImmutable::parse('2027-03-14 19:00:00', 'UTC'));
        $creacion->crear($administracion, 'De esta cuenta', 'Aviso de la cuenta de la sesión.');

        $respuesta = $this->llamarConToken($this->tokenDeLaApp($administracion), 'GET', self::RUTA)->assertOk();

        $this->assertSame(
            [['De esta cuenta', true], ['De la otra cuenta', false]],
            array_map(fn (array $aviso): array => [$aviso['titulo'], $aviso['enviado_por_esta_cuenta']], $respuesta->json('avisos')),
        );
        $this->assertStringNotContainsString($otra->correo, $respuesta->getContent());
        $this->assertStringNotContainsString($administracion->correo, $respuesta->getContent());
    }

    #[TestDox('administracion-app-E06 y administracion-app-E07: ver y mandar avisos masivos solo lo abre la administración con su token: las cuentas de usuario, lavandería y repartidor, y un token de administración sin la habilidad, reciben 403 sin datos; sin sesión o con el token vencido, 401; y no se guarda ni sale ningún aviso')]
    public function test_administracion_app_e06_y_e07_solo_la_administracion_ve_y_manda_avisos_masivos(): void
    {
        $this->simularFcm();
        Queue::fake();
        $this->tresCuentasConTelefono();
        // Un aviso que la lista de un rol que no corresponde podría traer. La cola se vuelve a simular vacía: lo que
        // cuenta es lo que pongan las llamadas rechazadas.
        app(CreacionDeAvisoMasivo::class)->crear($this->cuentaDeAdministracion(), self::TITULO, self::MENSAJE);
        Queue::fake();

        $this->assertSoloEntraLaAdministracion('GET', self::RUTA);
        $this->assertSoloEntraLaAdministracion('POST', self::RUTA, ['titulo' => 'Otro aviso', 'mensaje' => 'Que no debe salir.']);

        $this->assertSame([self::TITULO], AvisoMasivo::query()->pluck('titulo')->all());
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    #[TestDox('administracion-app-E43: el contrato de los avisos enviados con la app: uno ya contado, otro en la cola y uno de otra cuenta de administración, sin su correo; sin avisos, la lista vacía')]
    public function test_administracion_app_e43_el_contrato_de_los_avisos_enviados(): void
    {
        $this->simularFcm();
        Queue::fake();
        $this->tresCuentasConTelefono();
        $administracion = $this->cuentaDeAdministracion();
        $token = $this->tokenDeLaApp($administracion);
        $creacion = app(CreacionDeAvisoMasivo::class);

        $this->assertCumpleSuContrato('avisos-masivos.lista-vacia', 'GET /api/administracion/avisos-masivos', $this->llamarConToken($token, 'GET', self::RUTA));

        $contado = $creacion->crear($this->cuentaDeAdministracion(), 'Aviso de prueba de otra cuenta', 'Texto de prueba de otra cuenta.');
        app()->call([new MandarAvisoMasivo($contado), 'handle']);
        $creacion->crear($administracion, self::TITULO, self::MENSAJE);

        $this->assertCumpleSuContrato('avisos-masivos.lista', 'GET /api/administracion/avisos-masivos', $this->llamarConToken($token, 'GET', self::RUTA));
    }

    #[TestDox('administracion-app-E42: el contrato del aviso nuevo con la app: el 201 con el aviso en la cola, y el 422 con el error de cada campo')]
    public function test_administracion_app_e42_el_contrato_del_aviso_nuevo(): void
    {
        Queue::fake();
        $administracion = $this->cuentaDeAdministracion();

        $this->assertCumpleSuContrato('avisos-masivos.nuevo', 'POST /api/administracion/avisos-masivos', $this->mandar($administracion), 201);
        $this->assertCumpleSuContrato(
            'avisos-masivos.nuevo-invalido',
            'POST /api/administracion/avisos-masivos',
            $this->mandar($administracion, ['titulo' => '', 'mensaje' => str_repeat('a', 1001)]),
            422,
        );
    }

    /**
     * Tres cuentas de la app con un teléfono registrado cada una: un usuario, un socio lavandería y un socio repartidor.
     *
     * @return list<Dispositivo>
     */
    private function tresCuentasConTelefono(): array
    {
        return [
            $this->telefonoDe(Usuario::factory()->conRol(Rol::USUARIO)->create()),
            $this->telefonoDe(Usuario::factory()->conRol(Rol::LAVANDERIA)->create(), Dispositivo::IOS),
            $this->telefonoDe(Repartidor::factory()->create()->cuenta),
        ];
    }

    /**
     * La administración manda el aviso desde la app, con su token.
     *
     * @param  array<string, mixed>  $cuerpo
     */
    private function mandar(Usuario $administracion, array $cuerpo = ['titulo' => self::TITULO, 'mensaje' => self::MENSAJE]): TestResponse
    {
        return $this->llamarConToken($this->tokenDeLaApp($administracion), 'POST', self::RUTA, $cuerpo);
    }
}
