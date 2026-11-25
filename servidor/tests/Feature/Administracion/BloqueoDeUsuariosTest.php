<?php

namespace Tests\Feature\Administracion;

use App\Autorizacion\Autorizacion;
use App\Filament\Resources\Usuarios\Pages\ListUsuarios;
use App\Filament\Resources\Usuarios\Pages\ViewUsuario;
use App\Models\CuentaExterna;
use App\Models\Rol;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Database\Factories\UsuarioFactory;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Registro\Soporte\ImitaAGoogle;
use Tests\TestCase;

/**
 * El bloqueo de un usuario desde el panel y su levantamiento (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #3,
 * "historial y bloqueo"; design.md, lectura 3: "bloqueo" incluye levantarlo).
 *
 * Al bloquearla, la cuenta queda en usuarios.estado = 'bloqueado' con su bloqueado_en, y se revocan todos sus tokens de
 * Sanctum: la siguiente petición de la app con la sesión que tenía abierta se rechaza. Mientras siga bloqueada no abre
 * sesión con correo y contraseña ni con Google: lo decide el componente de autorización
 * (Autorizacion::puedeIniciarSesion()), que usará también la entrada con Apple cuando exista (DD-069). Al levantar el
 * bloqueo vuelve a activo y puede entrar otra vez.
 *
 * Lo que pasa con un pedido activo de la cuenta bloqueada es DD-016 (administracion-E05): el bloqueo no toca pedidos.
 *
 * La Google de prueba firma con sus propias llaves (Registro/Soporte/ImitaAGoogle). Cada petición a la API olvida la
 * cuenta de la anterior, también la sesión web del panel, como dos peticiones reales al servidor.
 *
 * Escenarios: administracion-E03 y administracion-E04 · Spec: openspec/changes/administracion/specs/administracion/
 * spec.md · Tarea 1.3
 */
class BloqueoDeUsuariosTest extends TestCase
{
    use ImitaAGoogle;
    use RefreshDatabase;

    private const CORREO = 'persona@ejemplo.com';

    private const BLOQUEADA = 'Esta cuenta está bloqueada.';

    private const CREDENCIALES_NO_VALIDAS = 'Las credenciales no son válidas.';

    private Usuario $administracion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->imitarAGoogle();
        $this->administracion = Usuario::factory()->conRol(Rol::ADMINISTRACION)->create();
    }

    #[TestDox('administracion-E03: con la sesión abierta en la app, al bloquearlo desde el panel la siguiente petición con esa sesión se rechaza, en todos sus teléfonos')]
    public function test_administracion_e03_al_bloquearlo_la_siguiente_peticion_con_su_sesion_se_rechaza(): void
    {
        $cuenta = $this->cuentaDeUsuario();
        $sesion = $this->iniciarSesion()->assertOk()->json('token');
        $otroTelefono = $this->iniciarSesion()->assertOk()->json('token');
        $this->consultarCuenta($sesion)->assertOk();

        $this->travelTo(CarbonImmutable::parse('2026-12-03 15:00:00', 'UTC'));
        $this->bloquearDesdeLaLista($cuenta);

        $this->consultarCuenta($sesion)->assertUnauthorized();
        $this->consultarCuenta($otroTelefono)->assertUnauthorized();

        $cuenta->refresh();
        $this->assertSame(Usuario::BLOQUEADO, $cuenta->estado);
        $this->assertSame('2026-12-03T15:00:00Z', $cuenta->bloqueado_en->utc()->toIso8601ZuluString());
        $this->assertSame(0, $cuenta->tokens()->count());
    }

    #[TestDox('administracion-E03: el usuario bloqueado no inicia sesión con correo y contraseña: la respuesta dice que la cuenta está bloqueada y no trae token; con otra contraseña, la de siempre')]
    public function test_administracion_e03_el_bloqueado_no_inicia_sesion_con_correo_y_contrasena(): void
    {
        $cuenta = $this->cuentaDeUsuario();
        $this->bloquearDesdeLaLista($cuenta);

        $this->iniciarSesion()
            ->assertUnprocessable()
            ->assertJsonPath('errors.correo', [self::BLOQUEADA])
            ->assertJsonMissingPath('token');

        // Sin la contraseña correcta, la respuesta no dice que la cuenta existe ni que está bloqueada.
        $this->iniciarSesion('otra-contrasena')
            ->assertUnprocessable()
            ->assertJsonPath('errors.correo', [self::CREDENCIALES_NO_VALIDAS]);

        $this->assertSame(0, PersonalAccessToken::count());
    }

    #[TestDox('administracion-E03: el usuario bloqueado no entra con Google, ni con su cuenta de Google ya ligada ni ligándola a su correo, y su cuenta no cambia')]
    public function test_administracion_e03_el_bloqueado_no_entra_con_google(): void
    {
        // Una cuenta que nació con Google, ya ligada.
        $this->entrarConGoogle($this->idTokenDeGoogle())->assertOk();
        $deGoogle = Usuario::conCorreo(self::CORREO)->sole();
        $this->bloquearDesdeLaLista($deGoogle);

        $this->entrarConGoogle($this->idTokenDeGoogle())
            ->assertUnprocessable()
            ->assertJsonPath('errors.id_token', [self::BLOQUEADA])
            ->assertJsonMissingPath('token');

        // Una cuenta con contraseña, sin ligar: entrar con Google no la liga, ni le anula la contraseña.
        $conContrasena = $this->cuentaDeUsuario('otra@ejemplo.com');
        $this->bloquearDesdeLaLista($conContrasena);

        $this->entrarConGoogle($this->idTokenDeGoogle(['sub' => '220000000000000000001', 'email' => 'otra@ejemplo.com']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.id_token', [self::BLOQUEADA])
            ->assertJsonMissingPath('token');

        $this->assertSame(1, CuentaExterna::count());
        $this->assertTrue(Hash::check(UsuarioFactory::CONTRASENA, $conContrasena->fresh()->password));
        $this->assertSame(0, PersonalAccessToken::count());
    }

    #[TestDox('administracion-E03: el componente de autorización no abre sesión a una cuenta bloqueada por ninguna vía, y si le quedara un token, ninguna ruta lo deja pasar; tampoco entra al panel')]
    public function test_administracion_e03_el_componente_rechaza_a_la_cuenta_bloqueada(): void
    {
        $autorizacion = app(Autorizacion::class);
        $cuenta = $this->cuentaDeUsuario();
        $this->assertTrue($autorizacion->puedeIniciarSesion($cuenta));

        $this->bloquearDesdeLaLista($cuenta);
        $cuenta->refresh();

        // La vía que llegue después, como la entrada con Apple (DD-069), pregunta aquí.
        $this->assertFalse($autorizacion->puedeIniciarSesion($cuenta));
        $this->assertFalse($autorizacion->permite($cuenta, Rol::USUARIO));

        // Un token que se emitiera por fuera de la entrada no pasa el componente.
        $this->consultarCuenta($cuenta->createToken('app')->plainTextToken)
            ->assertForbidden()
            ->assertJsonMissingPath('usuario');

        // Una cuenta de administración bloqueada tampoco entra al panel.
        $bloqueada = Usuario::factory()->conRol(Rol::ADMINISTRACION)->create();
        $bloqueada->forceFill(['estado' => Usuario::BLOQUEADO])->save();
        $this->assertFalse($autorizacion->puedeEntrarAlPanel($bloqueada));
        $this->actingAs($bloqueada, 'web')->get('/admin')->assertForbidden();
    }

    #[TestDox('administracion-E03 y E04: el botón de bloquear aparece en la cuenta activa y el de levantar el bloqueo en la bloqueada, en la lista y en la ficha')]
    public function test_administracion_e03_y_e04_cada_boton_aparece_cuando_se_puede(): void
    {
        $activa = $this->cuentaDeUsuario();
        $bloqueada = $this->cuentaDeUsuario('otra@ejemplo.com');
        $bloqueada->forceFill(['estado' => Usuario::BLOQUEADO, 'bloqueado_en' => now()])->save();
        $this->comoAdministracion();

        Livewire::test(ListUsuarios::class)
            ->assertTableActionVisible('bloquear', $activa)
            ->assertTableActionHidden('levantarBloqueo', $activa)
            ->assertTableActionHidden('bloquear', $bloqueada)
            ->assertTableActionVisible('levantarBloqueo', $bloqueada)
            ->assertTableColumnFormattedStateSet('estado', 'Bloqueado', $bloqueada);

        Livewire::test(ViewUsuario::class, ['record' => $activa->getRouteKey()])
            ->assertActionVisible('bloquear')
            ->assertActionHidden('levantarBloqueo')
            ->callAction('bloquear')
            ->assertActionHidden('bloquear')
            ->assertActionVisible('levantarBloqueo');

        $this->assertSame(Usuario::BLOQUEADO, $activa->fresh()->estado);
    }

    #[TestDox('administracion-E03: solo la administración bloquea, y solo cuentas de usuario: no las de las lavanderías ni las de la administración')]
    public function test_administracion_e03_solo_la_administracion_bloquea_cuentas_de_usuario(): void
    {
        $autorizacion = app(Autorizacion::class);
        $cuenta = $this->cuentaDeUsuario();

        $this->assertTrue($autorizacion->puedeBloquear($this->administracion, $cuenta));

        foreach ([Rol::USUARIO, Rol::LAVANDERIA, 'repartidor'] as $rol) {
            $otra = Usuario::factory()->conRol($rol)->create();

            $this->assertFalse($autorizacion->puedeBloquear($otra, $cuenta), $rol);
            $this->actingAs($otra, 'web')->get("/admin/usuarios/{$cuenta->id_publico}")->assertForbidden();
            $this->app['auth']->forgetGuards();
        }

        foreach ([Rol::LAVANDERIA, Rol::ADMINISTRACION] as $rol) {
            $this->assertFalse($autorizacion->puedeBloquear($this->administracion, Usuario::factory()->conRol($rol)->create()), $rol);
        }

        $this->assertSame(Usuario::ACTIVO, $cuenta->fresh()->estado);
    }

    #[TestDox('administracion-E04: al levantar el bloqueo, el usuario vuelve a activo y puede iniciar sesión otra vez con correo y contraseña y con Google')]
    public function test_administracion_e04_al_levantar_el_bloqueo_puede_iniciar_sesion_otra_vez(): void
    {
        $cuenta = $this->cuentaDeUsuario();
        $this->bloquearDesdeLaLista($cuenta);
        $this->iniciarSesion()->assertUnprocessable();

        $this->comoAdministracion();
        Livewire::test(ListUsuarios::class)
            ->callTableAction('levantarBloqueo', $cuenta)
            ->assertHasNoTableActionErrors();

        $cuenta->refresh();
        $this->assertSame(Usuario::ACTIVO, $cuenta->estado);
        $this->assertNull($cuenta->bloqueado_en);

        $sesion = $this->iniciarSesion()->assertOk()->json('token');
        $this->consultarCuenta($sesion)->assertOk()->assertJsonPath('usuario.id_publico', $cuenta->id_publico);
        $this->entrarConGoogle($this->idTokenDeGoogle())->assertOk()->assertJsonPath('usuario.id_publico', $cuenta->id_publico);
    }

    private function cuentaDeUsuario(string $correo = self::CORREO): Usuario
    {
        return Usuario::factory()->conRol(Rol::USUARIO)->create(['correo' => $correo]);
    }

    private function comoAdministracion(): void
    {
        $this->app['auth']->forgetGuards();
        Filament::setCurrentPanel('admin');
        $this->actingAs($this->administracion, 'web');
    }

    /**
     * La administración bloquea la cuenta con el botón de la lista de usuarios del panel.
     */
    private function bloquearDesdeLaLista(Usuario $cuenta): void
    {
        $this->comoAdministracion();

        Livewire::test(ListUsuarios::class)
            ->callTableAction('bloquear', $cuenta)
            ->assertHasNoTableActionErrors();
    }

    private function iniciarSesion(string $password = UsuarioFactory::CONTRASENA): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withoutToken()->postJson('/api/sesion', ['correo' => self::CORREO, 'password' => $password]);
    }

    private function entrarConGoogle(string $idToken): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withoutToken()->postJson('/api/sesion/google', ['id_token' => $idToken]);
    }

    private function consultarCuenta(string $token): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->getJson('/api/cuenta');
    }
}
