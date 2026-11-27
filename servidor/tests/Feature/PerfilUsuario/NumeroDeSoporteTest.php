<?php

namespace Tests\Feature\PerfilUsuario;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\PerfilUsuario\Soporte\PerfilDePrueba;
use Tests\TestCase;

/**
 * El número de WhatsApp Business de soporte: vive en la configuración del servidor, con su variable de entorno, y la
 * app lo lee al abrir Soporte; vacío si no existe (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo A #16, "Chat de
 * WhatsApp Business"; decisión 27 de acta-2026-11-20-modelo-y-pasarela).
 *
 * El número real de EL CLIENTE es DD-082, abierta: las pruebas usan números ficticios, que no son de nadie.
 *
 * Escenarios: perfil-usuario-E09 y perfil-usuario-E10 · Spec:
 * openspec/changes/perfil-y-soporte/specs/perfil-usuario/spec.md · Tarea 1.3 de
 * openspec/changes/perfil-y-soporte/tasks.md
 */
class NumeroDeSoporteTest extends TestCase
{
    use PerfilDePrueba;
    use RefreshDatabase;

    /** Un número ficticio, en el formato internacional que pide el enlace de WhatsApp: país, 1 y diez dígitos. */
    private const NUMERO_DE_PRUEBA = '5210000000001';

    private const VARIABLE = 'SOPORTE_WHATSAPP';

    #[TestDox('perfil-usuario-E09: con un número en la configuración, la API se lo da a la app para abrir el chat')]
    public function test_perfil_usuario_e09_con_un_numero_en_la_configuracion_la_api_lo_da_a_la_app(): void
    {
        config(['soporte.whatsapp' => self::NUMERO_DE_PRUEBA]);

        $this->comoCuenta($this->cuentaDeUsuario())->getJson('/api/soporte')
            ->assertOk()
            ->assertExactJson(['whatsapp' => self::NUMERO_DE_PRUEBA]);
    }

    #[TestDox('perfil-usuario-E09: el número sale solo con sus dígitos, aunque la configuración lo escriba con +, espacios o guiones')]
    public function test_perfil_usuario_e09_el_numero_sale_solo_con_sus_digitos(): void
    {
        $cuenta = $this->cuentaDeUsuario();

        foreach (['+52 1 000 000 0001', '(52) 1-000-000-0001', ' 521 000 000 0001 ', "52\t1000000000\n1"] as $escrito) {
            config(['soporte.whatsapp' => $escrito]);

            $this->comoCuenta($cuenta)->getJson('/api/soporte')
                ->assertOk()
                ->assertExactJson(['whatsapp' => self::NUMERO_DE_PRUEBA]);
        }
    }

    #[TestDox('perfil-usuario-E10: sin número en la configuración, la API responde vacío y la app no abre un enlace')]
    public function test_perfil_usuario_e10_sin_numero_en_la_configuracion_la_api_responde_vacio(): void
    {
        $cuenta = $this->cuentaDeUsuario();

        foreach ([null, '', '   ', '+', '-- --', 'sin número'] as $escrito) {
            config(['soporte.whatsapp' => $escrito]);

            $this->comoCuenta($cuenta)->getJson('/api/soporte')
                ->assertOk()
                ->assertExactJson(['whatsapp' => null]);
        }
    }

    #[TestDox('perfil-usuario-E10: el número sale de la variable de entorno, vacío si no existe, y el ejemplo del entorno la trae vacía')]
    public function test_perfil_usuario_e10_el_numero_sale_de_la_variable_de_entorno_y_vacio_si_no_existe(): void
    {
        $this->assertSame(self::NUMERO_DE_PRUEBA, $this->configuracionCon(self::NUMERO_DE_PRUEBA)['whatsapp']);
        $this->assertNull($this->configuracionCon(null)['whatsapp']);

        $ejemplo = file(base_path('.env.example'), FILE_IGNORE_NEW_LINES);
        $this->assertContains(self::VARIABLE.'=', $ejemplo, 'El ejemplo del entorno trae la variable, vacía.');
    }

    #[TestDox('perfil-usuario-E09: sin sesión, o sin el rol de usuario, la API no da el número')]
    public function test_perfil_usuario_e09_sin_sesion_o_sin_el_rol_de_usuario_la_api_no_da_el_numero(): void
    {
        config(['soporte.whatsapp' => self::NUMERO_DE_PRUEBA]);

        $sinSesion = $this->getJson('/api/soporte')->assertUnauthorized();
        $sinRol = $this->comoCuenta(Usuario::factory()->create())->getJson('/api/soporte')->assertForbidden();

        foreach ([$sinSesion, $sinRol] as $respuesta) {
            $this->assertStringNotContainsString(self::NUMERO_DE_PRUEBA, $respuesta->getContent());
        }
    }

    /**
     * config/soporte.php leído con la variable de entorno en ese valor, o sin ella si es null. Deja el entorno como
     * estaba.
     *
     * @return array<string, mixed>
     */
    private function configuracionCon(?string $valor): array
    {
        $antes = [$_ENV[self::VARIABLE] ?? null, $_SERVER[self::VARIABLE] ?? null, getenv(self::VARIABLE)];

        try {
            if ($valor === null) {
                unset($_ENV[self::VARIABLE], $_SERVER[self::VARIABLE]);
                putenv(self::VARIABLE);
            } else {
                $_ENV[self::VARIABLE] = $_SERVER[self::VARIABLE] = $valor;
                putenv(self::VARIABLE.'='.$valor);
            }

            return require config_path('soporte.php');
        } finally {
            [$env, $server, $putenv] = $antes;
            unset($_ENV[self::VARIABLE], $_SERVER[self::VARIABLE]);
            putenv(self::VARIABLE);

            if ($env !== null) {
                $_ENV[self::VARIABLE] = $env;
            }
            if ($server !== null) {
                $_SERVER[self::VARIABLE] = $server;
            }
            if ($putenv !== false) {
                putenv(self::VARIABLE.'='.$putenv);
            }
        }
    }
}
