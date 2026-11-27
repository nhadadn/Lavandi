<?php

namespace Tests\Feature\Registro;

use App\Http\Controllers\Api\SesionController;
use App\Models\Rol;
use App\Models\Usuario;
use Database\Factories\UsuarioFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\AdministracionEnLaApp\Soporte\SesionesDeLaApp;
use Tests\TestCase;

/**
 * La cuenta y el cierre de la sesión son de toda cuenta con un rol de la app, no solo del usuario: con el rol, la app
 * sabe a qué módulo entra (ANEXO A, Sección A.3; NSV-2026-001-v8.pdf:3:Cláusula I). La administración también, con su
 * token de administración: C-247 y la decisión 69 del acta del 2026-11-20 reemplazan la decisión 31, que la dejaba
 * fuera (ADR-0022, decisiones 1, 6 y 8). Un token suyo sin la habilidad no las abre. Del catálogo del territorio, la
 * administración lee los municipios, para cambiar el de una lavandería (administracion-app-E14; adenda del 2026-11-26 de
 * ADR-0022, decisión 6); las colonias de un código postal siguen con los tres roles de siempre.
 *
 * Los tokens son los reales, de App\Identidad\TokensDeLaApp, y van con withToken().
 *
 * Escenarios: registro-E33, administracion-app-E06 y administracion-app-E09 · Spec:
 * openspec/changes/administracion-en-la-app/specs/registro/spec.md y
 * openspec/changes/administracion-en-la-app/specs/administracion-en-la-app/spec.md · Tareas 1.3 y 1.5
 */
class CuentaDeLosRolesDeLaAppTest extends TestCase
{
    use RefreshDatabase;
    use SesionesDeLaApp;

    #[TestDox('registro-E33: una cuenta de lavandería consulta su cuenta, con su rol')]
    public function test_registro_e33_una_cuenta_de_lavanderia_consulta_su_cuenta_con_su_rol(): void
    {
        $lavanderia = Usuario::factory()->conRol(Rol::LAVANDERIA)->create(['correo' => 'socio@ejemplo.com']);

        $this->llamarConToken($this->tokenDeLaApp($lavanderia), 'GET', '/api/cuenta')
            ->assertOk()
            ->assertJsonPath('usuario.correo', 'socio@ejemplo.com')
            ->assertJsonPath('usuario.rol', Rol::LAVANDERIA);
    }

    #[TestDox('registro-E33: una cuenta de lavandería cierra su sesión y su token deja de servir')]
    public function test_registro_e33_una_cuenta_de_lavanderia_cierra_su_sesion(): void
    {
        $lavanderia = Usuario::factory()->conRol(Rol::LAVANDERIA)->create();
        $token = $this->tokenDeLaApp($lavanderia);

        $this->llamarConToken($token, 'DELETE', '/api/sesion')->assertNoContent();

        $this->assertSame(0, $lavanderia->tokens()->count());
        $this->llamarConToken($token, 'GET', '/api/cuenta')->assertUnauthorized();
    }

    #[TestDox('registro-E33: una cuenta de lavandería inicia sesión en la app y recibe su token y su rol')]
    public function test_registro_e33_la_lavanderia_inicia_sesion_en_la_app(): void
    {
        Usuario::factory()->conRol(Rol::LAVANDERIA)->create(['correo' => 'socio@ejemplo.com']);

        $this->postJson('/api/sesion', ['correo' => 'socio@ejemplo.com', 'password' => UsuarioFactory::CONTRASENA])
            ->assertOk()
            ->assertJsonPath('usuario.rol', Rol::LAVANDERIA)
            ->assertJsonStructure(['token']);
    }

    #[TestDox('registro-E33: una cuenta de administración inicia sesión en la app con su correo y su contraseña y recibe su token de administración (administracion-app-E01)')]
    public function test_registro_e33_la_administracion_inicia_sesion_en_la_app_y_recibe_su_token(): void
    {
        $administracion = $this->cuentaDeAdministracion(['correo' => 'duenio@ejemplo.com']);

        $this->postJson('/api/sesion', ['correo' => 'duenio@ejemplo.com', 'password' => UsuarioFactory::CONTRASENA])
            ->assertOk()
            ->assertJsonStructure(['token'])
            ->assertJsonPath('usuario.rol', Rol::ADMINISTRACION);

        $this->assertSame(['administracion'], $administracion->tokens()->sole()->abilities);
    }

    #[TestDox('registro-E33: con su token de administración, la consulta de su cuenta trae su correo y su rol, administracion, y el cierre de la sesión lo revoca (administracion-app-E04)')]
    public function test_registro_e33_la_administracion_consulta_su_cuenta_y_cierra_su_sesion(): void
    {
        $administracion = $this->cuentaDeAdministracion(['correo' => 'duenio@ejemplo.com']);
        $token = $this->tokenDeLaApp($administracion);

        $this->llamarConToken($token, 'GET', '/api/cuenta')
            ->assertOk()
            ->assertJsonPath('usuario.correo', 'duenio@ejemplo.com')
            ->assertJsonPath('usuario.rol', Rol::ADMINISTRACION);

        $this->llamarConToken($token, 'DELETE', '/api/sesion')->assertNoContent();

        $this->assertSame(0, $administracion->tokens()->count());
        $this->llamarConToken($token, 'GET', '/api/cuenta')->assertUnauthorized();
    }

    #[TestDox('registro-E33 y administracion-app-E06: un token de una cuenta de administración sin la habilidad administracion recibe 403 en la consulta de la cuenta y en el cierre de la sesión, sin datos, y el token sigue ahí')]
    public function test_registro_e33_y_administracion_app_e06_un_token_sin_la_habilidad_no_abre_la_cuenta_ni_la_sesion(): void
    {
        $administracion = $this->cuentaDeAdministracion();
        $token = $this->tokenSinLaHabilidad($administracion);

        $this->llamarConToken($token, 'GET', '/api/cuenta')
            ->assertForbidden()
            ->assertExactJson(self::RESPUESTA_SIN_ACCESO);
        $this->llamarConToken($token, 'DELETE', '/api/sesion')
            ->assertForbidden()
            ->assertExactJson(self::RESPUESTA_SIN_ACCESO);

        $this->assertSame(1, $administracion->tokens()->count());
    }

    #[TestDox('registro-E33: el catálogo de municipios lo leen los cuatro roles, también la administración con su token; las colonias de un código postal siguen solo para el usuario, la lavandería y el repartidor: la administración recibe 403 sin datos')]
    public function test_registro_e33_la_administracion_lee_los_municipios_y_no_las_colonias(): void
    {
        $administracion = $this->cuentaDeAdministracion();
        $token = $this->tokenDeLaApp($administracion);

        $this->llamarConToken($token, 'GET', '/api/catalogos/municipios')->assertOk()->assertJsonStructure(['municipios']);
        $this->llamarConToken($token, 'GET', '/api/codigos-postales/34000/colonias')
            ->assertForbidden()
            ->assertExactJson(self::RESPUESTA_SIN_ACCESO);

        foreach (self::ROLES_QUE_NO_SON_DE_ADMINISTRACION as $rol) {
            $deOtroRol = $this->tokenDeLaApp($this->cuentaQueOperaComo($rol));

            $this->llamarConToken($deOtroRol, 'GET', '/api/catalogos/municipios')->assertOk();
            $this->llamarConToken($deOtroRol, 'GET', '/api/codigos-postales/34000/colonias')->assertOk();
        }

        // Un token de administración sin la habilidad tampoco abre el catálogo (administracion-app-E06).
        $this->llamarConToken($this->tokenSinLaHabilidad($administracion), 'GET', '/api/catalogos/municipios')
            ->assertForbidden()
            ->assertExactJson(self::RESPUESTA_SIN_ACCESO);
    }

    #[TestDox('administracion-app-E09: a una cuenta sin ningún rol de la app, POST /api/sesion le responde 403 sin token, con un mensaje que no menciona el panel')]
    public function test_administracion_app_e09_una_cuenta_sin_rol_de_la_app_no_recibe_token(): void
    {
        $sinRol = Usuario::factory()->create(['correo' => 'sin.rol@ejemplo.com']);

        $respuesta = $this->llamarConToken(null, 'POST', '/api/sesion', [
            'correo' => 'sin.rol@ejemplo.com',
            'password' => UsuarioFactory::CONTRASENA,
        ]);

        $respuesta->assertForbidden()
            ->assertJsonMissingPath('token')
            ->assertExactJson(['message' => SesionController::SIN_ACCESO_A_LA_APP]);
        $this->assertSame('Esta cuenta no tiene acceso a la app.', $respuesta->json('message'));
        $this->assertStringNotContainsStringIgnoringCase('panel', $respuesta->json('message'));
        $this->assertSame(0, $sinRol->tokens()->count());
    }
}
