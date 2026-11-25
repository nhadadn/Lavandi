<?php

namespace Tests\Feature\Pago;

use App\Cobro\OrdenDePasarela;
use App\Cobro\PasarelaNoDisponible;
use App\Cobro\Pasarelas;
use App\Cobro\PasarelaSimulada;
use App\Models\Pago;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\Finder\Finder;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Pago\Soporte\CobrosDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * La pasarela y su llave salen de la configuración de cada entorno, nunca del código (ADR-0012; NSV-2026-001-v8.pdf:34:
 * ANEXO B, Sección B.11). En las pruebas automáticas, la pasarela es la simulada, con una llave de prueba de
 * phpunit.xml; la de Conekta, con las llaves de su sandbox, llega con la sección 3 de tasks.md.
 *
 * La pasarela simulada solo existe en local y en las pruebas: en otro entorno no se usa aunque tenga llave, para que un
 * aviso simulado nunca valga como pago (openspec/changes/pago/design.md, "Decisiones de la implementación").
 *
 * Escenario: pago-E17 · Spec: openspec/changes/pago/specs/pago/spec.md · Tarea 1.2
 */
class LlavesPorEntornoTest extends TestCase
{
    use AgendaDePrueba;
    use CobrosDePrueba;
    use LavanderiasDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    /** La llave de prueba que este archivo pone en la configuración, distinta de la de phpunit.xml. */
    private const OTRA_LLAVE = 'otra-llave-de-prueba-de-la-pasarela-simulada';

    /**
     * Formas de las llaves reales de las pasarelas, para que ninguna quede escrita a mano: la privada de Conekta
     * (key_…), las de Stripe (sk_ y pk_ de prueba o de producción) y los tokens de Mercado Pago (APP_USR-… y TEST-…).
     * Son una red mínima, como herramientas/ci/verificar_secretos.py.
     */
    private const FORMAS_DE_LLAVE = [
        '/\bkey_[A-Za-z0-9]{16,}\b/',
        '/\b[sp]k_(test|live)_[A-Za-z0-9]{10,}/',
        '/\bAPP_USR-[0-9a-f-]{20,}/i',
        '/\bTEST-[0-9a-f]{8,}-/i',
    ];

    /** Lo que se revisa: el código y la configuración de servidor/, sin las pruebas, lo de terceros ni lo generado. */
    private const CARPETAS_DEL_CODIGO = ['app', 'config', 'database', 'resources', 'routes'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();
    }

    #[TestDox('pago-E17: en el entorno de pruebas automáticas, la pasarela configurada es la simulada, con la llave de prueba de su configuración')]
    public function test_pago_e17_en_las_pruebas_la_pasarela_configurada_es_la_simulada_con_su_llave(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame(PasarelaSimulada::CODIGO, config('pasarela.pasarela'));
        $this->assertSame(Pago::PASARELA_SIMULADA, PasarelaSimulada::CODIGO);
        $this->assertNotSame('', (string) config('pasarela.simulada.llave'));
        $this->assertSame(env('PASARELA_SIMULADA_LLAVE'), config('pasarela.simulada.llave'));

        $this->assertInstanceOf(PasarelaSimulada::class, app(Pasarelas::class)->configurada());
        $this->assertSame(PasarelaSimulada::CODIGO, app(Pasarelas::class)->configurada()->codigo());
    }

    #[TestDox('pago-E17: al crear una orden en la pasarela simulada, la petición va autenticada con la llave de la configuración')]
    public function test_pago_e17_la_orden_va_autenticada_con_la_llave_de_la_configuracion(): void
    {
        config(['pasarela.simulada.llave' => self::OTRA_LLAVE]);
        $cobro = $this->cobroPendiente($this->pedidoPorPagar());

        $orden = app(Pasarelas::class)->configurada()->crearOrden($cobro);

        $this->assertInstanceOf(OrdenDePasarela::class, $orden);
        $this->assertNotSame('', $orden->id);

        $peticiones = $this->pasarelaSimulada()->peticiones();
        $this->assertCount(1, $peticiones);
        $this->assertSame('crear_orden', $peticiones[0]['operacion']);
        $this->assertSame('Bearer '.self::OTRA_LLAVE, $peticiones[0]['autorizacion']);
    }

    #[TestDox('pago-E17: sin llave en la configuración, la pasarela simulada no crea la orden: responde que el pago no está disponible')]
    public function test_pago_e17_sin_llave_la_pasarela_simulada_no_crea_la_orden(): void
    {
        config(['pasarela.simulada.llave' => null]);
        $cobro = $this->cobroPendiente($this->pedidoPorPagar());

        try {
            app(Pasarelas::class)->configurada()->crearOrden($cobro);
            $this->fail('Sin llave, la pasarela no debió crear la orden.');
        } catch (PasarelaNoDisponible $error) {
            $this->assertSame(503, $error->render()->getStatusCode());
            $this->assertSame(['message' => 'El pago no está disponible por ahora.'], $error->render()->getData(true));
        }

        $this->assertSame([], $this->pasarelaSimulada()->peticiones());
    }

    #[TestDox('pago-E17: sin pasarela en la configuración, o con una que no existe, no hay pasarela: responde que el pago no está disponible')]
    public function test_pago_e17_sin_pasarela_configurada_no_hay_pasarela(): void
    {
        foreach ([null, '', 'stripe', 'conekta-de-prueba'] as $codigo) {
            config(['pasarela.pasarela' => $codigo]);

            try {
                app(Pasarelas::class)->configurada();
                $this->fail('Sin una pasarela que exista, no debió haber pasarela.');
            } catch (PasarelaNoDisponible $error) {
                $this->assertSame(503, $error->render()->getStatusCode());
            }
        }
    }

    #[TestDox('pago-E17: la pasarela simulada solo existe en local y en las pruebas: en el entorno de prueba en internet y en producción no se usa, aunque tenga su llave')]
    public function test_pago_e17_la_pasarela_simulada_solo_existe_en_local_y_en_las_pruebas(): void
    {
        foreach (['production', 'staging', 'prueba'] as $entorno) {
            $this->app->detectEnvironment(fn (): string => $entorno);

            try {
                app(Pasarelas::class)->configurada();
                $this->fail("En {$entorno} no debió usarse la pasarela simulada.");
            } catch (PasarelaNoDisponible $error) {
                $this->assertSame(503, $error->render()->getStatusCode());
            }
        }

        $this->app->detectEnvironment(fn (): string => 'local');
        $this->assertInstanceOf(PasarelaSimulada::class, app(Pasarelas::class)->configurada());
    }

    #[TestDox('pago-E17: el código de servidor/ no contiene ninguna llave de la pasarela escrita a mano: la configuración la lee del entorno')]
    public function test_pago_e17_el_codigo_no_contiene_ninguna_llave_escrita_a_mano(): void
    {
        $configuracion = (string) file_get_contents(config_path('pasarela.php'));
        $this->assertStringContainsString("env('PASARELA')", $configuracion);
        $this->assertStringContainsString("env('PASARELA_SIMULADA_LLAVE')", $configuracion);

        $llaveDePrueba = (string) config('pasarela.simulada.llave');
        $this->assertNotSame('', $llaveDePrueba);

        $archivos = Finder::create()->files()->in(array_map(base_path(...), self::CARPETAS_DEL_CODIGO));
        $this->assertGreaterThan(0, $archivos->count());

        $hallazgos = [];

        foreach ($archivos as $archivo) {
            $texto = $archivo->getContents();

            if (str_contains($texto, $llaveDePrueba)) {
                $hallazgos[] = "{$archivo->getRelativePathname()}: la llave de prueba de la configuración";
            }

            foreach (self::FORMAS_DE_LLAVE as $forma) {
                if (preg_match($forma, $texto, $coincidencia)) {
                    $hallazgos[] = "{$archivo->getRelativePathname()}: {$coincidencia[0]}";
                }
            }
        }

        $this->assertSame([], $hallazgos);
    }
}
