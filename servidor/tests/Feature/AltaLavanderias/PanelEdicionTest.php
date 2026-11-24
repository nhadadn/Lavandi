<?php

namespace Tests\Feature\AltaLavanderias;

use App\Filament\Resources\Lavanderias\LavanderiaResource;
use App\Filament\Resources\Lavanderias\Pages\EditLavanderia;
use App\Filament\Resources\Lavanderias\Pages\ListLavanderias;
use App\Models\Lavanderia;
use App\Models\Rol;
use App\Models\Usuario;
use Closure;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\AltaLavanderias\Soporte\AltaDePrueba;
use Tests\Feature\AltaLavanderias\Soporte\PanelDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * La gestión de lavanderías en el panel web: la lista con su estado y su plaza, y la edición de sus datos de registro
 * con las mismas validaciones del registro de la app (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #1,
 * "edición de socios"; ADR-0003). El alta desde el panel espera DD-053 (alta-lavanderias-E11): el recurso no tiene
 * página de alta.
 *
 * Escenario: alta-lavanderias-E13 · Tarea 3.1 · Spec: openspec/changes/alta-de-lavanderias/specs/alta-lavanderias/spec.md
 */
class PanelEdicionTest extends TestCase
{
    use AltaDePrueba;
    use PanelDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    private ?Closure $deshacerRepeater = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararAlta();
        // Las filas de los repetidores llevan llaves 0, 1, 2… en lugar de UUID.
        $this->deshacerRepeater = Repeater::fake();
    }

    protected function tearDown(): void
    {
        ($this->deshacerRepeater)();

        parent::tearDown();
    }

    #[TestDox('alta-lavanderias-E13: la lista del panel muestra cada lavandería con su estado y su plaza')]
    public function test_alta_lavanderias_e13_la_lista_muestra_cada_lavanderia_con_su_estado_y_su_plaza(): void
    {
        $otra = $this->plazaDePrueba('Plaza de prueba 2');
        $activa = Lavanderia::factory()->en($this->plaza)->create(['nombre' => 'Lavandería de prueba Activa']);
        $suspendida = Lavanderia::factory()->en($otra)->suspendida()->create(['nombre' => 'Lavandería de prueba Suspendida']);
        $pendiente = Lavanderia::factory()->en($this->plaza)->pendiente()->create(['nombre' => 'Lavandería de prueba Pendiente']);
        $deBaja = Lavanderia::factory()->en($otra)->deBaja()->create(['nombre' => 'Lavandería de prueba de Baja']);
        $this->comoAdministracion();

        Livewire::test(ListLavanderias::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$activa, $suspendida, $pendiente, $deBaja])
            ->assertTableColumnFormattedStateSet('estado', 'Activa', $activa)
            ->assertTableColumnFormattedStateSet('estado', 'Suspendida', $suspendida)
            ->assertTableColumnFormattedStateSet('estado', 'Pendiente', $pendiente)
            ->assertTableColumnFormattedStateSet('estado', 'Dada de baja', $deBaja)
            ->assertTableColumnStateSet('plaza.nombre', 'Plaza de prueba 1', $activa)
            ->assertTableColumnStateSet('plaza.nombre', 'Plaza de prueba 2', $suspendida);

        $this->get('/admin/lavanderias')->assertOk()->assertSee('Lavandería de prueba Activa');
    }

    #[TestDox('alta-lavanderias-E13: la administración cambia el nombre y el horario de una lavandería, y la lavandería los ve en su perfil de la app')]
    public function test_alta_lavanderias_e13_la_administracion_cambia_nombre_y_horario_y_la_lavanderia_los_ve(): void
    {
        $cuenta = $this->cuentaDeLavanderia();
        $lavanderia = $this->registrada($cuenta);
        $this->comoAdministracion();

        // La página abre por su id_publico, con su foto firmada.
        $this->get("/admin/lavanderias/{$lavanderia->id_publico}/edit")->assertOk()->assertSee('Lavandería de prueba Alta');

        Livewire::test(EditLavanderia::class, ['record' => $lavanderia->getRouteKey()])
            ->assertOk()
            ->assertSchemaStateSet([
                'nombre' => 'Lavandería de prueba Alta',
                'direccion.calle' => 'Calle de prueba',
                'direccion.municipio' => $this->municipio->codigo,
            ])
            ->fillForm([
                'nombre' => 'Lavandería de prueba Editada',
                'ventanas' => [
                    ['dia_semana' => 3, 'hora_inicio' => '16:00', 'hora_fin' => '17:00'],
                    ['dia_semana' => 3, 'hora_inicio' => '17:00', 'hora_fin' => '18:00'],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->consultar($cuenta, $lavanderia->id_publico)
            ->assertOk()
            ->assertJsonPath('lavanderia.nombre', 'Lavandería de prueba Editada')
            ->assertJsonPath('lavanderia.ventanas', [
                ['dia_semana' => 3, 'hora_inicio' => '16:00', 'hora_fin' => '17:00'],
                ['dia_semana' => 3, 'hora_inicio' => '17:00', 'hora_fin' => '18:00'],
            ])
            // Lo que no cambió sigue igual.
            ->assertJsonPath('lavanderia.servicios.0.precio', '18.50');
    }

    #[TestDox('alta-lavanderias-E13: la administración cambia la foto, los servicios y la dirección de una lavandería')]
    public function test_alta_lavanderias_e13_la_administracion_cambia_foto_servicios_y_direccion(): void
    {
        $cuenta = $this->cuentaDeLavanderia();
        $lavanderia = $this->registrada($cuenta);
        $fotoAnterior = $lavanderia->fotos()->sole()->ruta;
        $this->geocodificarEn([10.7, -119.3]);
        $this->comoAdministracion();

        Livewire::test(EditLavanderia::class, ['record' => $lavanderia->getRouteKey()])
            ->fillForm([
                'foto' => $this->fotoDePrueba('desde-el-panel.jpg'),
                'direccion.calle' => 'Calle del panel',
                'servicios' => [
                    ['tipo' => 'estandar', 'precio' => '19.00'],
                    ['tipo' => 'planchado', 'precio' => '26.50'],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $lavanderia->refresh();
        $this->assertSame('Calle del panel', $lavanderia->calle);
        $this->assertCount(1, $this->geocodificador->consultas);
        $this->assertNotSame($fotoAnterior, $lavanderia->fotos()->sole()->ruta);
        $this->assertSame([$lavanderia->fotos()->sole()->ruta], $this->archivosDeFotos());

        $this->consultar($cuenta, $lavanderia->id_publico)
            ->assertJsonPath('lavanderia.servicios.*.precio', ['19.00', '26.50']);
    }

    #[TestDox('alta-lavanderias-E13: el panel aplica las validaciones del registro y, si rechaza, no cambia nada')]
    public function test_alta_lavanderias_e13_el_panel_aplica_las_validaciones_del_registro(): void
    {
        $cuenta = $this->cuentaDeLavanderia();
        $lavanderia = $this->registrada($cuenta);
        $antes = $this->consultar($cuenta, $lavanderia->id_publico)->json('lavanderia');
        $this->comoAdministracion();

        Livewire::test(EditLavanderia::class, ['record' => $lavanderia->getRouteKey()])
            ->fillForm([
                'nombre' => '',
                'direccion.codigo_postal' => '123',
                'servicios' => [['tipo' => 'estandar', 'precio' => '-1']],
                'ventanas' => [['dia_semana' => 1, 'hora_inicio' => '10:00', 'hora_fin' => '11:30']],
            ])
            ->call('save')
            ->assertHasFormErrors([
                'nombre',
                'direccion.codigo_postal',
                'servicios.0.precio',
                'ventanas.0.hora_fin',
            ]);

        unset($antes['foto']);
        $despues = $this->consultar($cuenta, $lavanderia->id_publico)->json('lavanderia');
        unset($despues['foto']);
        $this->assertSame($antes, $despues);
    }

    #[TestDox('alta-lavanderias-E13: una lavandería de prueba sin municipio y con franjas largas se edita en lo demás: solo se valida lo que cambia')]
    public function test_alta_lavanderias_e13_una_lavanderia_de_prueba_se_edita_en_lo_demas(): void
    {
        $deprueba = Lavanderia::factory()->en($this->plaza)->create(['nombre' => 'Lavandería de prueba sembrada']);
        $deprueba->horarios()->create(['dia_semana' => 1, 'hora_inicio' => '09:00', 'hora_fin' => '14:00']);
        $this->comoAdministracion();

        Livewire::test(EditLavanderia::class, ['record' => $deprueba->getRouteKey()])
            ->fillForm(['nombre' => 'Lavandería de prueba renombrada'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Lavandería de prueba renombrada', $deprueba->fresh()->nombre);
        $this->assertSame([[1, '09:00:00', '14:00:00']], $deprueba->horarios()->get()
            ->map(fn ($franja): array => [$franja->dia_semana, $franja->hora_inicio, $franja->hora_fin])->all());
        $this->assertSame([], $this->geocodificador->consultas);
    }

    #[TestDox('alta-lavanderias-E13: una dirección nueva fuera de las zonas se rechaza en el panel, junto a la dirección')]
    public function test_alta_lavanderias_e13_una_direccion_fuera_de_las_zonas_se_rechaza_en_el_panel(): void
    {
        $lavanderia = $this->registrada();
        $this->geocodificarEn([30.0, -100.0]);
        $this->comoAdministracion();

        Livewire::test(EditLavanderia::class, ['record' => $lavanderia->getRouteKey()])
            ->fillForm(['direccion.calle' => 'Calle lejana'])
            ->call('save')
            ->assertHasFormErrors(['direccion.calle']);

        $this->assertSame('Calle de prueba', $lavanderia->fresh()->calle);
    }

    #[TestDox('alta-lavanderias-E13: el panel no tiene alta ni borrado de lavanderías: el alta espera DD-053 y la baja no borra')]
    public function test_alta_lavanderias_e13_el_panel_no_tiene_alta_ni_borrado(): void
    {
        $lavanderia = Lavanderia::factory()->en($this->plaza)->create();
        $this->comoAdministracion();

        $this->assertFalse(LavanderiaResource::hasPage('create'));
        Livewire::test(ListLavanderias::class)->assertActionDoesNotExist('create');
        Livewire::test(EditLavanderia::class, ['record' => $lavanderia->getRouteKey()])->assertActionDoesNotExist('delete');
    }

    #[TestDox('alta-lavanderias-E13: una cuenta de socio lavandería o de usuario no ve la lista ni edita en el panel')]
    public function test_alta_lavanderias_e13_una_cuenta_de_otro_rol_no_ve_la_lista_ni_edita(): void
    {
        $lavanderia = Lavanderia::factory()->en($this->plaza)->create(['nombre' => 'Lavandería de prueba Privada']);

        foreach ([Rol::LAVANDERIA, Rol::USUARIO] as $rol) {
            $this->actingAs(Usuario::factory()->conRol($rol)->create(), 'web');

            $this->get('/admin/lavanderias')->assertForbidden()->assertDontSee('Lavandería de prueba Privada');
            $this->get("/admin/lavanderias/{$lavanderia->getRouteKey()}/edit")->assertForbidden();
        }

        $this->assertSame('Lavandería de prueba Privada', $lavanderia->fresh()->nombre);
    }
}
