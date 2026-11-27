<?php

namespace Tests\Feature\Avisos;

use App\Avisos\MandarAvisoAlTelefono;
use App\Models\Dispositivo;
use App\Models\EstadoPedido;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\Avisos\Soporte\CambiosDeEstadoDePrueba;
use Tests\Feature\Avisos\Soporte\FcmSimulado;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Estados\Soporte\EstadosDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * Los teléfonos registrados: la app manda a la API el token de Firebase del teléfono al iniciar sesión y cuando Firebase
 * lo renueva, y la API lo liga a la cuenta, lo pasa a la cuenta que inicia sesión en ese teléfono y lo borra al cerrar
 * la sesión o cuando FCM dice que ya no sirve (ADR-0008; ADR-0016, tabla dispositivos;
 * NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3, Separación por tipo de usuario).
 *
 * El contrato de las rutas para la app está en App\Http\Controllers\Api\DispositivosController y
 * App\Http\Controllers\Api\SesionController, y en openspec/changes/avisos/design.md. FCM está simulado (FcmSimulado).
 *
 * Escenarios: avisos-E04, avisos-E05, avisos-E06 y avisos-E21 · Spec: openspec/changes/avisos/specs/avisos/spec.md ·
 * Tarea 2.1
 */
class TelefonosRegistradosTest extends TestCase
{
    use AgendaDePrueba;
    use CambiosDeEstadoDePrueba;
    use EstadosDePrueba;
    use FcmSimulado;
    use LavanderiasDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    private Usuario $cuenta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();
        $this->simularFcm();
        $this->cuenta = $this->cuentaDeUsuario();
    }

    #[TestDox('avisos-E04: con la sesión iniciada, la app registra el token de su teléfono y la api lo guarda ligado a esa cuenta, con su plataforma')]
    public function test_avisos_e04_la_api_guarda_el_token_ligado_a_la_cuenta(): void
    {
        $this->registrarTelefono($this->cuenta, 'token-del-telefono', Dispositivo::IOS)
            ->assertNoContent();

        $telefono = Dispositivo::query()->sole();
        $this->assertSame($this->cuenta->getKey(), $telefono->id_usuarios);
        $this->assertSame('token-del-telefono', $telefono->token_fcm);
        $this->assertSame(Dispositivo::IOS, $telefono->plataforma);
        $this->assertTrue(now()->equalTo($telefono->ultimo_uso_en));
    }

    #[TestDox('avisos-E04: los avisos de los pedidos de esa cuenta van al token registrado')]
    public function test_avisos_e04_los_avisos_de_la_cuenta_van_al_token_registrado(): void
    {
        $pedido = $this->pedidoAceptadoDe($this->cuenta);
        $this->registrarTelefono($this->cuenta, 'token-del-telefono')->assertNoContent();

        $this->cambiarComoLaLavanderia($pedido, EstadoPedido::RECOLECTADO);

        $this->assertCount(1, $this->mensajesAlToken('token-del-telefono'));
    }

    #[TestDox('avisos-E04: registrar otra vez el mismo token, como al renovarlo o al volver a entrar, deja una sola fila con su último uso al día')]
    public function test_avisos_e04_registrar_otra_vez_el_mismo_token_deja_una_sola_fila(): void
    {
        $this->registrarTelefono($this->cuenta, 'token-del-telefono')->assertNoContent();
        $this->travel(2)->days();

        $this->registrarTelefono($this->cuenta, 'token-del-telefono')->assertNoContent();

        $telefono = Dispositivo::query()->sole();
        $this->assertTrue(now()->equalTo($telefono->ultimo_uso_en));
    }

    #[TestDox('avisos-E04: una cuenta puede tener varios teléfonos y el token nuevo que da Firebase al renovarse se registra aparte')]
    public function test_avisos_e04_una_cuenta_puede_tener_varios_telefonos(): void
    {
        $this->registrarTelefono($this->cuenta, 'token-del-android', Dispositivo::ANDROID)->assertNoContent();
        $this->registrarTelefono($this->cuenta, 'token-del-iphone', Dispositivo::IOS)->assertNoContent();

        $this->assertEqualsCanonicalizing(
            ['token-del-android', 'token-del-iphone'],
            Dispositivo::query()->where('id_usuarios', $this->cuenta->getKey())->pluck('token_fcm')->all(),
        );
    }

    #[TestDox('avisos-E04: sin token, con una plataforma que no es ios ni android o con un token demasiado largo, la api responde el error de cada campo y no guarda nada')]
    public function test_avisos_e04_la_api_valida_el_token_y_la_plataforma(): void
    {
        $this->registrar($this->cuenta, ['plataforma' => 'android'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token_fcm']);
        $this->registrar($this->cuenta, ['token_fcm' => 'token-del-telefono', 'plataforma' => 'web'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['plataforma']);
        $this->registrar($this->cuenta, ['token_fcm' => 'token-del-telefono'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['plataforma']);
        $this->registrar($this->cuenta, ['token_fcm' => ['no', 'es', 'texto'], 'plataforma' => 'ios'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token_fcm']);
        $this->registrar($this->cuenta, ['token_fcm' => str_repeat('a', 4097), 'plataforma' => 'ios'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token_fcm']);

        $this->assertSame(0, Dispositivo::query()->count());
    }

    #[TestDox('avisos-E04: sin sesión, la api no registra el teléfono')]
    public function test_avisos_e04_sin_sesion_no_se_registra_el_telefono(): void
    {
        $this->postJson('/api/dispositivos', ['token_fcm' => 'token-del-telefono', 'plataforma' => 'android'])
            ->assertUnauthorized();

        $this->assertSame(0, Dispositivo::query()->count());
    }

    #[TestDox('avisos-E04: una cuenta sin el rol de usuario no registra teléfono por esta ruta')]
    public function test_avisos_e04_una_cuenta_sin_el_rol_de_usuario_no_registra_telefono(): void
    {
        $lavanderia = Usuario::factory()->conRol(Rol::LAVANDERIA)->create();

        $this->registrarTelefono($lavanderia, 'token-del-telefono')->assertForbidden();

        $this->assertSame(0, Dispositivo::query()->count());
    }

    #[TestDox('avisos-E21: el teléfono de la cuenta A en que inicia sesión la cuenta B queda ligado solo a B, y los avisos de A ya no le llegan')]
    public function test_avisos_e21_el_telefono_que_pasa_a_otra_cuenta_queda_ligado_solo_a_ella(): void
    {
        $cuentaB = $this->cuentaDeUsuario();
        $pedidoA = $this->pedidoAceptadoDe($this->cuenta);
        $pedidoB = $this->pedidoAceptadoDe($cuentaB);
        $this->registrarTelefono($this->cuenta, 'token-compartido')->assertNoContent();

        $this->registrarTelefono($cuentaB, 'token-compartido', Dispositivo::IOS)->assertNoContent();

        $telefono = Dispositivo::query()->sole();
        $this->assertSame($cuentaB->getKey(), $telefono->id_usuarios);
        $this->assertSame(Dispositivo::IOS, $telefono->plataforma);

        $this->cambiarComoLaLavanderia($pedidoA, EstadoPedido::RECOLECTADO);
        $this->assertSame([], $this->mensajesAlToken('token-compartido'));

        $this->cambiarComoLaLavanderia($pedidoB, EstadoPedido::RECOLECTADO);
        $this->assertSame([$pedidoB->id_publico], array_column(array_column($this->mensajesAlToken('token-compartido'), 'data'), 'pedido'));
    }

    #[TestDox('avisos-E21: un aviso de A que esperaba en la cola cuando el teléfono pasó a B no le llega a B')]
    public function test_avisos_e21_un_aviso_que_esperaba_en_la_cola_no_llega_a_la_otra_cuenta(): void
    {
        $pedidoA = $this->pedidoConfirmadoDe($this->cuenta);
        $this->registrarTelefono($this->cuenta, 'token-compartido')->assertNoContent();
        $envio = new MandarAvisoAlTelefono($pedidoA, EstadoPedido::RECOLECTADO, Dispositivo::query()->sole()->getKey());

        $this->registrarTelefono($this->cuentaDeUsuario(), 'token-compartido')->assertNoContent();
        app()->call([$envio, 'handle']);

        $this->assertSame([], $this->mensajesAFcm());
    }

    #[TestDox('avisos-E05: al cerrar la sesión en ese teléfono, la api borra su token y los avisos de la cuenta ya no se le mandan')]
    public function test_avisos_e05_al_cerrar_la_sesion_se_borra_el_token(): void
    {
        $pedido = $this->pedidoAceptadoDe($this->cuenta);
        $sesion = $this->cuenta->createToken('app')->plainTextToken;
        $this->registrarTelefono($this->cuenta, 'token-del-telefono', sesion: $sesion)->assertNoContent();
        $this->registrarTelefono($this->cuenta, 'token-de-otro-telefono')->assertNoContent();

        $this->cerrarSesion($sesion, ['token_fcm' => 'token-del-telefono'])->assertNoContent();

        $this->assertSame(['token-de-otro-telefono'], Dispositivo::query()->pluck('token_fcm')->all());
        $this->assertNull(PersonalAccessToken::findToken($sesion), 'La sesión queda cerrada.');

        $this->cambiarComoLaLavanderia($pedido, EstadoPedido::RECOLECTADO);
        $this->assertSame([], $this->mensajesAlToken('token-del-telefono'));
        $this->assertCount(1, $this->mensajesAlToken('token-de-otro-telefono'));
    }

    #[TestDox('avisos-E05: cerrar la sesión sin token, como en un teléfono sin permiso de avisos, sigue cerrándola')]
    public function test_avisos_e05_cerrar_la_sesion_sin_token_sigue_cerrandola(): void
    {
        $sesion = $this->cuenta->createToken('app')->plainTextToken;
        $this->telefonoDe($this->cuenta);

        $this->cerrarSesion($sesion)->assertNoContent();

        $this->assertNull(PersonalAccessToken::findToken($sesion));
        $this->assertSame(1, Dispositivo::query()->count());
    }

    #[TestDox('avisos-E05: al cerrar su sesión, una cuenta no borra el teléfono de otra aunque mande su token, y la respuesta es la misma')]
    public function test_avisos_e05_una_cuenta_no_borra_el_telefono_de_otra(): void
    {
        $ajeno = $this->telefonoDe($this->cuentaDeUsuario());
        $sesion = $this->cuenta->createToken('app')->plainTextToken;

        $this->cerrarSesion($sesion, ['token_fcm' => $ajeno->token_fcm])->assertNoContent();

        $this->assertTrue($ajeno->exists && Dispositivo::query()->whereKey($ajeno->getKey())->exists());
        $this->assertSame($ajeno->id_usuarios, $ajeno->fresh()->id_usuarios);
    }

    #[TestDox('avisos-E05: un token que no es texto no impide cerrar la sesión con su error')]
    public function test_avisos_e05_un_token_que_no_es_texto_responde_el_error_del_campo(): void
    {
        $sesion = $this->cuenta->createToken('app')->plainTextToken;

        $this->cerrarSesion($sesion, ['token_fcm' => ['no', 'es', 'texto']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token_fcm']);
    }

    #[TestDox('avisos-E05: un aviso que esperaba en la cola cuando se cerró la sesión no sale')]
    public function test_avisos_e05_un_aviso_que_esperaba_en_la_cola_no_sale(): void
    {
        $pedido = $this->pedidoConfirmadoDe($this->cuenta);
        $sesion = $this->cuenta->createToken('app')->plainTextToken;
        $this->registrarTelefono($this->cuenta, 'token-del-telefono', sesion: $sesion)->assertNoContent();
        $envio = new MandarAvisoAlTelefono($pedido, EstadoPedido::RECOLECTADO, Dispositivo::query()->sole()->getKey());

        $this->cerrarSesion($sesion, ['token_fcm' => 'token-del-telefono'])->assertNoContent();
        app()->call([$envio, 'handle']);

        $this->assertSame([], $this->mensajesAFcm());
    }

    #[TestDox('avisos-E05: al borrar la cuenta se cierran todas sus sesiones y se borran todos sus teléfonos, y ninguno de otra cuenta')]
    public function test_avisos_e05_al_borrar_la_cuenta_se_borran_todos_sus_telefonos(): void
    {
        $this->telefonoDe($this->cuenta);
        $this->telefonoDe($this->cuenta, Dispositivo::IOS);
        $ajeno = $this->telefonoDe($this->cuentaDeUsuario());

        $this->withToken($this->cuenta->createToken('app')->plainTextToken)
            ->deleteJson('/api/cuenta')
            ->assertNoContent();

        $this->assertSame([$ajeno->getKey()], Dispositivo::query()->pluck('id_dispositivos')->all());
    }

    #[TestDox('avisos-E06: cuando FCM responde que el token ya no está registrado, la api lo borra y no lo reintenta')]
    public function test_avisos_e06_un_token_que_fcm_ya_no_reconoce_se_borra(): void
    {
        $this->simularTokenRechazado();
        $pedido = $this->pedidoConfirmadoDe($this->cuenta);
        $telefono = $this->telefonoDe($this->cuenta);
        $envio = (new MandarAvisoAlTelefono($pedido, EstadoPedido::RECOLECTADO, $telefono->getKey()))
            ->withFakeQueueInteractions();

        app()->call([$envio, 'handle']);

        $this->assertFalse(Dispositivo::query()->whereKey($telefono->getKey())->exists());
        $envio->assertNotReleased();
        $envio->assertNotFailed();
    }

    #[TestDox('avisos-E06: el token que FCM rechaza al cambiar el estado se borra, el cambio se guarda y los otros teléfonos de la cuenta siguen')]
    public function test_avisos_e06_el_token_rechazado_se_borra_y_los_otros_telefonos_siguen(): void
    {
        $pedido = $this->pedidoAceptadoDe($this->cuenta);
        $rechazado = $this->telefonoDe($this->cuenta);
        $vigente = $this->telefonoDe($this->cuenta, Dispositivo::IOS);
        $this->simularFcm(function ($peticion) use ($rechazado) {
            return $peticion->data()['message']['token'] === $rechazado->token_fcm
                ? $this->respuestaDeFcm(404, 'NOT_FOUND', 'UNREGISTERED', 'Requested entity was not found.')
                : Http::response(['name' => 'projects/'.self::PROYECTO_DE_PRUEBA.'/messages/0:2']);
        });

        $this->cambiarComoLaLavanderia($pedido, EstadoPedido::RECOLECTADO);

        $this->assertSame(EstadoPedido::RECOLECTADO, $pedido->fresh()->estado->codigo);
        $this->assertSame([$vigente->getKey()], Dispositivo::query()->pluck('id_dispositivos')->all());
    }

    #[TestDox('avisos-E06: un token que FCM no reconoce como suyo, o que no es un token válido, también se borra')]
    public function test_avisos_e06_un_token_de_otro_proyecto_o_no_valido_se_borra(): void
    {
        $pedido = $this->pedidoConfirmadoDe($this->cuenta);

        foreach ([
            $this->respuestaDeFcm(403, 'PERMISSION_DENIED', 'SENDER_ID_MISMATCH', 'SenderId mismatch'),
            $this->respuestaDeFcm(400, 'INVALID_ARGUMENT', 'INVALID_ARGUMENT', 'The registration token is not a valid FCM registration token'),
        ] as $respuesta) {
            $this->simularFcm($respuesta);
            $telefono = $this->telefonoDe($this->cuenta);

            app()->call([new MandarAvisoAlTelefono($pedido, EstadoPedido::RECOLECTADO, $telefono->getKey()), 'handle']);

            $this->assertFalse(Dispositivo::query()->whereKey($telefono->getKey())->exists());
        }
    }

    #[TestDox('avisos-E06: un error de FCM que no es del token, como un mensaje mal armado, no borra el token')]
    public function test_avisos_e06_un_error_que_no_es_del_token_no_lo_borra(): void
    {
        $this->simularFcm($this->respuestaDeFcm(400, 'INVALID_ARGUMENT', 'INVALID_ARGUMENT', 'Invalid value at message.data'));
        $pedido = $this->pedidoConfirmadoDe($this->cuenta);
        $telefono = $this->telefonoDe($this->cuenta);

        app()->call([new MandarAvisoAlTelefono($pedido, EstadoPedido::RECOLECTADO, $telefono->getKey()), 'handle']);

        $this->assertTrue(Dispositivo::query()->whereKey($telefono->getKey())->exists());
    }

    /**
     * La app registra el token del teléfono con la sesión de la cuenta: una nueva, o la que se pase.
     */
    private function registrarTelefono(
        Usuario $cuenta,
        string $token,
        string $plataforma = Dispositivo::ANDROID,
        ?string $sesion = null,
    ): TestResponse {
        return $this->registrar($cuenta, ['token_fcm' => $token, 'plataforma' => $plataforma], $sesion);
    }

    /**
     * @param  array<string, mixed>  $cuerpo
     */
    private function registrar(Usuario $cuenta, array $cuerpo, ?string $sesion = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($sesion ?? $cuenta->createToken('app')->plainTextToken)
            ->postJson('/api/dispositivos', $cuerpo);
    }

    /**
     * La app cierra la sesión con ese token de Sanctum, con el cuerpo que se pase.
     *
     * @param  array<string, mixed>  $cuerpo
     */
    private function cerrarSesion(string $sesion, array $cuerpo = []): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($sesion)->deleteJson('/api/sesion', $cuerpo);
    }

    /**
     * Un error de la API HTTP v1 de FCM, con su detalle FcmError.
     */
    private function respuestaDeFcm(int $codigo, string $estado, string $errorCode, string $mensaje): mixed
    {
        return Http::response([
            'error' => [
                'code' => $codigo,
                'message' => $mensaje,
                'status' => $estado,
                'details' => [[
                    '@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError',
                    'errorCode' => $errorCode,
                ]],
            ],
        ], $codigo);
    }
}
