<?php

namespace Tests\Feature\PerfilUsuario;

use App\Models\CuentaExterna;
use App\Models\Usuario;
use Database\Factories\UsuarioFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\PerfilUsuario\Soporte\PerfilDePrueba;
use Tests\Feature\Registro\Soporte\ImitaAGoogle;
use Tests\TestCase;

/**
 * Borrar la cuenta desde la app: la cuenta queda borrada, sus sesiones abiertas se cierran y ya no vuelve a entrar, ni
 * con correo y contraseña ni con Google (ADR-0005, "Borrar la cuenta se puede desde la app, como piden las dos
 * tiendas"; openspec/changes/perfil-y-soporte/design.md, decisión 2).
 *
 * La cuenta se borra en suave (usuarios.deleted_at) y sus datos quedan como están: qué se borra y qué se conserva, y qué
 * pasa con un pedido en curso, es DD-109 (perfil-usuario-E16). La entrada con Apple todavía no existe (DD-069): cuando
 * llegue, rechaza la cuenta borrada como las otras dos.
 *
 * Cada petición olvida la cuenta de la anterior, como dos peticiones reales al servidor. Google es la de prueba
 * (Tests\Feature\Registro\Soporte\ImitaAGoogle), sin salir a la red.
 *
 * Escenarios: perfil-usuario-E15 · Spec: openspec/changes/perfil-y-soporte/specs/perfil-usuario/spec.md · Tarea 1.5
 * de openspec/changes/perfil-y-soporte/tasks.md
 */
class BorrarCuentaTest extends TestCase
{
    use ImitaAGoogle;
    use PerfilDePrueba;
    use RefreshDatabase;

    private const CORREO = 'persona@ejemplo.com';

    private const CREDENCIALES_NO_VALIDAS = 'Las credenciales no son válidas.';

    private const GOOGLE_NO_VALIDO = 'No se pudo entrar con tu cuenta de Google.';

    protected function setUp(): void
    {
        parent::setUp();

        $this->imitarAGoogle();
    }

    #[TestDox('perfil-usuario-E15: con sesión en dos teléfonos, la cuenta se borra y sus dos tokens quedan revocados')]
    public function test_perfil_usuario_e15_la_cuenta_se_borra_y_sus_dos_tokens_quedan_revocados(): void
    {
        $usuario = $this->cuentaDeUsuario(['correo' => self::CORREO]);
        $telefono1 = $this->iniciarSesion()->assertOk()->json('token');
        $telefono2 = $this->iniciarSesion()->assertOk()->json('token');
        $this->assertSame(2, $usuario->tokens()->count());

        $this->borrarCuenta($telefono1)->assertNoContent();

        foreach ([$telefono1, $telefono2] as $token) {
            $this->consultarCuenta($token)->assertUnauthorized();
        }
        $this->assertSame(0, PersonalAccessToken::count());

        $this->assertNull(Usuario::find($usuario->getKey()));
        $this->assertTrue(Usuario::withTrashed()->findOrFail($usuario->getKey())->trashed());
    }

    #[TestDox('perfil-usuario-E15: la cuenta borrada ya no inicia sesión con su correo y su contraseña, ni recibe el enlace para crear otra')]
    public function test_perfil_usuario_e15_la_cuenta_borrada_ya_no_inicia_sesion_con_correo_y_contrasena(): void
    {
        $this->cuentaDeUsuario(['correo' => self::CORREO]);
        $this->borrarCuenta($this->iniciarSesion()->json('token'))->assertNoContent();

        $respuesta = $this->iniciarSesion();

        $respuesta->assertUnprocessable();
        $respuesta->assertJsonValidationErrors(['correo' => self::CREDENCIALES_NO_VALIDAS]);
        $respuesta->assertJsonMissingPath('token');
        $this->assertSame(0, PersonalAccessToken::count());

        // Tampoco vuelve a entrar por la recuperación de la contraseña: no hay enlace para una cuenta borrada.
        Notification::fake();
        $this->olvidarSesion();
        $this->postJson('/api/contrasena/olvido', ['correo' => self::CORREO])->assertOk();
        Notification::assertNothingSent();
    }

    #[TestDox('perfil-usuario-E15: la cuenta de Google borrada ya no inicia sesión con Google, sus tokens quedan revocados y no se crea otra')]
    public function test_perfil_usuario_e15_la_cuenta_de_google_borrada_ya_no_inicia_sesion_con_google(): void
    {
        $telefono1 = $this->entrarConGoogle()->assertOk()->json('token');
        $telefono2 = $this->entrarConGoogle()->assertOk()->json('token');
        $usuario = Usuario::sole();
        $this->assertSame(2, $usuario->tokens()->count());

        $this->borrarCuenta($telefono2)->assertNoContent();

        foreach ([$telefono1, $telefono2] as $token) {
            $this->consultarCuenta($token)->assertUnauthorized();
        }

        $respuesta = $this->entrarConGoogle();

        $respuesta->assertUnprocessable();
        $respuesta->assertJsonValidationErrors(['id_token' => self::GOOGLE_NO_VALIDO]);
        $respuesta->assertJsonMissingPath('token');
        $this->assertSame(0, PersonalAccessToken::count());
        $this->assertSame(1, Usuario::withTrashed()->count());
        $this->assertSame(1, CuentaExterna::count());
    }

    #[TestDox('perfil-usuario-E15: borrar una cuenta no toca otra: la otra conserva su cuenta y sus sesiones')]
    public function test_perfil_usuario_e15_borrar_una_cuenta_no_toca_otra(): void
    {
        $ana = $this->cuentaDeUsuario(['correo' => self::CORREO]);
        $beto = $this->cuentaDeUsuario(['correo' => 'beto@ejemplo.com']);
        $tokenDeBeto = $beto->createToken('app')->plainTextToken;

        $this->borrarCuenta($ana->createToken('app')->plainTextToken)->assertNoContent();

        $this->consultarCuenta($tokenDeBeto)->assertOk()->assertJsonPath('usuario.id_publico', $beto->id_publico);
        $this->assertFalse($beto->fresh()->trashed());
        $this->assertSame(1, $beto->tokens()->count());
        $this->assertTrue(Usuario::withTrashed()->findOrFail($ana->getKey())->trashed());
    }

    #[TestDox('perfil-usuario-E15: sin sesión, o sin el rol de usuario, no se borra ninguna cuenta ni se revoca ningún token')]
    public function test_perfil_usuario_e15_sin_sesion_o_sin_el_rol_de_usuario_no_se_borra_ninguna_cuenta(): void
    {
        $usuario = $this->cuentaDeUsuario(['correo' => self::CORREO]);
        $usuario->createToken('app');
        $sinRol = Usuario::factory()->create();
        $tokenSinRol = $sinRol->createToken('app')->plainTextToken;

        $this->olvidarSesion();
        $this->deleteJson('/api/cuenta')->assertUnauthorized();
        $this->borrarCuenta($tokenSinRol)->assertForbidden();

        $this->assertFalse($usuario->fresh()->trashed());
        $this->assertFalse($sinRol->fresh()->trashed());
        $this->assertSame(2, PersonalAccessToken::count());
    }

    private function iniciarSesion(): TestResponse
    {
        $this->olvidarSesion();

        return $this->postJson('/api/sesion', ['correo' => self::CORREO, 'password' => UsuarioFactory::CONTRASENA]);
    }

    private function entrarConGoogle(): TestResponse
    {
        $this->olvidarSesion();

        return $this->postJson('/api/sesion/google', ['id_token' => $this->idTokenDeGoogle(['email' => self::CORREO])]);
    }

    private function consultarCuenta(string $token): TestResponse
    {
        $this->olvidarSesion();

        return $this->withToken($token)->getJson('/api/cuenta');
    }

    private function borrarCuenta(string $token): TestResponse
    {
        $this->olvidarSesion();

        return $this->withToken($token)->deleteJson('/api/cuenta');
    }

    /**
     * La aplicación de la prueba conserva la cuenta autenticada entre peticiones; un servidor real no.
     */
    private function olvidarSesion(): void
    {
        $this->app['auth']->forgetGuards();
        $this->withoutToken();
    }
}
