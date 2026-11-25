<?php

namespace Tests\Feature\Autorizacion;

use App\Autorizacion\Autorizacion;
use App\Filament\Pages\MonitoreoDePedidos;
use App\Filament\Resources\Lavanderias\Pages\ListLavanderias;
use App\Filament\Resources\Repartidores\Pages\ListRepartidores;
use App\Filament\Resources\Usuarios\Pages\ListUsuarios;
use App\Filament\Resources\Usuarios\Pages\ViewUsuario;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\Autorizacion\Soporte\DosPlazasDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Estados\Soporte\EstadosDePrueba;
use Tests\Feature\PedidosLavanderia\Soporte\PedidosDeLaLavanderiaDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * La administración consulta los socios, los usuarios y los pedidos de todas las plazas, porque el Módulo D gestiona a
 * todos los socios y usuarios y monitorea todos los pedidos activos (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo
 * D #1 a #4; :44, ANEXO D, D.1). Lo permite el componente de autorización (Autorizacion::*DelPanel()), que es de donde
 * parten los recursos y las páginas del panel.
 *
 * Con el sembrado de dos plazas (Soporte\DosPlazasDePrueba). El detalle de cada pantalla lo prueban alta-de-lavanderias,
 * repartidor y administracion (administracion-E01 y E06, repartidor-E05); esta prueba junta las dos plazas en cada una.
 *
 * Escenario: autorizacion-E13 · Spec: openspec/changes/autorizacion-por-rol/specs/autorizacion/spec.md · Tarea 3.2
 */
class AdministracionTodasLasPlazasTest extends TestCase
{
    use AgendaDePrueba;
    use DosPlazasDePrueba;
    use EstadosDePrueba;
    use LavanderiasDePrueba;
    use PedidosDeLaLavanderiaDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sembrarDosPlazas();
    }

    #[TestDox('autorizacion-E13: con pedidos, socios y usuarios en las plazas P1 y P2, el componente le da a la administración las lavanderías, los repartidores, los usuarios y los pedidos de las dos plazas')]
    public function test_autorizacion_e13_el_componente_le_da_a_la_administracion_las_dos_plazas(): void
    {
        $autorizacion = app(Autorizacion::class);

        $this->assertContieneLasCuatroCadenas('lavanderia', $autorizacion->lavanderiasDelPanel($this->administracion)->pluck('id_lavanderias')->all());
        $this->assertContieneLasCuatroCadenas('repartidor', $autorizacion->repartidoresDelPanel($this->administracion)->pluck('id_repartidores')->all());
        $this->assertContieneLasCuatroCadenas('usuario', $autorizacion->usuariosDelPanel($this->administracion)->pluck('id_usuarios')->all());
        $this->assertContieneLasCuatroCadenas('enCurso', $autorizacion->pedidosDelPanel($this->administracion)->pluck('id_pedidos')->all());
        $this->assertContieneLasCuatroCadenas('entregado', $autorizacion->pedidosDelPanel($this->administracion)->pluck('id_pedidos')->all());
        $this->assertContieneLasCuatroCadenas('enCurso', $autorizacion->pedidosActivosDelPanel($this->administracion)->pluck('id_pedidos')->all());

        // Un socio de la plaza P1 no recibe nada del panel: la prueba no pasa en vacío.
        $socio = $this->cadenas['UnoA']['lavanderia']->administrador;
        $this->assertSame(0, $autorizacion->lavanderiasDelPanel($socio)->count());
        $this->assertSame(0, $autorizacion->pedidosDelPanel($socio)->count());
    }

    #[TestDox('autorizacion-E13: en el panel, la administración ve en cada lista las lavanderías, los repartidores y los usuarios de las dos plazas, y en el monitoreo los pedidos en curso de las dos')]
    public function test_autorizacion_e13_el_panel_le_muestra_las_dos_plazas(): void
    {
        $this->comoLaAdministracion();

        Livewire::test(ListLavanderias::class)->assertOk()->assertCanSeeTableRecords($this->deLasCuatroCadenas('lavanderia'));
        Livewire::test(ListRepartidores::class)->assertOk()->assertCanSeeTableRecords($this->deLasCuatroCadenas('repartidor'));
        Livewire::test(ListUsuarios::class)->assertOk()->assertCanSeeTableRecords($this->deLasCuatroCadenas('usuario'));
        Livewire::test(MonitoreoDePedidos::class)
            ->assertOk()
            ->assertCanSeeTableRecords($this->deLasCuatroCadenas('enCurso'))
            ->assertSee([$this->plazaUno->nombre, $this->plazaDos->nombre]);
    }

    #[TestDox('autorizacion-E13: la administración abre la ficha de un usuario de cada plaza y ve su historial de pedidos')]
    public function test_autorizacion_e13_la_ficha_de_un_usuario_de_cada_plaza_trae_su_historial(): void
    {
        $this->comoLaAdministracion();

        foreach (['UnoA', 'DosB'] as $marca) {
            $cadena = $this->cadenas[$marca];

            Livewire::test(ViewUsuario::class, ['record' => $cadena['usuario']->getRouteKey()])
                ->assertOk()
                ->assertSee([$cadena['enCurso']->folio, $cadena['entregado']->folio]);
        }
    }

    private function comoLaAdministracion(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs($this->administracion, 'web');
    }

    /**
     * Lo de ese tipo de las cuatro cadenas, de las dos plazas.
     *
     * @return list<mixed>
     */
    private function deLasCuatroCadenas(string $que): array
    {
        return array_values(array_map(fn (array $cadena): mixed => $cadena[$que], $this->cadenas));
    }

    /**
     * Las llaves que dio el componente traen las de lo de ese tipo de las cuatro cadenas, de las dos plazas.
     *
     * @param  list<int>  $llaves
     */
    private function assertContieneLasCuatroCadenas(string $que, array $llaves): void
    {
        foreach ($this->cadenas as $marca => $cadena) {
            $this->assertContains($cadena[$que]->getKey(), $llaves, "{$que} {$marca}, de la plaza {$cadena['plaza']->nombre}");
        }
    }
}
