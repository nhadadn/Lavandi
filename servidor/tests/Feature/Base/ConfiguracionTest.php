<?php

namespace Tests\Feature\Base;

use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Los secretos, el nombre del producto y la dirección salen de la configuración del entorno.
 *
 * Escenarios: base-E08 y base-E10 · Spec: openspec/changes/base-tecnica/specs/base/spec.md
 */
class ConfiguracionTest extends TestCase
{
    /** Nombres de variable que guardan una contraseña, una llave o un token. */
    private const NOMBRE_DE_SECRETO = '/KEY|PASS|SECRET|TOKEN|CREDENTIAL/i';

    private const NOMBRE_DE_PRUEBA = 'Producto de prueba';

    private const DIRECCION_DE_PRUEBA = 'https://servidor.prueba.test';

    #[TestDox('base-E08: el servidor no versiona ningún .env, salvo el ejemplo')]
    public function test_base_e08_el_servidor_no_versiona_ningun_env_salvo_el_ejemplo(): void
    {
        $proceso = Process::path(base_path())->run(['git', 'ls-files', '-z', '--', '.']);
        $this->assertTrue($proceso->successful(), $proceso->errorOutput());

        $entornos = array_values(array_filter(
            explode("\0", $proceso->output()),
            fn (string $ruta): bool => str_starts_with(basename($ruta), '.env'),
        ));

        $this->assertSame(['.env.example'], $entornos);
    }

    #[TestDox('base-E08: el ejemplo del entorno no trae secretos')]
    public function test_base_e08_el_ejemplo_del_entorno_no_trae_secretos(): void
    {
        $variables = $this->variablesDelEjemplo();

        $this->assertArrayHasKey('APP_KEY', $variables);
        $this->assertSame('', $variables['APP_KEY'], 'APP_KEY no lleva valor en .env.example.');

        foreach ($variables as $nombre => $valor) {
            if (! preg_match(self::NOMBRE_DE_SECRETO, $nombre)) {
                continue;
            }

            // Solo se permite vacío, o una referencia a otra variable, como "${PUSHER_APP_KEY}".
            $this->assertMatchesRegularExpression(
                '/^(|\$\{[A-Z0-9_]+\})$/',
                $valor,
                "{$nombre} trae valor en .env.example.",
            );
        }
    }

    #[TestDox('base-E10: el título del inicio de sesión lleva el nombre de la configuración')]
    public function test_base_e10_el_titulo_del_inicio_de_sesion_lleva_el_nombre_de_la_configuracion(): void
    {
        config(['app.name' => self::NOMBRE_DE_PRUEBA]);

        $html = (string) $this->get('/admin/login')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('~<title>(.*?)</title>~s', $html);
        preg_match('~<title>(.*?)</title>~s', $html, $titulo);
        $this->assertStringContainsString(self::NOMBRE_DE_PRUEBA, $titulo[1]);
    }

    #[TestDox('base-E10: los enlaces absolutos usan la dirección de la configuración')]
    public function test_base_e10_los_enlaces_absolutos_usan_la_direccion_de_la_configuracion(): void
    {
        config(['app.url' => self::DIRECCION_DE_PRUEBA]);

        // La consulta llega por otra dirección, como pasa con un túnel o un proxy.
        $this->get('http://otra-direccion.test/admin')
            ->assertRedirect(self::DIRECCION_DE_PRUEBA.'/admin/login');

        $html = (string) $this->get('http://otra-direccion.test/admin/login')->assertOk()->getContent();

        preg_match_all('~(?:href|src|action)="(https?://[^"]+)"~', $html, $enlaces);
        $this->assertNotEmpty($enlaces[1], 'La pantalla debe traer enlaces absolutos para revisarlos.');

        foreach ($enlaces[1] as $enlace) {
            $this->assertStringStartsWith(self::DIRECCION_DE_PRUEBA.'/', $enlace);
        }
    }

    /**
     * Las variables de .env.example, incluidas las comentadas, con su valor sin comillas.
     *
     * @return array<string, string>
     */
    private function variablesDelEjemplo(): array
    {
        $variables = [];

        foreach (file(base_path('.env.example'), FILE_IGNORE_NEW_LINES) as $linea) {
            if (preg_match('/^\s*#?\s*([A-Z0-9_]+)\s*=(.*)$/', $linea, $partes)) {
                $variables[$partes[1]] = trim(trim($partes[2]), '"\'');
            }
        }

        return $variables;
    }
}
