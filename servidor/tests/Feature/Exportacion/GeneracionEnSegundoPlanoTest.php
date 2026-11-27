<?php

namespace Tests\Feature\Exportacion;

use App\Autorizacion\Autorizacion;
use App\Exportacion\Exportacion;
use App\Models\Rol;
use App\Models\Usuario;
use Filament\Facades\Filament;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\AltaLavanderias\Soporte\PanelDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Exportacion\Soporte\DescargasDePrueba;
use Tests\Feature\Indicadores\Soporte\IndicadoresDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * Las descargas se generan en segundo plano, el panel avisa cuando el archivo está listo y solo lo baja la cuenta que lo
 * pidió (ADR-0003; NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3, separación por tipo de usuario).
 *
 * - Con la cola de la base, como en el entorno de prueba y en producción (DD-070), la acción solo deja la descarga en
 *   cola y dice que el archivo se está generando. El proceso de colas escribe el archivo y deja el aviso en las
 *   notificaciones del panel de esa cuenta, con un enlace para cada formato.
 * - El enlace es la ruta de descarga de Filament. Quién baja el archivo lo decide el componente único de autorización:
 *   la cuenta que lo pidió, si todavía es de administración. A cualquier otra cuenta el archivo le responde como si no
 *   existiera (ADR-0019, decisión 3); sin sesión, se le pide entrar.
 * - Los archivos viven los días de la configuración, 7 por omisión, y después el borrado diario los quita con su fila
 *   (design.md, pregunta abierta 2; exportacion-E12).
 *
 * Escenarios: exportacion-E10, exportacion-E11 y exportacion-E12 · Spec:
 * openspec/changes/exportacion/specs/exportacion/spec.md · Tarea 4.1
 */
class GeneracionEnSegundoPlanoTest extends TestCase
{
    use AgendaDePrueba;
    use DescargasDePrueba;
    use IndicadoresDePrueba;
    use LavanderiasDePrueba;
    use PanelDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    private const RANGO = ['desde' => '2026-01-01', 'hasta' => '2026-12-31', 'granularidad' => 'dia'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();
        $this->pedidoConfirmadoEl('2026-12-01 10:00:00');
    }

    #[TestDox('exportacion-E10: la administración pide la descarga de un rango largo, el panel le dice que el archivo se está generando y, al terminar la cola, le avisa en el panel con el enlace de descarga')]
    public function test_exportacion_e10_avisa_que_se_genera_y_al_terminar_deja_el_enlace_en_el_panel(): void
    {
        config(['queue.default' => 'database']);
        $administracion = $this->comoAdministracion();

        $this->pedirDescarga('descargarPedidosTotales', self::RANGO)
            ->assertHasNoActionErrors()
            ->assertNotified('El archivo se está generando');

        // La petición solo dejó la descarga en cola.
        $exportacion = Exportacion::sole();
        $this->assertNull($exportacion->completed_at);
        $this->assertFalse(Storage::disk($exportacion->file_disk)->exists($exportacion->getFileDirectory()));
        $this->assertSame(0, $administracion->notifications()->count());

        // El proceso de colas, en este mismo proceso. Su tope de memoria va por encima de lo que ya usa la suite: con el
        // de omisión (128 MB), el trabajador se detiene antes de empezar cuando la prueba corre con todas las demás.
        $this->artisan('queue:work', [
            '--stop-when-empty' => true,
            '--memory' => (int) ceil(memory_get_usage(true) / 1048576) + 256,
        ])->assertSuccessful();

        $exportacion->refresh();
        $this->assertNotNull($exportacion->completed_at);
        $this->assertSame(365, $exportacion->successful_rows);

        $aviso = $administracion->notifications()->sole();
        $this->assertSame('El archivo de pedidos totales está listo', $aviso->data['title']);
        $this->assertStringContainsString('365 filas', $aviso->data['body']);

        $enlaces = array_column($aviso->data['actions'], 'url', 'name');
        $this->assertSame(['download_csv', 'download_xlsx'], array_keys($enlaces));

        $this->actingAs($administracion, 'web');
        $csv = $this->get($enlaces['download_csv'])->assertOk()->streamedContent();
        $this->assertStringContainsString('Periodo,Pedidos', $csv);
        $this->get($enlaces['download_xlsx'])->assertOk();

        // El aviso se ve en las notificaciones del panel.
        $this->assertTrue(Filament::getPanel('admin')->hasDatabaseNotifications());
    }

    #[TestDox('exportacion-E10: con la cola en la misma petición, el aviso de archivo listo sale de una vez, con sus dos enlaces')]
    public function test_exportacion_e10_con_la_cola_en_la_misma_peticion_el_aviso_sale_de_una_vez(): void
    {
        $this->comoAdministracion();

        $this->pedirDescarga('descargarPedidosTotales', self::RANGO)
            ->assertHasNoActionErrors()
            ->assertNotified('El archivo de pedidos totales está listo');

        $this->assertNotNull(Exportacion::sole()->completed_at);
    }

    #[TestDox('exportacion-E11: con un archivo generado para una cuenta de administración, otra cuenta que abre su enlace no recibe el archivo: le responde como si no existiera')]
    public function test_exportacion_e11_otra_cuenta_no_baja_el_archivo(): void
    {
        $duena = $this->comoAdministracion();
        $this->pedirDescarga('descargarPedidosTotales', self::RANGO)->assertHasNoActionErrors();
        $exportacion = Exportacion::sole();

        foreach ([Rol::ADMINISTRACION, Rol::USUARIO, Rol::LAVANDERIA, Rol::REPARTIDOR] as $rol) {
            $this->actingAs(Usuario::factory()->conRol($rol)->create(), 'web');

            foreach (['csv', 'xlsx'] as $formato) {
                $this->abrirEnlace($exportacion, $formato)->assertNotFound();
            }
        }

        // Sin sesión, se le pide entrar.
        $this->app['auth']->forgetGuards();
        $this->abrirEnlace($exportacion, 'csv')->assertUnauthorized();

        // La dueña sí lo baja.
        $this->actingAs($duena, 'web');
        $this->abrirEnlace($exportacion, 'csv')->assertOk();
        $this->abrirEnlace($exportacion, 'xlsx')->assertOk();
    }

    #[TestDox('exportacion-E11: el componente único de autorización decide quién baja el archivo: la cuenta que lo pidió mientras siga siendo de administración')]
    public function test_exportacion_e11_el_componente_decide_quien_baja_el_archivo(): void
    {
        $duena = $this->comoAdministracion();
        $this->pedirDescarga('descargarPedidosTotales', self::RANGO)->assertHasNoActionErrors();
        $exportacion = Exportacion::sole();
        $autorizacion = app(Autorizacion::class);

        $this->assertTrue($autorizacion->puedeDescargarLaExportacion($duena, $exportacion));
        $this->assertFalse($autorizacion->puedeDescargarLaExportacion(Usuario::factory()->conRol(Rol::ADMINISTRACION)->create(), $exportacion));

        // Bloqueada la cuenta, ya no baja ni su propio archivo.
        $duena->forceFill(['estado' => Usuario::BLOQUEADO])->save();
        $this->assertFalse($autorizacion->puedeDescargarLaExportacion($duena->refresh(), $exportacion));
        $this->actingAs($duena, 'web');
        $this->abrirEnlace($exportacion, 'csv')->assertNotFound();

        // La ruta de descarga de Filament está entre las que no piden rol, con la razón de la exportación.
        $this->assertStringContainsString('exportacion-E11', $autorizacion->rutasSinSesion()['GET filament/exports/{export}/download']);
    }

    #[TestDox('exportacion-E12: la descarga vive 7 días por omisión; pasados, el borrado programado una vez al día la quita con sus archivos y conserva la más reciente')]
    public function test_exportacion_e12_la_descarga_caduca_y_el_borrado_diario_la_quita(): void
    {
        // Los 7 días son el valor por omisión de la configuración (EXPORTACION_DIAS_DE_VIDA), no del contrato.
        config(['exportacion.dias_de_vida' => 7]);
        $this->comoAdministracion();
        $this->pedirDescarga('descargarPedidosTotales', self::RANGO)->assertHasNoActionErrors();
        $vieja = Exportacion::sole();
        $disco = Storage::disk($vieja->file_disk);
        $this->assertTrue($disco->exists($vieja->getFileDirectory()));

        $this->travel(6)->days();
        $this->pedirDescarga('descargarPedidosTotales', self::RANGO)->assertHasNoActionErrors();
        $nueva = $this->ultimaExportacion();

        // A los 7 días y un poco, la primera ya no está; la segunda sí.
        $this->travel(1)->days();
        $this->travel(1)->minutes();
        $this->artisan('model:prune', ['--model' => [Exportacion::class]])->assertSuccessful();

        $this->assertModelMissing($vieja);
        $this->assertFalse($disco->exists($vieja->getFileDirectory()));
        $this->assertModelExists($nueva);
        $this->assertTrue($disco->exists($nueva->getFileDirectory()));

        // El borrado corre solo, una vez al día, a medianoche.
        $programado = collect(app(Schedule::class)->events())
            ->filter(fn (Event $evento): bool => str_contains((string) $evento->command, 'model:prune')
                && str_contains((string) $evento->command, 'Exportacion'));
        $this->assertCount(1, $programado, 'El borrado de las descargas viejas no está programado.');
        $this->assertSame('0 0 * * *', $programado->sole()->expression);
    }
}
