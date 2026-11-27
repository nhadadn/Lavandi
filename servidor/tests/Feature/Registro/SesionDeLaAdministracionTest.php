<?php

namespace Tests\Feature\Registro;

use App\Autorizacion\Autorizacion;
use App\Identidad\TokensDeLaApp;
use App\Models\Rol;
use App\Models\Usuario;
use App\Notifications\EnlaceDeRecuperacion;
use Database\Factories\UsuarioFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\AdministracionEnLaApp\Soporte\SesionesDeLaApp;
use Tests\TestCase;

/**
 * La sesión de la administración en la app: inicia sesión con su correo y su contraseña en POST /api/sesion, como los
 * otros roles, y recibe un token de administración, con la habilidad administracion y ninguna otra, que vence a los días
 * de la configuración. Lo revocan el cierre de la sesión, la contraseña nueva de la recuperación y el vencimiento
 * (NSV-2026-001-v8.pdf:3:Cláusula I; :20:ANEXO A, Sección A.3, Control de acceso; :10:Cláusula X; C-247; ADR-0022,
 * decisiones 1 a 4). Los tokens de los otros roles no cambian.
 *
 * Los tokens son los reales, de App\Identidad\TokensDeLaApp, y van con withToken(). Cada petición olvida la cuenta de la
 * anterior, como dos peticiones reales al servidor.
 *
 * Escenarios: administracion-app-E01, administracion-app-E04, administracion-app-E05 y registro-E10, con registro-E12 y
 * registro-E24 para la cuenta de administración · Spec:
 * openspec/changes/administracion-en-la-app/specs/administracion-en-la-app/spec.md y
 * openspec/changes/administracion-en-la-app/specs/registro/spec.md · Tareas 1.1 y 1.2
 */
class SesionDeLaAdministracionTest extends TestCase
{
    use RefreshDatabase;
    use SesionesDeLaApp;

    private const CORREO = 'duenio@ejemplo.com';

    private const CONTRASENA_NUEVA = 'contrasena-nueva-de-prueba';

    #[TestDox('administracion-app-E01: con su correo y su contraseña, la cuenta de administración recibe un token y su cuenta con el rol administracion')]
    public function test_administracion_app_e01_la_administracion_inicia_sesion_con_su_correo_y_su_contrasena(): void
    {
        $cuenta = $this->cuentaDeAdministracion(['correo' => self::CORREO]);

        $respuesta = $this->iniciarSesion(self::CORREO, UsuarioFactory::CONTRASENA);

        $respuesta->assertOk()
            ->assertJsonStructure(['token', 'usuario' => ['id_publico', 'correo', 'rol']])
            ->assertJsonPath('usuario.id_publico', $cuenta->id_publico)
            ->assertJsonPath('usuario.correo', self::CORREO)
            ->assertJsonPath('usuario.rol', Rol::ADMINISTRACION);

        $this->consultarCuenta($respuesta->json('token'))
            ->assertOk()
            ->assertJsonPath('usuario.rol', Rol::ADMINISTRACION);
    }

    #[TestDox('administracion-app-E01: el token se llama administracion, lleva la habilidad administracion y ninguna otra, y vence a los 30 días de abierto, los de la configuración')]
    public function test_administracion_app_e01_el_token_es_de_administracion_y_vence_a_los_dias_de_la_configuracion(): void
    {
        $this->freezeTime();
        $this->cuentaDeAdministracion(['correo' => self::CORREO]);

        $this->assertSame(30, config('acceso.dias_del_token_de_administracion'));
        $this->iniciarSesion(self::CORREO, UsuarioFactory::CONTRASENA)->assertOk();

        $token = PersonalAccessToken::query()->sole();
        $this->assertSame(TokensDeLaApp::NOMBRE_DE_ADMINISTRACION, $token->name);
        $this->assertSame([Autorizacion::HABILIDAD_DE_ADMINISTRACION], $token->abilities);
        $this->assertSame(now()->addDays(30)->toDateTimeString(), $token->expires_at?->toDateTimeString());
    }

    #[TestDox('administracion-app-E01: si la configuración cambia los días, el token vence a los días nuevos')]
    public function test_administracion_app_e01_si_la_configuracion_cambia_los_dias_el_token_vence_a_los_dias_nuevos(): void
    {
        $this->freezeTime();
        config(['acceso.dias_del_token_de_administracion' => 7]);
        $this->cuentaDeAdministracion(['correo' => self::CORREO]);

        $this->iniciarSesion(self::CORREO, UsuarioFactory::CONTRASENA)->assertOk();

        $this->assertSame(
            now()->addDays(7)->toDateTimeString(),
            PersonalAccessToken::query()->sole()->expires_at?->toDateTimeString(),
        );
    }

    #[TestDox('administracion-app-E01: los días salen de la variable ACCESO_DIAS_DEL_TOKEN_DE_ADMINISTRACION de config/acceso.php, 30 si no está, va vacía o en cero')]
    public function test_administracion_app_e01_los_dias_salen_de_la_variable_de_entorno(): void
    {
        $this->assertSame(12, $this->configuracionDeAccesoConEntorno(['ACCESO_DIAS_DEL_TOKEN_DE_ADMINISTRACION' => '12'])['dias_del_token_de_administracion']);
        $this->assertSame(30, $this->configuracionDeAccesoConEntorno(['ACCESO_DIAS_DEL_TOKEN_DE_ADMINISTRACION' => ''])['dias_del_token_de_administracion']);
        // En cero, el token nacería vencido y no abriría ninguna sesión.
        $this->assertSame(30, $this->configuracionDeAccesoConEntorno(['ACCESO_DIAS_DEL_TOKEN_DE_ADMINISTRACION' => '0'])['dias_del_token_de_administracion']);
        $this->assertSame(30, $this->configuracionDeAccesoConEntorno([])['dias_del_token_de_administracion']);
    }

    #[TestDox('administracion-app-E01: los tokens de las cuentas de usuario, de lavandería y de repartidor salen como antes: llamados app, con "*" y sin vencimiento')]
    public function test_administracion_app_e01_los_tokens_de_los_otros_roles_salen_como_antes(): void
    {
        foreach (self::ROLES_QUE_NO_SON_DE_ADMINISTRACION as $rol) {
            $cuenta = $this->cuentaQueOperaComo($rol);

            $this->iniciarSesion($cuenta->correo, UsuarioFactory::CONTRASENA)->assertOk()->assertJsonPath('usuario.rol', $rol);

            $token = $cuenta->tokens()->sole();
            $this->assertSame(TokensDeLaApp::NOMBRE, $token->name, $rol);
            $this->assertSame(['*'], $token->abilities, $rol);
            $this->assertNull($token->expires_at, $rol);
        }
    }

    #[TestDox('administracion-app-E01: con una contraseña incorrecta, la cuenta de administración recibe lo mismo que cualquier cuenta (registro-E08)')]
    public function test_administracion_app_e01_con_una_contrasena_incorrecta_responde_lo_mismo_que_a_cualquier_cuenta(): void
    {
        $this->cuentaDeAdministracion(['correo' => self::CORREO]);
        Usuario::factory()->conRol(Rol::USUARIO)->create(['correo' => 'persona@ejemplo.com']);

        $administracion = $this->iniciarSesion(self::CORREO, 'otra-contrasena');
        $usuario = $this->iniciarSesion('persona@ejemplo.com', 'otra-contrasena');

        $administracion->assertUnprocessable()->assertJsonMissingPath('token');
        $this->assertSame($usuario->status(), $administracion->status());
        $this->assertSame($usuario->json(), $administracion->json());
        $this->assertSame(0, PersonalAccessToken::count());
    }

    #[TestDox('administracion-app-E01: con la cuenta bloqueada, la cuenta de administración recibe lo mismo que cualquier cuenta bloqueada (administracion-E03)')]
    public function test_administracion_app_e01_con_la_cuenta_bloqueada_responde_lo_mismo_que_a_cualquier_cuenta(): void
    {
        $this->cuentaDeAdministracion(['correo' => self::CORREO, 'estado' => Usuario::BLOQUEADO]);
        Usuario::factory()->conRol(Rol::USUARIO)->create(['correo' => 'persona@ejemplo.com', 'estado' => Usuario::BLOQUEADO]);

        $administracion = $this->iniciarSesion(self::CORREO, UsuarioFactory::CONTRASENA);
        $usuario = $this->iniciarSesion('persona@ejemplo.com', UsuarioFactory::CONTRASENA);

        $administracion->assertUnprocessable()
            ->assertJsonValidationErrors(['correo' => 'Esta cuenta está bloqueada.'])
            ->assertJsonMissingPath('token');
        $this->assertSame($usuario->status(), $administracion->status());
        $this->assertSame($usuario->json(), $administracion->json());
        $this->assertSame(0, PersonalAccessToken::count());
    }

    #[TestDox('administracion-app-E01: con demasiados intentos, la cuenta de administración recibe lo mismo que cualquier cuenta (registro-E30)')]
    public function test_administracion_app_e01_con_demasiados_intentos_responde_lo_mismo_que_a_cualquier_cuenta(): void
    {
        $this->cuentaDeAdministracion(['correo' => self::CORREO]);
        Usuario::factory()->conRol(Rol::USUARIO)->create(['correo' => 'persona@ejemplo.com']);
        $limite = (int) config('acceso.intentos_por_minuto');

        foreach ([self::CORREO, 'persona@ejemplo.com'] as $correo) {
            for ($intento = 1; $intento <= $limite; $intento++) {
                $this->iniciarSesion($correo, 'otra-contrasena')->assertUnprocessable();
            }
        }

        // Con la contraseña correcta: si la revisara, abriría sesión.
        $administracion = $this->iniciarSesion(self::CORREO, UsuarioFactory::CONTRASENA);
        $usuario = $this->iniciarSesion('persona@ejemplo.com', UsuarioFactory::CONTRASENA);

        $administracion->assertTooManyRequests()->assertJsonMissingPath('token');
        $this->assertSame($usuario->status(), $administracion->status());
        $this->assertSame($usuario->json(), $administracion->json());
        $this->assertSame(0, PersonalAccessToken::count());
    }

    #[TestDox('administracion-app-E04: DELETE /api/sesion revoca el token de administración con el que llega, y solo ese: la otra sesión de la cuenta sigue abierta (registro-E12)')]
    public function test_administracion_app_e04_cerrar_la_sesion_revoca_el_token_de_administracion_con_el_que_llega(): void
    {
        $cuenta = $this->cuentaDeAdministracion(['correo' => self::CORREO]);
        $telefono = $this->iniciarSesion(self::CORREO, UsuarioFactory::CONTRASENA)->assertOk()->json('token');
        $tableta = $this->iniciarSesion(self::CORREO, UsuarioFactory::CONTRASENA)->assertOk()->json('token');
        $this->assertSame(2, $cuenta->tokens()->count());

        $this->llamarConToken($telefono, 'DELETE', '/api/sesion')->assertNoContent();

        $this->assertSame(1, $cuenta->tokens()->count());
        $this->consultarCuenta($telefono)->assertUnauthorized();
        $this->consultarCuenta($tableta)->assertOk()->assertJsonPath('usuario.rol', Rol::ADMINISTRACION);
    }

    #[TestDox('administracion-app-E05: un token de administración vencido recibe 401; uno que todavía no vence sigue sirviendo, sin pedir otra vez la contraseña')]
    public function test_administracion_app_e05_un_token_vencido_recibe_401_y_uno_vigente_sigue_sirviendo(): void
    {
        $this->freezeTime();
        $this->cuentaDeAdministracion(['correo' => self::CORREO]);
        $token = $this->iniciarSesion(self::CORREO, UsuarioFactory::CONTRASENA)->assertOk()->json('token');

        $this->travel(29)->days();
        $this->consultarCuenta($token)->assertOk();

        $this->travelTo(now()->addDay()->addSecond());
        $this->consultarCuenta($token)->assertUnauthorized()->assertJsonMissingPath('usuario');
        $this->llamarConToken($token, 'DELETE', '/api/sesion')->assertUnauthorized();
    }

    #[TestDox('administracion-app-E05: el token de administración revocado al cerrar la sesión recibe 401')]
    public function test_administracion_app_e05_el_token_revocado_al_cerrar_la_sesion_recibe_401(): void
    {
        $this->cuentaDeAdministracion(['correo' => self::CORREO]);
        $token = $this->iniciarSesion(self::CORREO, UsuarioFactory::CONTRASENA)->assertOk()->json('token');

        $this->llamarConToken($token, 'DELETE', '/api/sesion')->assertNoContent();

        $this->consultarCuenta($token)->assertUnauthorized();
        $this->llamarConToken($token, 'DELETE', '/api/sesion')->assertUnauthorized();
    }

    #[TestDox('administracion-app-E05: la contraseña nueva de la recuperación revoca los tokens de administración de la cuenta, que reciben 401 (registro-E24)')]
    public function test_administracion_app_e05_la_contrasena_nueva_de_la_recuperacion_revoca_los_tokens_de_administracion(): void
    {
        $cuenta = $this->cuentaDeAdministracion(['correo' => self::CORREO]);
        $telefono = $this->iniciarSesion(self::CORREO, UsuarioFactory::CONTRASENA)->assertOk()->json('token');
        $tableta = $this->iniciarSesion(self::CORREO, UsuarioFactory::CONTRASENA)->assertOk()->json('token');

        $this->guardarContrasena($this->tokenDeRecuperacion(), self::CONTRASENA_NUEVA)->assertOk();

        $this->assertSame(0, $cuenta->tokens()->count());
        $this->consultarCuenta($telefono)->assertUnauthorized();
        $this->consultarCuenta($tableta)->assertUnauthorized();

        // Con la contraseña nueva vuelve a entrar, con un token de administración nuevo.
        $nuevo = $this->iniciarSesion(self::CORREO, self::CONTRASENA_NUEVA)->assertOk()->json('token');
        $this->consultarCuenta($nuevo)->assertOk()->assertJsonPath('usuario.rol', Rol::ADMINISTRACION);
        $this->assertSame([Autorizacion::HABILIDAD_DE_ADMINISTRACION], $cuenta->tokens()->sole()->abilities);
    }

    #[TestDox('registro-E10: con el token de una cuenta de administración que ya venció, la consulta de la cuenta responde que no hay sesión, sin datos')]
    public function test_registro_e10_con_el_token_de_administracion_vencido_no_hay_sesion(): void
    {
        $cuenta = $this->cuentaDeAdministracion(['correo' => self::CORREO]);
        $vigente = $this->tokenDeLaApp($cuenta);
        $vencido = $this->tokenVencido($cuenta);

        $this->consultarCuenta($vigente)->assertOk()->assertJsonPath('usuario.correo', self::CORREO);
        $this->consultarCuenta($vencido)->assertUnauthorized()->assertJsonMissingPath('usuario');
    }

    private function iniciarSesion(string $correo, string $password): TestResponse
    {
        return $this->llamarConToken(null, 'POST', route('sesion.iniciar'), ['correo' => $correo, 'password' => $password]);
    }

    private function consultarCuenta(string $token): TestResponse
    {
        return $this->llamarConToken($token, 'GET', route('cuenta'));
    }

    /**
     * Pide el enlace de recuperación por la API, como la app, y devuelve el token que llegó en el correo.
     */
    private function tokenDeRecuperacion(): string
    {
        Notification::fake();
        $this->llamarConToken(null, 'POST', route('contrasena.olvido'), ['correo' => self::CORREO])->assertOk();

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
        $this->app['auth']->forgetGuards();
        $this->withoutToken();

        return $this->from(route('contrasena.formulario', ['token' => $token, 'correo' => self::CORREO]))
            ->post(route('contrasena.restablecer'), [
                'token' => $token,
                'correo' => self::CORREO,
                'password' => $password,
                'password_confirmation' => $password,
            ]);
    }

    /**
     * Lee config/acceso.php de nuevo con unas variables de entorno puestas solo durante la lectura. env() lee de
     * $_SERVER, de $_ENV y de getenv(), donde Dotenv también deja lo del .env local: la variable se quita de los tres
     * durante la lectura y vuelve después a como estaba, para que un valor del .env local no cambie el resultado.
     *
     * @param  array<string, string>  $variables
     * @return array<string, mixed>
     */
    private function configuracionDeAccesoConEntorno(array $variables): array
    {
        $nombre = 'ACCESO_DIAS_DEL_TOKEN_DE_ADMINISTRACION';
        $antes = [$_SERVER[$nombre] ?? null, $_ENV[$nombre] ?? null, getenv($nombre)];
        unset($_SERVER[$nombre], $_ENV[$nombre]);
        putenv($nombre);

        foreach ($variables as $variable => $valor) {
            $_SERVER[$variable] = $_ENV[$variable] = $valor;
        }

        try {
            return require config_path('acceso.php');
        } finally {
            unset($_SERVER[$nombre], $_ENV[$nombre]);

            if ($antes[0] !== null) {
                $_SERVER[$nombre] = $antes[0];
            }

            if ($antes[1] !== null) {
                $_ENV[$nombre] = $antes[1];
            }

            if ($antes[2] !== false) {
                putenv("{$nombre}={$antes[2]}");
            }
        }
    }
}
