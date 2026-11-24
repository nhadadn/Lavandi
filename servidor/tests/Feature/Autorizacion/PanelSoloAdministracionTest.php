<?php

namespace Tests\Feature\Autorizacion;

use App\Autorizacion\Autorizacion;
use App\Filament\Auth\IniciarSesion;
use App\Models\Lavanderia;
use App\Models\Repartidor;
use App\Models\Rol;
use App\Models\Testimonio;
use App\Models\Usuario;
use Database\Factories\UsuarioFactory;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as Ruta;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Autorizacion\Soporte\TablaDeRutas;
use Tests\TestCase;

/**
 * El panel web solo admite el rol de administración: una cuenta de usuario, una de socio lavandería y una de socio
 * repartidor no entran, ni con su contraseña ni con una sesión web abierta, y una de administración sí
 * (NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3, separación por tipo de usuario; ADR-0005; ADR-0019). Lo decide el
 * componente único de autorización a través de Usuario::canAccessPanel(), también para las lavanderías del panel.
 *
 * Las rutas del panel salen de la tabla de rutas (Soporte\TablaDeRutas): una página o un recurso nuevo entra solo.
 *
 * Escenario: autorizacion-E03 · Spec: openspec/changes/autorizacion-por-rol/specs/autorizacion/spec.md · Llega con
 * alta-de-lavanderias, que abre el primer recurso del panel.
 */
class PanelSoloAdministracionTest extends TestCase
{
    use RefreshDatabase;
    use TablaDeRutas;

    /** Los roles que no son de administración. */
    private const OTROS_ROLES = [Rol::USUARIO, Rol::LAVANDERIA, Rol::REPARTIDOR];

    #[TestDox('autorizacion-E03: una cuenta de usuario, de socio lavandería o de socio repartidor no entra al panel con su contraseña')]
    public function test_autorizacion_e03_las_cuentas_de_otros_roles_no_entran_al_panel_con_su_contrasena(): void
    {
        Filament::setCurrentPanel('admin');

        foreach (self::OTROS_ROLES as $rol) {
            $cuenta = Usuario::factory()->conRol($rol)->create();

            Livewire::test(IniciarSesion::class)
                ->fillForm(['email' => $cuenta->correo, 'password' => UsuarioFactory::CONTRASENA])
                ->call('authenticate')
                ->assertHasFormErrors(['email']);

            $this->assertGuest('web');
        }
    }

    #[TestDox('autorizacion-E03: con una sesión web abierta, el panel y su recurso de lavanderías rechazan a las cuentas de otros roles')]
    public function test_autorizacion_e03_con_sesion_web_el_panel_rechaza_a_las_cuentas_de_otros_roles(): void
    {
        foreach (self::OTROS_ROLES as $rol) {
            $this->actingAs(Usuario::factory()->conRol($rol)->create(), 'web');

            $this->get('/admin')->assertForbidden();
            $this->get('/admin/lavanderias')->assertForbidden();
        }
    }

    #[TestDox('autorizacion-E03: una cuenta de administración sí entra con su contraseña y ve el recurso de lavanderías')]
    public function test_autorizacion_e03_una_cuenta_de_administracion_si_entra(): void
    {
        $administracion = Usuario::factory()->conRol(Rol::ADMINISTRACION)->create(['correo' => 'administracion@ejemplo.com']);
        Filament::setCurrentPanel('admin');

        Livewire::test(IniciarSesion::class)
            ->fillForm(['email' => 'Administracion@Ejemplo.com', 'password' => UsuarioFactory::CONTRASENA])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($administracion, 'web');
        $this->get('/admin')->assertOk();
        $this->get('/admin/lavanderias')->assertOk();
    }

    #[TestDox('autorizacion-E03: con una sesión web abierta, cada ruta del panel, recorrida desde la tabla de rutas, rechaza a las cuentas de usuario, de socio lavandería y de socio repartidor sin mostrar nada, y la administración sí entra a cada una')]
    public function test_autorizacion_e03_cada_ruta_del_panel_rechaza_a_los_otros_roles_y_admite_a_la_administracion(): void
    {
        // Un registro de cada recurso, para que las rutas de un registro abran uno que existe.
        $lavanderia = Lavanderia::factory()->create(['nombre' => 'Lavandería del panel E03']);
        Repartidor::factory()->create();
        Testimonio::factory()->create();
        Usuario::factory()->conRol(Rol::USUARIO)->create();

        $rutas = $this->rutasDelPanel();
        // Las páginas, las listas y las fichas de los cuatro recursos, por lo menos: la prueba no pasa en vacío.
        $this->assertGreaterThanOrEqual(12, count($rutas));
        $noRechazaron = [];
        $noEntro = [];

        // Una cuenta por rol en todo el recorrido: la sesión web guarda la de la cuenta que entró (AuthenticateSession).
        $administracion = Usuario::factory()->conRol(Rol::ADMINISTRACION)->create();
        $otras = array_map(fn (string $rol): Usuario => Usuario::factory()->conRol($rol)->create(), self::OTROS_ROLES);

        // La ficha de cada recurso abre un registro que la administración ve en él.
        $this->actingAs($administracion, 'web');
        Filament::setCurrentPanel('admin');
        $urls = array_map(fn (Ruta $ruta): string => $this->urlDe($ruta, ['record' => $this->registroDe($ruta)]), $rutas);

        foreach ($rutas as $nombre => $ruta) {
            $url = $urls[$nombre];

            foreach ($otras as $cuenta) {
                $respuesta = $this->actingAs($cuenta, 'web')->call($this->metodoDe($nombre), $url);

                if ($respuesta->status() !== 403 || str_contains($respuesta->getContent(), $lavanderia->nombre)) {
                    $noRechazaron[] = "{$nombre} · {$cuenta->roles->first()->codigo} · {$respuesta->status()}";
                }
            }

            $respuesta = $this->actingAs($administracion, 'web')->call($this->metodoDe($nombre), $url);

            // Cada página le abre; salir de la sesión la manda al inicio de sesión.
            $entro = $this->metodoDe($nombre) === 'GET' ? $respuesta->status() === 200 : $respuesta->isRedirection();

            if (! $entro) {
                $noEntro[] = "{$nombre} · {$respuesta->status()}";
            }
        }

        $this->assertSame([], $noRechazaron);
        $this->assertSame([], $noEntro);
    }

    #[TestDox('autorizacion-E03: el componente le da a la administración todas las lavanderías del panel, de todas las plazas, y a otra cuenta ninguna')]
    public function test_autorizacion_e03_el_componente_da_las_lavanderias_del_panel_solo_a_la_administracion(): void
    {
        Lavanderia::factory()->count(2)->create();
        $autorizacion = app(Autorizacion::class);

        $this->assertSame(2, $autorizacion->lavanderiasDelPanel(Usuario::factory()->conRol(Rol::ADMINISTRACION)->create())->count());

        foreach (self::OTROS_ROLES as $rol) {
            $this->assertSame(0, $autorizacion->lavanderiasDelPanel(Usuario::factory()->conRol($rol)->create())->count(), $rol);
        }
    }

    /**
     * Las rutas del panel detrás de su puerta, por "MÉTODO uri": todas las de la tabla de rutas con la regla del panel.
     *
     * @return array<string, Ruta>
     */
    private function rutasDelPanel(): array
    {
        return array_filter($this->rutasRegistradas(), fn (Ruta $ruta): bool => $this->reglaDe($ruta) === 'panel');
    }

    /**
     * La llave en la URL de un registro que la cuenta de la sesión ve en el recurso de la ruta
     * (filament.admin.resources.<slug>.<página>), o null si la ruta no es de un registro.
     */
    private function registroDe(Ruta $ruta): ?string
    {
        if (! in_array('record', $this->parametrosDe($ruta), true)) {
            return null;
        }

        $slug = Str::between((string) $ruta->getName(), 'filament.admin.resources.', '.');
        $recurso = collect(Filament::getPanel('admin')->getResources())
            ->sole(fn (string $recurso): bool => $recurso::getSlug() === $slug);

        return (string) $recurso::getEloquentQuery()->firstOrFail()->getRouteKey();
    }
}
