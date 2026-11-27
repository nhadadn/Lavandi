<?php

namespace Tests\Feature\AdministracionEnLaApp;

use App\Filament\Auth\IniciarSesion;
use App\Models\Rol;
use App\Models\Usuario;
use Database\Factories\UsuarioFactory;
use DOMElement;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Landing\Soporte\Portada;
use Tests\TestCase;

/**
 * El panel web de Filament se queda como respaldo interno: mismas rutas (/admin), misma entrada con contraseña y rol de
 * administración, y nada lo muestra; la portada tampoco lo enlaza (C-247; ADR-0022, decisiones 18 y 19). Que el panel
 * quede como respaldo viene de C-247, no del contrato. Las demás pruebas del panel siguen sin cambios.
 *
 * Escenario: administracion-app-E10 · Spec: openspec/changes/administracion-en-la-app/specs/administracion-en-la-app/
 * spec.md · Tarea 1.5
 */
class PanelDeRespaldoTest extends TestCase
{
    use Portada;
    use RefreshDatabase;

    #[TestDox('administracion-app-E10: la cuenta de administración entra al panel /admin con su contraseña, como en autorizacion-E03')]
    public function test_administracion_app_e10_la_administracion_entra_al_panel_con_su_contrasena(): void
    {
        $administracion = Usuario::factory()->conRol(Rol::ADMINISTRACION)->create(['correo' => 'duenio@ejemplo.com']);
        Filament::setCurrentPanel('admin');

        Livewire::test(IniciarSesion::class)
            ->fillForm(['email' => 'duenio@ejemplo.com', 'password' => UsuarioFactory::CONTRASENA])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($administracion, 'web');
        $this->get('/admin')->assertOk();
    }

    #[TestDox('administracion-app-E10: el panel rechaza a las cuentas de usuario, de lavandería y de repartidor, con su contraseña y con una sesión web abierta, como en autorizacion-E03')]
    public function test_administracion_app_e10_el_panel_rechaza_a_las_cuentas_de_otros_roles(): void
    {
        Filament::setCurrentPanel('admin');

        foreach ([Rol::USUARIO, Rol::LAVANDERIA, Rol::REPARTIDOR] as $rol) {
            $cuenta = Usuario::factory()->conRol($rol)->create();

            Livewire::test(IniciarSesion::class)
                ->fillForm(['email' => $cuenta->correo, 'password' => UsuarioFactory::CONTRASENA])
                ->call('authenticate')
                ->assertHasFormErrors(['email']);
            $this->assertGuest('web');

            $this->actingAs($cuenta, 'web')->get('/admin')->assertForbidden();
            $this->app['auth']->forgetGuards();
        }
    }

    #[TestDox('administracion-app-E10: la portada no trae ningún enlace al panel')]
    public function test_administracion_app_e10_la_portada_no_enlaza_el_panel(): void
    {
        $panel = '/'.trim(Filament::getPanel('admin')->getPath(), '/');
        $portada = $this->portada();
        $enlaces = [];

        foreach ($portada->query('//*[@href or @action or @formaction]') as $elemento) {
            if (! $elemento instanceof DOMElement) {
                continue;
            }

            foreach (['href', 'action', 'formaction'] as $atributo) {
                $destino = (string) parse_url($elemento->getAttribute($atributo), PHP_URL_PATH);

                if ($destino === $panel || str_starts_with($destino, $panel.'/')) {
                    $enlaces[] = "<{$elemento->tagName} {$atributo}=\"{$elemento->getAttribute($atributo)}\">";
                }
            }
        }

        // La portada sí trae enlaces: la búsqueda no pasa en vacío.
        $this->assertNotEmpty(iterator_to_array($portada->query('//a[@href]')));
        $this->assertSame([], $enlaces);
    }
}
