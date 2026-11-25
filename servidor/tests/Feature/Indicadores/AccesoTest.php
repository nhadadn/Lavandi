<?php

namespace Tests\Feature\Indicadores;

use App\Autorizacion\Autorizacion;
use App\Filament\Pages\Indicadores;
use App\Models\Rol;
use App\Models\Usuario;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\AltaLavanderias\Soporte\PanelDePrueba;
use Tests\TestCase;

/**
 * El panel de indicadores vive dentro de la administración y solo lo ve el rol de administración
 * (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5, "integrado en la sección de administración"; NSV-2026-001-v8.pdf:20:
 * ANEXO A, Sección A.3, separación por tipo de usuario; ADR-0019).
 *
 * Lo decide el componente único de autorización: la página y cada uno de sus widgets le preguntan, y un widget montado
 * por otra cuenta responde 403 aunque se pida directo a Livewire (autorizacion-E03).
 *
 * Escenario: indicadores-E01 (la página) · Spec: openspec/changes/indicadores/specs/indicadores/spec.md · Tarea 1.1
 */
class AccesoTest extends TestCase
{
    use PanelDePrueba;
    use RefreshDatabase;

    /** Los roles que no son de administración; repartidor todavía no tiene constante en Rol. */
    private const OTROS_ROLES = [Rol::USUARIO, Rol::LAVANDERIA, 'repartidor'];

    #[TestDox('indicadores-E01: la administración abre la página de indicadores y la ve en la navegación del panel')]
    public function test_indicadores_e01_la_administracion_abre_la_pagina_de_indicadores(): void
    {
        $administracion = $this->comoAdministracion();

        $this->assertTrue(app(Autorizacion::class)->puedeVerLosIndicadores($administracion));
        $this->get('/admin/indicadores')->assertOk();
        $this->get('/admin')->assertOk()->assertSee('Indicadores');
    }

    #[TestDox('indicadores-E01: una cuenta de usuario, de socio lavandería o de socio repartidor no ve la página de indicadores')]
    public function test_indicadores_e01_las_cuentas_de_otros_roles_no_ven_la_pagina(): void
    {
        foreach (self::OTROS_ROLES as $rol) {
            $cuenta = Usuario::factory()->conRol($rol)->create();
            $this->actingAs($cuenta, 'web');

            $this->assertFalse(app(Autorizacion::class)->puedeVerLosIndicadores($cuenta), $rol);
            $this->get('/admin/indicadores')->assertForbidden();
        }
    }

    #[TestDox('indicadores-E01: ningún widget de la página se monta para una cuenta que no es de administración, ni sin sesión')]
    public function test_indicadores_e01_ningun_widget_se_monta_para_otra_cuenta(): void
    {
        Filament::setCurrentPanel('admin');
        $widgets = (new Indicadores)->getWidgets();
        $this->assertNotEmpty($widgets);

        foreach ($widgets as $widget) {
            $this->assertFalse($widget::canView(), "{$widget} sin sesión.");
        }

        foreach (self::OTROS_ROLES as $rol) {
            $this->actingAs(Usuario::factory()->conRol($rol)->create(), 'web');

            foreach ($widgets as $widget) {
                $this->assertFalse($widget::canView(), "{$widget} con {$rol}.");
                Livewire::test($widget)->assertForbidden();
            }
        }
    }

    #[TestDox('indicadores-E01: los widgets de indicadores no aparecen en el tablero de inicio del panel')]
    public function test_indicadores_e01_los_widgets_no_aparecen_en_el_tablero_de_inicio(): void
    {
        $this->comoAdministracion();

        foreach ((new Indicadores)->getWidgets() as $widget) {
            $this->assertFalse($widget::isDiscovered(), $widget);
            $this->get('/admin')->assertDontSeeLivewire($widget);
        }
    }
}
