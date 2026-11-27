<?php

namespace Tests\Feature\Autorizacion;

use App\Autorizacion\Autorizacion;
use App\Http\Middleware\Autorizar;
use App\Models\Rol;
use App\Models\Usuario;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Resources\Resource;
use Filament\Widgets\Widget;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Autorizacion\Soporte\RecursoSinRegla;
use Tests\Feature\Autorizacion\Soporte\TablaDeRutas;
use Tests\TestCase;

/**
 * Toda ruta y todo recurso del panel declara su regla en el componente único de autorización, y una ruta sin regla hace
 * fallar las pruebas y se nombra (NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3, Separación por tipo de usuario; ADR-0003;
 * ADR-0019, decisión 1). Las rutas salen de la tabla del enrutador, no de una lista escrita a mano: una ruta nueva sin
 * regla, también la de un cambio que llegue después, hace fallar esta prueba.
 *
 * La regla de cada ruta es una de tres (Soporte\TablaDeRutas): la sesión de Sanctum y autorizar:<rol> en la API, la
 * puerta del panel en /admin, o su declaración en Autorizacion::rutasSinSesion(), con su razón. Las del inicio de sesión,
 * la recuperación, la portada y la infraestructura de Laravel, Livewire y Filament se declaran ahí.
 *
 * Corre en su propio paso de la integración continua, junto con el barrido contra fugas (SEG-3).
 *
 * Escenario: autorizacion-E01 · Spec: openspec/changes/autorizacion-por-rol/specs/autorizacion/spec.md · Tarea 1.1
 */
#[Group('barrido-contra-fugas')]
class CoberturaDeReglasTest extends TestCase
{
    use RefreshDatabase;
    use TablaDeRutas;

    /** Los roles que no son de administración. */
    private const OTROS_ROLES = [Rol::USUARIO, Rol::LAVANDERIA, Rol::REPARTIDOR];

    #[TestDox('autorizacion-E01: toda ruta registrada declara su regla: la API pide sesión y pasa por el componente, el panel pasa por su puerta, y las que no piden sesión lo declaran en el componente')]
    public function test_autorizacion_e01_toda_ruta_registrada_declara_su_regla(): void
    {
        $this->assertSame([], $this->rutasSinRegla());

        // La tabla trae las tres reglas: la prueba no pasa en vacío.
        $reglas = array_map(fn ($ruta): ?string => $this->reglaDe($ruta), $this->rutasRegistradas());
        $this->assertContains('GET api/pedidos', array_keys($reglas, 'componente', true));
        $this->assertContains('GET admin/lavanderias', array_keys($reglas, 'panel', true));
        $this->assertArrayHasKey('POST api/sesion', app(Autorizacion::class)->rutasSinSesion());
    }

    #[TestDox('autorizacion-E01: una ruta nueva sin regla hace fallar la prueba y la nombra, en la API, en el panel y fuera de los dos; la misma uri con otro método no hereda el permiso')]
    public function test_autorizacion_e01_una_ruta_nueva_sin_regla_hace_fallar_la_prueba_y_la_nombra(): void
    {
        Route::prefix('api')->middleware('api')->group(function () {
            Route::get('prueba/sin-regla', fn () => 'nada');
            Route::get('prueba/solo-sesion', fn () => 'nada')->middleware('auth:sanctum');
            Route::get('prueba/solo-componente', fn () => 'nada')->middleware('autorizar:usuario');
            Route::get('prueba/con-las-dos', fn () => 'nada')->middleware(['auth:sanctum', 'autorizar:usuario']);
            // Una ruta sin sesión con otro método no hereda el permiso.
            Route::put('sesion', fn () => 'nada');
        });
        Route::middleware('web')->group(function () {
            Route::get('prueba/pagina-sin-regla', fn () => 'nada');
            Route::get('admin/prueba/sin-puerta', fn () => 'nada');
        });

        $this->assertSame([
            'GET admin/prueba/sin-puerta',
            'GET api/prueba/sin-regla',
            'GET api/prueba/solo-componente',
            'GET api/prueba/solo-sesion',
            'GET prueba/pagina-sin-regla',
            'PUT api/sesion',
        ], $this->rutasSinRegla());
    }

    #[TestDox('autorizacion-E01: las rutas que no piden sesión, como el inicio de sesión, lo declaran en el componente con su razón, existen y de verdad no la piden')]
    public function test_autorizacion_e01_las_rutas_sin_sesion_lo_declaran_en_el_componente(): void
    {
        $sinSesion = app(Autorizacion::class)->rutasSinSesion();
        $registradas = $this->rutasRegistradas();

        foreach (['POST api/registro', 'POST api/sesion', 'POST api/sesion/google', 'POST api/contrasena/olvido',
            'POST api/repartidor/registro', 'GET admin/login', 'GET /'] as $declarada) {
            $this->assertArrayHasKey($declarada, $sinSesion);
        }

        foreach ($sinSesion as $nombre => $razon) {
            $this->assertArrayHasKey($nombre, $registradas, "{$nombre}: se declara sin sesión y no es una ruta.");
            $this->assertNotSame('', trim($razon), "{$nombre}: se declara sin sesión sin decir por qué.");

            $middleware = $this->middlewareDe($registradas[$nombre]);
            $this->assertNotContains(Authenticate::class.':sanctum', $middleware, $nombre);
            $this->assertSame([], array_values(array_filter(
                $middleware,
                fn (string $clase): bool => Str::startsWith($clase, Autorizar::class),
            )), "{$nombre}: se declara sin sesión y pasa por el componente.");
        }
    }

    #[TestDox('autorizacion-E01: todo recurso, toda página y todo widget del panel declara su regla en el componente: le niega la entrada a una cuenta de otro rol aunque pasara la puerta del panel, o el componente lo declara sin datos')]
    public function test_autorizacion_e01_todo_recurso_del_panel_declara_su_regla(): void
    {
        $this->assertSame([], $this->piezasDelPanelSinRegla());

        // El panel tiene recursos, páginas y widgets propios: la prueba no pasa en vacío.
        $panel = Filament::getPanel('admin');
        $this->assertGreaterThanOrEqual(4, count($panel->getResources()));
        $this->assertGreaterThanOrEqual(4, count($panel->getPages()));
        $this->assertNotEmpty($panel->getWidgets());
    }

    #[TestDox('autorizacion-E01: un recurso nuevo del panel sin regla hace fallar la prueba y lo nombra')]
    public function test_autorizacion_e01_un_recurso_nuevo_sin_regla_hace_fallar_la_prueba(): void
    {
        $this->assertSame([RecursoSinRegla::class], $this->piezasDelPanelSinRegla([RecursoSinRegla::class]));
    }

    /**
     * Los recursos, las páginas y los widgets del panel, más los que se pasen, que dejan entrar a una cuenta que no es de
     * administración y que el componente no declara sin datos.
     *
     * @param  list<class-string>  $otras
     * @return list<class-string>
     */
    private function piezasDelPanelSinRegla(array $otras = []): array
    {
        $panel = Filament::getPanel('admin');
        Filament::setCurrentPanel($panel);
        $sinDatos = app(Autorizacion::class)->panelSinDatos();
        $cuentas = array_map(fn (string $rol): Usuario => Usuario::factory()->conRol($rol)->create(), self::OTROS_ROLES);
        $sinRegla = [];

        foreach ([...$panel->getResources(), ...$panel->getPages(), ...$panel->getWidgets(), ...$otras] as $pieza) {
            if (array_key_exists($pieza, $sinDatos)) {
                continue;
            }

            foreach ($cuentas as $cuenta) {
                $this->actingAs($cuenta, 'web');

                if ($this->dejaEntrar($pieza)) {
                    $sinRegla[] = $pieza;

                    break;
                }
            }
        }

        return $sinRegla;
    }

    /**
     * @param  class-string  $pieza
     */
    private function dejaEntrar(string $pieza): bool
    {
        return match (true) {
            is_subclass_of($pieza, Resource::class), is_subclass_of($pieza, Page::class) => $pieza::canAccess(),
            is_subclass_of($pieza, Widget::class) => $pieza::canView(),
            default => true,
        };
    }
}
