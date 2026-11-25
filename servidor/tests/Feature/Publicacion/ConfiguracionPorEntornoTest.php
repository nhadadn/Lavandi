<?php

namespace Tests\Feature\Publicacion;

use App\Avisos\Fcm\CanalDeFcm;
use App\Cobro\PasarelaNoDisponible;
use App\Cobro\Pasarelas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\Finder\Finder;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\Avisos\Soporte\FcmSimulado;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Pago\Soporte\CobrosDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\Feature\Publicacion\Soporte\VariablesDelEntorno;
use Tests\TestCase;

/**
 * Cada entorno con sus credenciales: la pasarela, el proyecto de Firebase, las llaves de Google y el correo salen de las
 * variables del entorno, nunca del código, así que prueba usa las de prueba y producción las de producción, en cuentas a
 * nombre de EL CLIENTE (NSV-2026-001-v8.pdf:7:Cláusula IV; NSV-2026-001-v8.pdf:34:ANEXO B, Sección B.11; ADR-0012).
 *
 * Cada prueba simula el .env de un entorno con Soporte\VariablesDelEntorno: pone las variables en el entorno del proceso,
 * como el hosting, y vuelve a leer el archivo de configuración. Las cuentas de servicio de Firebase son de prueba, con una
 * llave que se genera al correr (Tests\Feature\Avisos\Soporte\FcmSimulado); ninguna petición sale a la red.
 *
 * La pasarela del contrato es Conekta (ADR-0010; DD-079 si se firma el cambio). Su implementación es la sección 3 de
 * openspec/changes/pago/tasks.md: aquí se prueba con la pasarela simulada el mecanismo que va a usar, y la revisión del
 * código ya rechaza cualquier llave de Conekta escrita a mano.
 *
 * Escenario: publicacion-E01 · Spec: openspec/changes/publicacion/specs/publicacion/spec.md · Tarea 1.1
 */
class ConfiguracionPorEntornoTest extends TestCase
{
    use AgendaDePrueba;
    use CobrosDePrueba;
    use FcmSimulado;
    use LavanderiasDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;
    use VariablesDelEntorno;

    /**
     * Formas de las credenciales reales, para que ninguna quede escrita a mano: las llaves de las API de Google, los
     * clientes de OAuth de Google, las llaves privadas (como la de una cuenta de servicio de Firebase) y la llave privada
     * de Conekta. Son una red mínima, como herramientas/ci/verificar_secretos.py.
     */
    private const FORMAS_DE_CREDENCIAL = [
        'llave de una API de Google' => '/AIza[0-9A-Za-z_\-]{35}/',
        'cliente de OAuth de Google' => '/\d{6,}-[0-9a-z]{20,}\.apps\.googleusercontent\.com/',
        'llave privada' => '/-----BEGIN [A-Z ]*PRIVATE KEY-----/',
        'llave privada de una cuenta de servicio' => '/"private_key"\s*:\s*"/',
        'llave privada de Conekta' => '/\bkey_[A-Za-z0-9]{16,}\b/',
    ];

    /** Lo que se revisa: el código y la configuración de servidor/, sin las pruebas, lo de terceros ni lo generado. */
    private const CARPETAS_DEL_CODIGO = ['app', 'config', 'database', 'resources', 'routes'];

    /**
     * Cada credencial, por el archivo de configuración y la clave donde vive, con la variable del entorno que la da.
     */
    private const CREDENCIALES = [
        ['pasarela', 'pasarela', 'PASARELA'],
        ['pasarela', 'simulada.llave', 'PASARELA_SIMULADA_LLAVE'],
        ['pasarela', 'conekta.llave', 'PASARELA_CONEKTA_LLAVE'],
        ['avisos', 'fcm.credenciales', 'FCM_CREDENCIALES'],
        ['services', 'geocodificacion.llave', 'GOOGLE_GEOCODIFICACION_LLAVE'],
        ['services', 'google_maps_panel.llave', 'GOOGLE_MAPS_PANEL_LLAVE'],
        ['mail', 'default', 'MAIL_MAILER'],
        ['mail', 'mailers.smtp.host', 'MAIL_HOST'],
        ['mail', 'mailers.smtp.username', 'MAIL_USERNAME'],
        ['mail', 'mailers.smtp.password', 'MAIL_PASSWORD'],
        ['mail', 'from.address', 'MAIL_FROM_ADDRESS'],
    ];

    #[TestDox('publicacion-E01: al mandar un aviso, el servidor usa el proyecto de Firebase del entorno: con la configuración de prueba, el de prueba; con la de producción, el de producción')]
    public function test_publicacion_e01_cada_entorno_manda_los_avisos_con_su_proyecto_de_firebase(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            self::DIRECCION_DE_TOKENS => Http::response(['access_token' => self::TOKEN_DE_ACCESO, 'expires_in' => 3599]),
            'https://fcm.googleapis.com/v1/projects/*' => Http::response(['name' => 'mensaje-de-prueba']),
        ]);

        foreach (['proyecto-de-prueba' => 'avisos@proyecto-de-prueba.iam.example.test',
            'proyecto-de-produccion' => 'avisos@proyecto-de-produccion.iam.example.test'] as $proyecto => $correo) {
            $archivo = $this->configurarCuentaDeServicio(['project_id' => $proyecto, 'client_email' => $correo]);
            config(['avisos.fcm.credenciales' => null]);

            $this->configuracionDelEntorno('avisos', ['FCM_CREDENCIALES' => $archivo]);
            app(CanalDeFcm::class)->mandar('token-de-prueba', ['notification' => ['title' => 'Prueba', 'body' => 'Prueba']]);
        }

        $proyectos = Http::recorded(fn (Request $peticion): bool => str_starts_with($peticion->url(), 'https://fcm.googleapis.com/'))
            ->map(fn (array $par): string => $par[0]->url())
            ->values()
            ->all();

        $this->assertSame([
            'https://fcm.googleapis.com/v1/projects/proyecto-de-prueba/messages:send',
            'https://fcm.googleapis.com/v1/projects/proyecto-de-produccion/messages:send',
        ], $proyectos);
    }

    #[TestDox('publicacion-E01: al crear una orden de pago, el servidor usa la pasarela y la llave de las variables del entorno, y con otra llave en el entorno usa la otra')]
    public function test_publicacion_e01_la_orden_de_pago_usa_la_pasarela_y_la_llave_del_entorno(): void
    {
        $this->prepararPedido();

        foreach (['llave-del-entorno-de-prueba', 'llave-de-otro-entorno-de-prueba'] as $llave) {
            $this->configuracionDelEntorno('pasarela', ['PASARELA' => 'simulada', 'PASARELA_SIMULADA_LLAVE' => $llave]);

            app(Pasarelas::class)->configurada()->crearOrden($this->cobroPendiente($this->pedidoPorPagar()));
        }

        $this->assertSame(
            ['Bearer llave-del-entorno-de-prueba', 'Bearer llave-de-otro-entorno-de-prueba'],
            array_column($this->pasarelaSimulada()->peticiones(), 'autorizacion'),
        );
    }

    #[TestDox('publicacion-E01: con la configuración de producción no hay pasarela de prueba: la simulada no cobra aunque el entorno la nombre y traiga su llave')]
    public function test_publicacion_e01_en_produccion_no_cobra_la_pasarela_de_prueba(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        $this->configuracionDelEntorno('pasarela', ['PASARELA' => 'simulada', 'PASARELA_SIMULADA_LLAVE' => 'llave-de-prueba']);

        $this->expectException(PasarelaNoDisponible::class);

        app(Pasarelas::class)->configurada();
    }

    #[TestDox('publicacion-E01: la pasarela, Firebase, las llaves de Google y el correo salen de las variables del entorno: con otras variables, otra configuración')]
    public function test_publicacion_e01_cada_credencial_sale_de_su_variable_del_entorno(): void
    {
        foreach (self::CREDENCIALES as [$archivo, $clave, $variable]) {
            foreach (["valor-de-prueba-de-{$variable}", "otro-valor-de-prueba-de-{$variable}"] as $valor) {
                $this->configuracionDelEntorno($archivo, [$variable => $valor]);

                $this->assertSame($valor, config("{$archivo}.{$clave}"), "{$archivo}.{$clave} sale de {$variable}.");
            }
        }

        $this->configuracionDelEntorno('services', ['GOOGLE_CLIENTES_OAUTH' => 'cliente-a.apps.example.test, cliente-b.apps.example.test']);
        $this->assertSame(['cliente-a.apps.example.test', 'cliente-b.apps.example.test'], config('services.google.clientes'));
    }

    #[TestDox('publicacion-E01: ninguna llave, token ni credencial está escrita en el código: ni de Google, ni de Firebase, ni de la pasarela, y solo la configuración lee el entorno')]
    public function test_publicacion_e01_ninguna_credencial_esta_escrita_en_el_codigo(): void
    {
        $archivos = Finder::create()->files()->in(array_map(base_path(...), self::CARPETAS_DEL_CODIGO));
        $raiz = Finder::create()->files()->in(public_path())->depth(0)->ignoreDotFiles(false);
        $this->assertGreaterThan(0, $archivos->count());

        $hallazgos = [];

        foreach ([...$archivos, ...$raiz] as $archivo) {
            $texto = $archivo->getContents();

            foreach (self::FORMAS_DE_CREDENCIAL as $que => $forma) {
                if (preg_match($forma, $texto, $coincidencia)) {
                    $hallazgos[] = "{$archivo->getRelativePathname()}: {$que} ({$coincidencia[0]})";
                }
            }

            // Fuera de config/, env() no lee nada cuando la configuración está en caché, como en producción.
            if (! str_starts_with($archivo->getRealPath(), config_path()) && preg_match('/\benv\(/', $texto)) {
                $hallazgos[] = "{$archivo->getRelativePathname()}: lee el entorno fuera de config/";
            }
        }

        $this->assertSame([], $hallazgos);
    }
}
