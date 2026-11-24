<?php

namespace Tests\Feature\Registro;

use App\Models\Rol;
use App\Models\Usuario;
use Database\Factories\UsuarioFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * El límite de intentos del inicio de sesión: 5 por minuto, por correo y por dirección, con el valor en configuración
 * (NSV-2026-001-v8.pdf:10:Cláusula X, medidas de seguridad técnicas razonables; ADR-0018, decisión 3; C-223).
 *
 * Escenario: registro-E30 · Spec: openspec/changes/registro-e-inicio-de-sesion/specs/registro/spec.md
 */
class LimiteDeIntentosTest extends TestCase
{
    use RefreshDatabase;

    /** El límite que prueban estas pruebas: el de ADR-0018. */
    private const INTENTOS_POR_MINUTO = 5;

    private const CORREO = 'persona@ejemplo.com';

    private const DEMASIADOS_INTENTOS = 'Demasiados intentos. Espera un minuto y vuelve a intentarlo.';

    protected function setUp(): void
    {
        parent::setUp();

        config(['acceso.intentos_por_minuto' => self::INTENTOS_POR_MINUTO]);
        Usuario::factory()->conRol(Rol::USUARIO)->create(['correo' => self::CORREO]);
    }

    #[TestDox('registro-E30: un intento más que el límite, con el mismo correo y desde la misma dirección, recibe demasiados intentos sin revisar la contraseña')]
    public function test_registro_e30_un_intento_mas_que_el_limite_recibe_demasiados_intentos_sin_revisar_la_contrasena(): void
    {
        for ($intento = 1; $intento <= self::INTENTOS_POR_MINUTO; $intento++) {
            $this->intentar(self::CORREO, 'otra-contrasena')->assertUnprocessable();
        }

        // Con la contraseña correcta: si la revisara, abriría sesión.
        $respuesta = $this->intentar(self::CORREO, UsuarioFactory::CONTRASENA);

        $respuesta->assertTooManyRequests();
        $respuesta->assertJsonPath('message', self::DEMASIADOS_INTENTOS);
        $respuesta->assertJsonMissingPath('token');
        $respuesta->assertHeader('Retry-After');
        $this->assertSame(0, PersonalAccessToken::count());
    }

    #[TestDox('registro-E30: los intentos que abren sesión también cuentan')]
    public function test_registro_e30_los_intentos_que_abren_sesion_tambien_cuentan(): void
    {
        for ($intento = 1; $intento <= self::INTENTOS_POR_MINUTO; $intento++) {
            $this->intentar(self::CORREO, UsuarioFactory::CONTRASENA)->assertOk();
        }

        $this->intentar(self::CORREO, UsuarioFactory::CONTRASENA)->assertTooManyRequests();
        $this->assertSame(self::INTENTOS_POR_MINUTO, PersonalAccessToken::count());
    }

    #[TestDox('registro-E30: pasado el minuto, vuelve a aceptar intentos')]
    public function test_registro_e30_pasado_el_minuto_vuelve_a_aceptar_intentos(): void
    {
        $this->agotarElLimite();
        $this->intentar(self::CORREO, UsuarioFactory::CONTRASENA)->assertTooManyRequests();

        $this->travel(61)->seconds();

        $this->intentar(self::CORREO, UsuarioFactory::CONTRASENA)->assertOk()->assertJsonStructure(['token']);
    }

    #[TestDox('registro-E30: el límite es por correo y por dirección: otro correo, u otra dirección, sigue entrando')]
    public function test_registro_e30_el_limite_es_por_correo_y_por_direccion(): void
    {
        Usuario::factory()->conRol(Rol::USUARIO)->create(['correo' => 'otra@ejemplo.com']);
        $this->agotarElLimite();

        $this->intentar('otra@ejemplo.com', UsuarioFactory::CONTRASENA)->assertOk();
        $this->intentar(self::CORREO, UsuarioFactory::CONTRASENA, '10.0.0.2')->assertOk();

        // El mismo correo con otras mayúsculas es el mismo correo.
        $this->intentar('Persona@Ejemplo.COM', UsuarioFactory::CONTRASENA)->assertTooManyRequests();
    }

    #[TestDox('registro-E08 y E30: un correo que no es texto no rompe el límite: responde con el error del correo y no abre sesión')]
    public function test_registro_e08_y_e30_un_correo_que_no_es_texto_no_rompe_el_limite_y_responde_con_el_error_del_correo(): void
    {
        $datos = ['correo' => [self::CORREO], 'password' => UsuarioFactory::CONTRASENA];

        // En JSON y como formulario (correo[]=…): las dos llegan al limitador como un arreglo.
        foreach ([$this->postJson('/api/sesion', $datos), $this->post('/api/sesion', $datos)] as $respuesta) {
            $respuesta->assertUnprocessable();
            $respuesta->assertJsonValidationErrors(['correo' => 'El correo no es válido.']);
            $respuesta->assertJsonMissingPath('token');
        }

        $this->assertSame(0, PersonalAccessToken::count());
    }

    #[TestDox('registro-E30: el límite sale de la configuración')]
    public function test_registro_e30_el_limite_sale_de_la_configuracion(): void
    {
        config(['acceso.intentos_por_minuto' => 2]);

        $this->intentar(self::CORREO, 'otra-contrasena')->assertUnprocessable();
        $this->intentar(self::CORREO, 'otra-contrasena')->assertUnprocessable();
        $this->intentar(self::CORREO, UsuarioFactory::CONTRASENA)->assertTooManyRequests();
    }

    #[TestDox('registro-E30: el límite vive en config/acceso.php como intentos_por_minuto')]
    public function test_registro_e30_el_limite_vive_en_la_configuracion_de_acceso(): void
    {
        $configuracion = require config_path('acceso.php');

        $this->assertArrayHasKey('intentos_por_minuto', $configuracion);
        $this->assertIsInt($configuracion['intentos_por_minuto']);
    }

    private function agotarElLimite(): void
    {
        for ($intento = 1; $intento <= self::INTENTOS_POR_MINUTO; $intento++) {
            $this->intentar(self::CORREO, 'otra-contrasena')->assertUnprocessable();
        }
    }

    private function intentar(string $correo, string $password, string $direccion = '127.0.0.1'): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withServerVariables(['REMOTE_ADDR' => $direccion])
            ->postJson('/api/sesion', ['correo' => $correo, 'password' => $password]);
    }
}
