<?php

namespace Tests\Feature\Registro;

use App\Identidad\TokensDeLaApp;
use App\Models\CuentaExterna;
use App\Models\Rol;
use App\Models\Usuario;
use App\Notifications\EnlaceDeRecuperacion;
use Database\Factories\UsuarioFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Registro\Soporte\ImitaAGoogle;
use Tests\TestCase;

/**
 * El inicio de sesión con Google: POST /api/sesion/google recibe el idToken que la app obtuvo de Google, lo valida
 * contra Google y contra los clientes configurados, y crea, encuentra o liga la cuenta antes de abrir la sesión
 * (NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #2, "con opción de Google Sign-In"; ADR-0005; C-224).
 *
 * Un idToken cuyo correo Google no da por verificado se rechaza siempre, haya o no cuenta con ese correo, con el mismo
 * mensaje que cualquier otro idToken no válido: no dice si existe una cuenta (C-228, DD-128 cerrada).
 *
 * Al ligar Google a una cuenta que ya existía, se cierran las sesiones que tenía abiertas y su contraseña queda
 * anulada: quien registró el correo antes ya no entra, y el dueño del correo puede crear otra con la recuperación
 * (DD-132, decisión 23 de acta-2026-11-20-modelo-y-pasarela).
 *
 * Una cuenta de administración no entra con Google, tenga o no Google ligado: entra con su correo y su contraseña, y
 * la respuesta no le liga nada, ni le anula la contraseña, ni le cierra las sesiones (ADR-0022, decisión 5).
 *
 * Las pruebas no salen a la red: la Google de prueba firma con sus propias llaves (Soporte/ImitaAGoogle). Cada
 * petición olvida la cuenta de la anterior, como dos peticiones reales al servidor.
 *
 * Escenarios: registro-E16, registro-E17, registro-E18, registro-E33 (la administración que entra con Google) y
 * administracion-app-E08 · Spec:
 * openspec/changes/registro-e-inicio-de-sesion/specs/registro/spec.md, con el delta de
 * openspec/changes/administracion-en-la-app/specs/registro/spec.md
 */
class GoogleTest extends TestCase
{
    use ImitaAGoogle;
    use RefreshDatabase;

    private const CORREO = 'persona@ejemplo.com';

    private const OTRO_CORREO = 'otra@ejemplo.com';

    private const CONTRASENA_NUEVA = 'contrasena-nueva-de-prueba';

    private const NO_VALIDO = 'No se pudo entrar con tu cuenta de Google.';

    private const CREDENCIALES_NO_VALIDAS = 'Las credenciales no son válidas.';

    private const ADMINISTRACION_CON_CONTRASENA = 'La administración entra con su correo y su contraseña.';

    protected function setUp(): void
    {
        parent::setUp();

        $this->imitarAGoogle();
    }

    #[TestDox('registro-E16: con un correo que Google da por verificado y sin cuenta, se crea la cuenta con el rol de usuario y la respuesta trae un token de sesión')]
    public function test_registro_e16_con_un_correo_verificado_sin_cuenta_se_crea_la_cuenta_con_el_rol_de_usuario_y_la_respuesta_trae_un_token(): void
    {
        $respuesta = $this->entrarConGoogle($this->idTokenDeGoogle(['email_verified' => true]));

        $respuesta->assertOk();
        $respuesta->assertJsonStructure(['token', 'usuario' => ['id_publico', 'correo', 'rol']]);
        $respuesta->assertJsonPath('usuario.correo', self::CORREO);
        $respuesta->assertJsonPath('usuario.rol', Rol::USUARIO);

        $usuario = Usuario::sole();
        $this->assertSame($usuario->id_publico, $respuesta->json('usuario.id_publico'));
        $this->assertSame([Rol::USUARIO], $usuario->roles()->pluck('codigo')->all());
        $this->assertNull($usuario->password, 'Quien entra con Google no tiene contraseña.');
        $this->assertSame('activo', $usuario->fresh()->estado);

        $cuentaDeGoogle = CuentaExterna::sole();
        $this->assertSame($usuario->getKey(), $cuentaDeGoogle->id_usuarios);
        $this->assertSame('google', $cuentaDeGoogle->proveedor);
        $this->assertSame(self::SUJETO_DE_PRUEBA, $cuentaDeGoogle->sujeto);
        $this->assertSame(self::CORREO, $cuentaDeGoogle->correo);

        // El token abre la sesión de esa cuenta.
        $this->consultarCuenta($respuesta->json('token'))
            ->assertOk()
            ->assertJsonPath('usuario.id_publico', $usuario->id_publico);
    }

    #[TestDox('registro-E16: si la petición trae otro rol, la cuenta queda igual con el rol de usuario')]
    public function test_registro_e16_si_la_peticion_trae_otro_rol_la_cuenta_queda_igual_con_el_rol_de_usuario(): void
    {
        $this->entrarConGoogle($this->idTokenDeGoogle(), ['rol' => Rol::ADMINISTRACION])
            ->assertOk()
            ->assertJsonPath('usuario.rol', Rol::USUARIO);

        $this->assertSame([Rol::USUARIO], Usuario::sole()->roles()->pluck('codigo')->all());
    }

    #[TestDox('registro-E16: la misma cuenta de Google vuelve a entrar a la misma cuenta, sin crear otra')]
    public function test_registro_e16_la_misma_cuenta_de_google_vuelve_a_entrar_a_la_misma_cuenta_sin_crear_otra(): void
    {
        $primera = $this->entrarConGoogle($this->idTokenDeGoogle());
        $segunda = $this->entrarConGoogle($this->idTokenDeGoogle());

        $primera->assertOk();
        $segunda->assertOk();
        $this->assertSame($primera->json('usuario.id_publico'), $segunda->json('usuario.id_publico'));
        $this->assertNotSame($primera->json('token'), $segunda->json('token'));

        $usuario = Usuario::sole();
        $this->assertSame(1, CuentaExterna::count());
        $this->assertSame(2, $usuario->tokens()->count());
        $this->assertSame(1, $usuario->roles()->count());
    }

    #[TestDox('registro-E16: la cuenta de Google se reconoce por su sujeto, aunque en Google haya cambiado el correo')]
    public function test_registro_e16_la_cuenta_de_google_se_reconoce_por_su_sujeto_aunque_en_google_haya_cambiado_el_correo(): void
    {
        $primera = $this->entrarConGoogle($this->idTokenDeGoogle());
        $segunda = $this->entrarConGoogle($this->idTokenDeGoogle(['email' => 'correo-nuevo@ejemplo.com']));

        $segunda->assertOk();
        $this->assertSame($primera->json('usuario.id_publico'), $segunda->json('usuario.id_publico'));
        $this->assertSame(1, Usuario::count());
        $this->assertSame(1, CuentaExterna::count());
    }

    #[TestDox('registro-E16: una cuenta borrada no vuelve a entrar con Google, y no se crea otra')]
    public function test_registro_e16_una_cuenta_borrada_no_vuelve_a_entrar_con_google_y_no_se_crea_otra(): void
    {
        $this->entrarConGoogle($this->idTokenDeGoogle())->assertOk();
        $usuario = Usuario::sole();
        $usuario->tokens()->delete();
        $usuario->delete();

        $this->assertRechazado($this->entrarConGoogle($this->idTokenDeGoogle()), self::NO_VALIDO);

        $this->assertSame(1, Usuario::withTrashed()->count());
        $this->assertSame(1, CuentaExterna::count());
    }

    #[TestDox('registro-E17: un idToken vencido se rechaza y no emite sesión')]
    public function test_registro_e17_un_id_token_vencido_se_rechaza_y_no_emite_sesion(): void
    {
        $vencido = $this->idTokenDeGoogle([
            'iat' => now()->subHours(2)->getTimestamp(),
            'exp' => now()->subHour()->getTimestamp(),
        ]);

        $this->assertRechazadoSinCuenta($this->entrarConGoogle($vencido));
    }

    #[TestDox('registro-E17: un idToken con firma no válida se rechaza y no emite sesión')]
    public function test_registro_e17_un_id_token_con_firma_no_valida_se_rechaza_y_no_emite_sesion(): void
    {
        $this->assertRechazadoSinCuenta($this->entrarConGoogle($this->idTokenDeGoogle(firmante: 'impostor')));
    }

    #[TestDox('registro-E17: un idToken emitido para otro cliente se rechaza y no emite sesión')]
    public function test_registro_e17_un_id_token_emitido_para_otro_cliente_se_rechaza_y_no_emite_sesion(): void
    {
        $deOtroCliente = $this->idTokenDeGoogle(['aud' => self::CLIENTE_AJENO, 'azp' => self::CLIENTE_AJENO]);

        $this->assertRechazadoSinCuenta($this->entrarConGoogle($deOtroCliente));
    }

    #[TestDox('registro-E17: un idToken con un correo que Google no da por verificado y sin cuenta se rechaza, sin crear la cuenta ni emitir sesión')]
    public function test_registro_e17_un_id_token_con_un_correo_que_google_no_da_por_verificado_y_sin_cuenta_se_rechaza_sin_crear_la_cuenta(): void
    {
        // El reclamo en falso, el reclamo ausente y el reclamo en texto: ninguno es un correo verificado.
        foreach ([false, null, 'false'] as $verificado) {
            $sinVerificar = $this->idTokenDeGoogle(['email_verified' => $verificado]);

            $this->assertRechazadoSinCuenta($this->entrarConGoogle($sinVerificar));
        }
    }

    #[TestDox('registro-E17: una cuenta de Google ya ligada no entra si su idToken llega con el correo sin verificar')]
    public function test_registro_e17_una_cuenta_de_google_ya_ligada_no_entra_si_su_id_token_llega_con_el_correo_sin_verificar(): void
    {
        $this->entrarConGoogle($this->idTokenDeGoogle())->assertOk();
        $usuario = Usuario::sole();
        $usuario->tokens()->delete();

        $sinVerificar = $this->idTokenDeGoogle(['email_verified' => false]);

        $this->assertRechazado($this->entrarConGoogle($sinVerificar), self::NO_VALIDO);
        $this->assertSame(0, PersonalAccessToken::count());
        $this->assertSame(1, Usuario::count());
        $this->assertSame(1, CuentaExterna::count());
    }

    #[TestDox('registro-E17: un idToken falso con el sujeto de una cuenta que ya existe no abre su sesión')]
    public function test_registro_e17_un_id_token_falso_con_el_sujeto_de_una_cuenta_que_ya_existe_no_abre_su_sesion(): void
    {
        $this->entrarConGoogle($this->idTokenDeGoogle())->assertOk();
        $usuario = Usuario::sole();
        $usuario->tokens()->delete();

        $falso = $this->idTokenDeGoogle(['sub' => self::SUJETO_DE_PRUEBA], firmante: 'impostor');

        $this->assertRechazado($this->entrarConGoogle($falso), self::NO_VALIDO);
        $this->assertSame(0, PersonalAccessToken::count());
    }

    #[TestDox('registro-E17: sin idToken, o con uno que no es texto, el servidor rechaza la entrada en el campo id_token')]
    public function test_registro_e17_sin_id_token_o_con_uno_que_no_es_texto_el_servidor_rechaza_la_entrada_en_el_campo_id_token(): void
    {
        $this->postJson('/api/sesion/google', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['id_token' => 'Falta el token de Google.']);

        $this->postJson('/api/sesion/google', ['id_token' => ['no', 'es', 'texto']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['id_token' => self::NO_VALIDO]);

        $this->assertSame(0, Usuario::count());
        $this->assertSame(0, PersonalAccessToken::count());
    }

    #[TestDox('registro-E18: con un correo que ya tiene cuenta con contraseña y que Google da por verificado, se liga a esa cuenta')]
    public function test_registro_e18_con_un_correo_que_ya_tiene_cuenta_con_contrasena_y_que_google_da_por_verificado_se_liga_a_esa_cuenta(): void
    {
        $existente = $this->cuentaConContrasena();
        $this->assertTrue(Hash::check(UsuarioFactory::CONTRASENA, $existente->password));

        // Google puede escribir el correo con otras mayúsculas: es el mismo correo.
        $respuesta = $this->entrarConGoogle($this->idTokenDeGoogle(['email' => 'Persona@Ejemplo.com']));

        $respuesta->assertOk();
        $respuesta->assertJsonPath('usuario.id_publico', $existente->id_publico);
        $respuesta->assertJsonPath('usuario.rol', Rol::USUARIO);
        $this->assertSame(1, Usuario::count(), 'No se crea otra cuenta.');

        $cuentaDeGoogle = CuentaExterna::sole();
        $this->assertSame($existente->getKey(), $cuentaDeGoogle->id_usuarios);
        $this->assertSame('google', $cuentaDeGoogle->proveedor);
        $this->assertSame(self::SUJETO_DE_PRUEBA, $cuentaDeGoogle->sujeto);

        // La contraseña queda anulada (DD-132): la cuenta se queda sin contraseña, como la que nace con Google, y la
        // anterior ya no sirve. El token de la respuesta sí abre la sesión de esa cuenta.
        $this->assertNull($existente->fresh()->password, 'La contraseña anterior queda anulada.');
        $this->consultarCuenta($respuesta->json('token'))
            ->assertOk()
            ->assertJsonPath('usuario.id_publico', $existente->id_publico);
    }

    #[TestDox('registro-E18: al ligar, las sesiones que la cuenta tenía abiertas se cierran, y solo las de esa cuenta; la respuesta trae un token nuevo que sí sirve')]
    public function test_registro_e18_al_ligar_las_sesiones_que_la_cuenta_tenia_abiertas_se_cierran_y_la_respuesta_trae_un_token_nuevo(): void
    {
        $existente = $this->cuentaConContrasena();
        // Quien registró el correo antes tiene dos sesiones abiertas, y otra cuenta tiene la suya.
        $telefono = $this->iniciarSesion(UsuarioFactory::CONTRASENA)->assertOk()->json('token');
        $tableta = $this->iniciarSesion(UsuarioFactory::CONTRASENA)->assertOk()->json('token');
        $otraCuenta = Usuario::factory()->conRol(Rol::USUARIO)->create(['correo' => self::OTRO_CORREO]);
        $deLaOtraCuenta = app(TokensDeLaApp::class)->crear($otraCuenta)->plainTextToken;

        $respuesta = $this->entrarConGoogle($this->idTokenDeGoogle());

        $respuesta->assertOk();
        $respuesta->assertJsonPath('usuario.id_publico', $existente->id_publico);

        // Con un token revocado, el servidor responde que no hay sesión (registro-E10).
        $this->consultarCuenta($telefono)->assertUnauthorized();
        $this->consultarCuenta($tableta)->assertUnauthorized();
        $this->assertSame(1, $existente->tokens()->count(), 'Solo queda el token de la respuesta.');
        $this->consultarCuenta($respuesta->json('token'))
            ->assertOk()
            ->assertJsonPath('usuario.id_publico', $existente->id_publico);

        $this->consultarCuenta($deLaOtraCuenta)
            ->assertOk()
            ->assertJsonPath('usuario.id_publico', $otraCuenta->id_publico);
    }

    #[TestDox('registro-E18: con la contraseña anulada, la anterior ya no entra: la respuesta es la misma que con una contraseña equivocada (registro-E08)')]
    public function test_registro_e18_con_la_contrasena_anulada_la_anterior_ya_no_entra_y_la_respuesta_es_la_de_una_contrasena_equivocada(): void
    {
        $this->cuentaConContrasena();
        Usuario::factory()->conRol(Rol::USUARIO)->create(['correo' => self::OTRO_CORREO]);
        $this->entrarConGoogle($this->idTokenDeGoogle())->assertOk();
        $tokens = PersonalAccessToken::count();

        $conLaAnterior = $this->iniciarSesion(UsuarioFactory::CONTRASENA);
        // La contraseña equivocada de una cuenta que sí tiene contraseña (registro-E08).
        $conUnaEquivocada = $this->iniciarSesion('otra-contrasena', self::OTRO_CORREO);

        $conLaAnterior->assertUnprocessable();
        $conLaAnterior->assertJsonValidationErrors(['correo' => self::CREDENCIALES_NO_VALIDAS]);
        $conLaAnterior->assertJsonMissingPath('token');
        $this->assertSame($conUnaEquivocada->status(), $conLaAnterior->status());
        $this->assertSame($conUnaEquivocada->json(), $conLaAnterior->json());
        $this->assertSame($tokens, PersonalAccessToken::count(), 'La contraseña anterior no emite token.');
    }

    #[TestDox('registro-E18: con la contraseña anulada, el dueño del correo crea otra con la recuperación y entra con ella; volver a entrar con Google ya no la anula (registro-E22 a E24)')]
    public function test_registro_e18_con_la_contrasena_anulada_el_dueno_del_correo_crea_otra_con_la_recuperacion_y_entra_con_ella(): void
    {
        $existente = $this->cuentaConContrasena();
        $this->entrarConGoogle($this->idTokenDeGoogle())->assertOk();

        $this->guardarContrasena($this->tokenDeRecuperacion(), self::CONTRASENA_NUEVA)
            ->assertOk()
            ->assertSee('Tu contraseña nueva quedó guardada');

        $conLaNueva = $this->iniciarSesion(self::CONTRASENA_NUEVA);
        $conLaNueva->assertOk();
        $conLaNueva->assertJsonPath('usuario.id_publico', $existente->id_publico);
        $this->iniciarSesion(UsuarioFactory::CONTRASENA)->assertUnprocessable();

        // Ya ligada, la cuenta de Google vuelve a entrar sin ligar otra vez: no cierra la sesión que abrió la
        // contraseña nueva ni la anula (registro-E16).
        $this->entrarConGoogle($this->idTokenDeGoogle())
            ->assertOk()
            ->assertJsonPath('usuario.id_publico', $existente->id_publico);
        $this->consultarCuenta($conLaNueva->json('token'))->assertOk();
        $this->assertTrue(Hash::check(self::CONTRASENA_NUEVA, $existente->fresh()->password));
        $this->assertSame(1, CuentaExterna::count());
    }

    #[TestDox('registro-E18: ya ligada, la cuenta de Google vuelve a entrar a la cuenta existente')]
    public function test_registro_e18_ya_ligada_la_cuenta_de_google_vuelve_a_entrar_a_la_cuenta_existente(): void
    {
        $existente = $this->cuentaConContrasena();

        $this->entrarConGoogle($this->idTokenDeGoogle())->assertOk();
        $this->entrarConGoogle($this->idTokenDeGoogle())
            ->assertOk()
            ->assertJsonPath('usuario.id_publico', $existente->id_publico);

        $this->assertSame(1, Usuario::count());
        $this->assertSame(1, CuentaExterna::count());
    }

    #[TestDox('registro-E18: si Google no da el correo por verificado, el servidor rechaza la entrada y no liga nada, con el mensaje que no dice si hay cuenta')]
    public function test_registro_e18_si_google_no_da_el_correo_por_verificado_el_servidor_rechaza_la_entrada_y_no_liga_nada(): void
    {
        $existente = $this->cuentaConContrasena();
        $abierta = app(TokensDeLaApp::class)->crear($existente)->plainTextToken;

        $sinVerificar = $this->entrarConGoogle($this->idTokenDeGoogle(['email_verified' => false]));
        $sinReclamo = $this->entrarConGoogle($this->idTokenDeGoogle(['email_verified' => null]));

        // El mismo mensaje que sin cuenta (registro-E17): la respuesta no dice que el correo ya tiene una.
        $this->assertRechazado($sinVerificar, self::NO_VALIDO);
        $this->assertRechazado($sinReclamo, self::NO_VALIDO);
        $this->assertSame(0, CuentaExterna::count());
        $this->assertSame(1, Usuario::count());
        $this->assertSame(1, PersonalAccessToken::count(), 'El rechazo no emite sesión.');

        // Sin ligar, tampoco se cierran las sesiones ni se anula la contraseña.
        $this->consultarCuenta($abierta)->assertOk();
        $this->assertTrue(Hash::check(UsuarioFactory::CONTRASENA, $existente->fresh()->password));
    }

    #[TestDox('administracion-app-E08, registro-E18 y registro-E33: una cuenta de administración con contraseña, sin Google ligado y con su sesión abierta, que entra con Google con su correo verificado recibe 422 en id_token, sin token: no se liga, no se anula su contraseña y su sesión sigue abierta')]
    public function test_administracion_app_e08_registro_e18_y_registro_e33_la_administracion_sin_google_ligado_no_entra_con_google(): void
    {
        $administracion = Usuario::factory()->conRol(Rol::ADMINISTRACION)->create(['correo' => self::CORREO]);
        $abierta = $this->iniciarSesion(UsuarioFactory::CONTRASENA)->assertOk()->json('token');
        $antes = $administracion->fresh()->getAttributes();
        $tokens = PersonalAccessToken::query()->orderBy('id')->pluck('token')->all();

        $respuesta = $this->entrarConGoogle($this->idTokenDeGoogle(['email_verified' => true]));

        $this->assertRechazado($respuesta, self::ADMINISTRACION_CON_CONTRASENA);
        $this->assertSame(0, CuentaExterna::count(), 'No se liga la cuenta de Google.');
        $this->assertSame(1, Usuario::count(), 'No se crea otra cuenta.');
        $this->assertSame($tokens, PersonalAccessToken::query()->orderBy('id')->pluck('token')->all(), 'No se crea ni se borra ningún token.');
        $this->assertSame($antes, $administracion->fresh()->getAttributes(), 'La cuenta queda como estaba.');
        $this->assertTrue(Hash::check(UsuarioFactory::CONTRASENA, $administracion->fresh()->password));

        $this->consultarCuenta($abierta)->assertOk()->assertJsonPath('usuario.rol', Rol::ADMINISTRACION);
        $this->iniciarSesion(UsuarioFactory::CONTRASENA)->assertOk()->assertJsonPath('usuario.rol', Rol::ADMINISTRACION);
    }

    #[TestDox('administracion-app-E08: una cuenta de administración que ya tiene Google ligado recibe la misma respuesta: 422 en id_token, sin token y sin tocar su cuenta, su contraseña ni sus tokens')]
    public function test_administracion_app_e08_la_administracion_con_google_ya_ligado_tampoco_entra_con_google(): void
    {
        $administracion = Usuario::factory()->conRol(Rol::ADMINISTRACION)->create(['correo' => self::CORREO]);
        // Como la ligó el hueco de la tarea 6.2 de administracion, antes de ADR-0022.
        $administracion->cuentasExternas()->create([
            'proveedor' => 'google',
            'sujeto' => self::SUJETO_DE_PRUEBA,
            'correo' => self::CORREO,
        ]);
        $abierta = $this->iniciarSesion(UsuarioFactory::CONTRASENA)->assertOk()->json('token');
        // Y un token "*" que salió por Google entonces.
        $deGoogle = $administracion->createToken(TokensDeLaApp::NOMBRE)->plainTextToken;
        $antes = $administracion->fresh()->getAttributes();
        $tokens = PersonalAccessToken::query()->orderBy('id')->pluck('token')->all();

        // El mismo sujeto, aunque en Google haya cambiado el correo: es la rama de la cuenta ya ligada.
        $respuesta = $this->entrarConGoogle($this->idTokenDeGoogle(['email' => 'correo-nuevo@ejemplo.com']));

        $this->assertRechazado($respuesta, self::ADMINISTRACION_CON_CONTRASENA);
        $this->assertSame(1, CuentaExterna::count());
        $this->assertSame(1, Usuario::count());
        $this->assertSame($tokens, PersonalAccessToken::query()->orderBy('id')->pluck('token')->all(), 'No se crea ni se borra ningún token.');
        $this->assertSame($antes, $administracion->fresh()->getAttributes(), 'La cuenta queda como estaba.');
        $this->assertTrue(Hash::check(UsuarioFactory::CONTRASENA, $administracion->fresh()->password));

        $this->consultarCuenta($abierta)->assertOk();
        // El token "*" no abre la cuenta (administracion-app-E06).
        $this->consultarCuenta($deGoogle)->assertForbidden()->assertJsonMissingPath('usuario');
    }

    /**
     * @param  array<string, mixed>  $otrosDatos
     */
    private function entrarConGoogle(string $idToken, array $otrosDatos = []): TestResponse
    {
        $this->olvidarSesion();

        return $this->postJson('/api/sesion/google', ['id_token' => $idToken, ...$otrosDatos]);
    }

    private function consultarCuenta(string $token): TestResponse
    {
        $this->olvidarSesion();

        return $this->withToken($token)->getJson('/api/cuenta');
    }

    /**
     * La cuenta que alguien registró antes con el correo de la prueba y la contraseña de la fábrica.
     */
    private function cuentaConContrasena(): Usuario
    {
        return Usuario::factory()->conRol(Rol::USUARIO)->create(['correo' => self::CORREO]);
    }

    private function iniciarSesion(string $password, string $correo = self::CORREO): TestResponse
    {
        $this->olvidarSesion();

        return $this->postJson(route('sesion.iniciar'), ['correo' => $correo, 'password' => $password]);
    }

    /**
     * Pide el enlace de recuperación por la API, como la app, y devuelve el token que llegó en el correo.
     */
    private function tokenDeRecuperacion(): string
    {
        Notification::fake();
        $this->olvidarSesion();
        $this->postJson(route('contrasena.olvido'), ['correo' => self::CORREO])->assertOk();

        $token = null;
        Notification::assertSentOnDemand(
            EnlaceDeRecuperacion::class,
            function (EnlaceDeRecuperacion $aviso) use (&$token): bool {
                $token = $aviso->token;

                return $aviso->correo === self::CORREO;
            },
        );

        return (string) $token;
    }

    /**
     * Guarda la contraseña nueva en la página web del enlace (registro-E24).
     */
    private function guardarContrasena(string $token, string $password): TestResponse
    {
        $this->olvidarSesion();

        return $this->from(route('contrasena.formulario', ['token' => $token, 'correo' => self::CORREO]))
            ->post(route('contrasena.restablecer'), [
                'token' => $token,
                'correo' => self::CORREO,
                'password' => $password,
                'password_confirmation' => $password,
            ]);
    }

    /**
     * La aplicación de la prueba conserva la cuenta autenticada entre peticiones; un servidor real no.
     */
    private function olvidarSesion(): void
    {
        $this->app['auth']->forgetGuards();
        $this->withoutToken();
    }

    private function assertRechazado(TestResponse $respuesta, string $mensaje): void
    {
        $respuesta->assertUnprocessable();
        $respuesta->assertJsonValidationErrors(['id_token' => $mensaje]);
        $respuesta->assertJsonMissingPath('token');
        $respuesta->assertJsonMissingPath('usuario');
    }

    /**
     * Rechazado, y sin rastro: ni cuenta, ni cuenta de Google, ni token.
     */
    private function assertRechazadoSinCuenta(TestResponse $respuesta): void
    {
        $this->assertRechazado($respuesta, self::NO_VALIDO);
        $this->assertSame(0, Usuario::withTrashed()->count());
        $this->assertSame(0, CuentaExterna::count());
        $this->assertSame(0, PersonalAccessToken::count());
    }
}
