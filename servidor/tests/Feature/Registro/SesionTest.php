<?php

namespace Tests\Feature\Registro;

use App\Models\Rol;
use App\Models\Usuario;
use Database\Factories\UsuarioFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * El inicio de sesión con correo y contraseña, la consulta de la propia cuenta y el cierre de sesión, con tokens de
 * Sanctum (NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #2; :20, Control de acceso; ADR-0005).
 *
 * Cada petición de una prueba olvida la cuenta de la anterior (forgetGuards), como dos peticiones reales al servidor.
 *
 * Escenarios: registro-E06, registro-E07, registro-E08, registro-E10 y registro-E12 · Spec:
 * openspec/changes/registro-e-inicio-de-sesion/specs/registro/spec.md
 */
class SesionTest extends TestCase
{
    use RefreshDatabase;

    private const CORREO = 'persona@ejemplo.com';

    private const CREDENCIALES_NO_VALIDAS = 'Las credenciales no son válidas.';

    #[TestDox('registro-E07: con su correo y su contraseña, el servidor responde con un token de sesión nuevo')]
    public function test_registro_e07_con_su_correo_y_su_contrasena_el_servidor_responde_con_un_token_de_sesion_nuevo(): void
    {
        $usuario = $this->cuenta();

        $primera = $this->iniciarSesion(self::CORREO, UsuarioFactory::CONTRASENA);
        $segunda = $this->iniciarSesion(self::CORREO, UsuarioFactory::CONTRASENA);

        foreach ([$primera, $segunda] as $respuesta) {
            $respuesta->assertOk();
            $respuesta->assertJsonStructure(['token', 'usuario' => ['id_publico', 'correo', 'rol']]);
            $respuesta->assertJsonPath('usuario.id_publico', $usuario->id_publico);
            $respuesta->assertJsonPath('usuario.correo', self::CORREO);
            $respuesta->assertJsonPath('usuario.rol', Rol::USUARIO);
        }

        // Cada inicio de sesión es un token nuevo, y los dos sirven.
        $this->assertNotSame($primera->json('token'), $segunda->json('token'));
        $this->assertSame(2, $usuario->tokens()->count());
        $this->consultarCuenta($primera->json('token'))->assertOk();
        $this->consultarCuenta($segunda->json('token'))->assertOk();
    }

    #[TestDox('registro-E07: el correo se reconoce sin distinguir mayúsculas')]
    public function test_registro_e07_el_correo_se_reconoce_sin_distinguir_mayusculas(): void
    {
        $usuario = $this->cuenta();

        $this->iniciarSesion('Persona@Ejemplo.COM', UsuarioFactory::CONTRASENA)
            ->assertOk()
            ->assertJsonPath('usuario.id_publico', $usuario->id_publico);
    }

    #[TestDox('registro-E08: con una contraseña distinta, el servidor no emite token y dice que las credenciales no son válidas')]
    public function test_registro_e08_con_una_contrasena_distinta_el_servidor_no_emite_token(): void
    {
        $this->cuenta();

        $respuesta = $this->iniciarSesion(self::CORREO, 'otra-contrasena');

        $this->assertCredencialesNoValidas($respuesta);
    }

    #[TestDox('registro-E08: con un correo que no tiene cuenta, la respuesta es la misma')]
    public function test_registro_e08_con_un_correo_que_no_tiene_cuenta_la_respuesta_es_la_misma(): void
    {
        $this->cuenta();

        $respuesta = $this->iniciarSesion('nadie@ejemplo.com', UsuarioFactory::CONTRASENA);

        $this->assertCredencialesNoValidas($respuesta);
    }

    #[TestDox('registro-E08: una cuenta sin contraseña, de Google o de Apple, no entra con contraseña')]
    public function test_registro_e08_una_cuenta_sin_contrasena_de_google_o_de_apple_no_entra_con_contrasena(): void
    {
        $this->cuenta(['password' => null]);

        $this->assertCredencialesNoValidas($this->iniciarSesion(self::CORREO, UsuarioFactory::CONTRASENA));
        $this->assertCredencialesNoValidas($this->iniciarSesion(self::CORREO, 'cualquier-contrasena'));
    }

    #[TestDox('registro-E08: una cuenta borrada no abre sesión')]
    public function test_registro_e08_una_cuenta_borrada_no_abre_sesion(): void
    {
        $this->cuenta()->delete();

        $this->assertCredencialesNoValidas($this->iniciarSesion(self::CORREO, UsuarioFactory::CONTRASENA));
    }

    #[TestDox('registro-E08: sin correo o sin contraseña, el servidor rechaza el inicio de sesión con el error de cada campo')]
    public function test_registro_e08_sin_correo_o_sin_contrasena_el_servidor_rechaza_con_el_error_de_cada_campo(): void
    {
        $this->postJson('/api/sesion', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['correo' => 'Escribe tu correo.', 'password' => 'Escribe tu contraseña.']);

        $this->assertSame(0, PersonalAccessToken::count());
    }

    #[TestDox('registro-E10: con su token, la cuenta recibe su correo y su rol')]
    public function test_registro_e10_con_su_token_la_cuenta_recibe_su_correo_y_su_rol(): void
    {
        $usuario = $this->cuenta();
        $token = $this->iniciarSesion(self::CORREO, UsuarioFactory::CONTRASENA)->json('token');

        $respuesta = $this->consultarCuenta($token);

        $respuesta->assertOk();
        $respuesta->assertExactJson([
            'usuario' => [
                'id_publico' => $usuario->id_publico,
                'correo' => self::CORREO,
                'rol' => Rol::USUARIO,
            ],
        ]);
    }

    #[TestDox('registro-E10: cada token ve solo su propia cuenta')]
    public function test_registro_e10_cada_token_ve_solo_su_propia_cuenta(): void
    {
        $una = $this->cuenta();
        $otra = $this->cuenta(['correo' => 'otra@ejemplo.com']);
        $tokenDeOtra = $this->iniciarSesion('otra@ejemplo.com', UsuarioFactory::CONTRASENA)->json('token');

        $respuesta = $this->consultarCuenta($tokenDeOtra);

        $respuesta->assertOk();
        $respuesta->assertJsonPath('usuario.id_publico', $otra->id_publico);
        $this->assertStringNotContainsString($una->id_publico, $respuesta->getContent());
        $this->assertStringNotContainsString(self::CORREO, $respuesta->getContent());
    }

    #[TestDox('registro-E10: sin token, o con un token que no existe, el servidor responde que no hay sesión')]
    public function test_registro_e10_sin_token_o_con_un_token_que_no_existe_el_servidor_responde_que_no_hay_sesion(): void
    {
        $this->cuenta();

        $this->getJson('/api/cuenta')->assertUnauthorized()->assertJsonMissingPath('usuario');
        $this->consultarCuenta('1|un-token-que-no-existe')->assertUnauthorized()->assertJsonMissingPath('usuario');
    }

    #[TestDox('registro-E10: sin el encabezado Accept, la API también responde en JSON que no hay sesión')]
    public function test_registro_e10_sin_el_encabezado_accept_la_api_tambien_responde_en_json_que_no_hay_sesion(): void
    {
        $respuesta = $this->get('/api/cuenta');

        $respuesta->assertUnauthorized();
        $this->assertStringContainsString('application/json', (string) $respuesta->headers->get('Content-Type'));
    }

    #[TestDox('registro-E06: el inicio de sesión y la consulta de la cuenta no incluyen la contraseña ni su hash')]
    public function test_registro_e06_el_inicio_de_sesion_y_la_consulta_de_la_cuenta_no_incluyen_la_contrasena_ni_su_hash(): void
    {
        $this->cuenta();
        $hash = DB::table('usuarios')->value('password');

        $sesion = $this->iniciarSesion(self::CORREO, UsuarioFactory::CONTRASENA);
        $cuenta = $this->consultarCuenta($sesion->json('token'));

        foreach ([$sesion, $cuenta] as $respuesta) {
            $respuesta->assertOk();
            $respuesta->assertJsonMissingPath('usuario.password');
            $this->assertStringNotContainsString(UsuarioFactory::CONTRASENA, $respuesta->getContent());
            $this->assertStringNotContainsString($hash, $respuesta->getContent());
            $this->assertStringNotContainsString('password', $respuesta->getContent());
        }
    }

    #[TestDox('registro-E12: cerrar la sesión revoca el token de esa sesión, y solo ese')]
    public function test_registro_e12_cerrar_la_sesion_revoca_el_token_de_esa_sesion_y_solo_ese(): void
    {
        $usuario = $this->cuenta();
        $telefono = $this->iniciarSesion(self::CORREO, UsuarioFactory::CONTRASENA)->json('token');
        $otroTelefono = $this->iniciarSesion(self::CORREO, UsuarioFactory::CONTRASENA)->json('token');

        $this->olvidarSesion();
        $this->withToken($telefono)->deleteJson('/api/sesion')->assertNoContent();

        $this->consultarCuenta($telefono)->assertUnauthorized();
        $this->assertSame(1, $usuario->tokens()->count());
        $this->consultarCuenta($otroTelefono)->assertOk();
    }

    #[TestDox('registro-E12: sin token, cerrar la sesión responde que no hay sesión')]
    public function test_registro_e12_sin_token_cerrar_la_sesion_responde_que_no_hay_sesion(): void
    {
        $this->deleteJson('/api/sesion')->assertUnauthorized();
    }

    /**
     * La cuenta de prueba: rol de usuario, el correo de esta prueba y la contraseña de la fábrica.
     *
     * @param  array<string, mixed>  $atributos
     */
    private function cuenta(array $atributos = []): Usuario
    {
        return Usuario::factory()->conRol(Rol::USUARIO)->create(['correo' => self::CORREO, ...$atributos]);
    }

    private function iniciarSesion(string $correo, string $password): TestResponse
    {
        $this->olvidarSesion();

        return $this->postJson('/api/sesion', ['correo' => $correo, 'password' => $password]);
    }

    private function consultarCuenta(string $token): TestResponse
    {
        $this->olvidarSesion();

        return $this->withToken($token)->getJson('/api/cuenta');
    }

    /**
     * La aplicación de la prueba conserva la cuenta autenticada entre peticiones; un servidor real no.
     */
    private function olvidarSesion(): void
    {
        $this->app['auth']->forgetGuards();
        $this->withoutToken();
    }

    private function assertCredencialesNoValidas(TestResponse $respuesta): void
    {
        $respuesta->assertUnprocessable();
        $respuesta->assertJsonValidationErrors(['correo' => self::CREDENCIALES_NO_VALIDAS]);
        $respuesta->assertJsonMissingValidationErrors(['password']);
        $respuesta->assertJsonMissingPath('token');
        $this->assertSame(0, PersonalAccessToken::count());
    }
}
