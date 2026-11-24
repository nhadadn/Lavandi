<?php

namespace Tests\Feature\Administracion;

use App\Autorizacion\Autorizacion;
use App\Comisiones\PorcentajesDeComision;
use App\Filament\Pages\ComisionesPorLavanderia;
use App\Filament\Pages\ComisionGlobal;
use App\Models\Comision;
use App\Models\Lavanderia;
use App\Models\Plaza;
use App\Models\Rol;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\AltaLavanderias\Soporte\PanelDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * Las comisiones del panel: un porcentaje global, uno solo para todas las plazas, y uno propio para cada lavandería, que
 * prevalece sobre el global; sin ningún porcentaje de fábrica y siempre entre 0 y 100 (NSV-2026-001-v8.pdf:19:ANEXO A,
 * Sección A.3, Módulo D #5, "Porcentaje de comisión por socio o global"; design.md, lectura 2 y Open Questions 4).
 *
 * El global lo fija solo la administración, que es una para todas las plazas: el contrato no nombra una administración
 * por plaza (openspec/changes/autorizacion-por-rol/design.md, Non-Goals).
 *
 * Un cambio de porcentaje es una fila nueva en comisiones y la anterior queda como historial, con su fin de vigencia. El
 * cálculo de la comisión de cada pedido espera la base que fije EL CLIENTE (DD-020 y DD-013, administracion-E15 y E16), y
 * la del repartidor, DD-019 (administracion-E14).
 *
 * Los porcentajes son de prueba: los de producción los captura EL CLIENTE.
 *
 * Escenarios: administracion-E11, administracion-E12 y administracion-E13 · Spec:
 * openspec/changes/administracion/specs/administracion/spec.md · Tarea 3.1
 */
class ConfiguracionDeComisionesTest extends TestCase
{
    use PanelDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    private const FUERA_DE_RANGO = 'El porcentaje va de 0 a 100.';

    private Plaza $plaza;

    private Lavanderia $primera;

    private Lavanderia $segunda;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-12-01 12:00:00', 'UTC'));
        $this->plaza = $this->plazaDePrueba('Plaza de prueba 1');
        $this->primera = Lavanderia::factory()->en($this->plaza)->create(['nombre' => 'Lavandería de prueba A']);
        $this->segunda = Lavanderia::factory()->en($this->plaza)->create(['nombre' => 'Lavandería de prueba B']);
    }

    #[TestDox('administracion-E11: sin porcentaje global guardado no aplica ninguno: el sistema no trae porcentajes de fábrica')]
    public function test_administracion_e11_sin_porcentaje_guardado_no_aplica_ninguno(): void
    {
        $this->assertSame(0, Comision::count());
        $this->assertNull($this->porcentajes()->globalVigente());
        $this->assertNull($this->porcentajes()->queAplica($this->primera));
        $this->comoAdministracion();

        Livewire::test(ComisionGlobal::class)
            ->assertSeeText('Sin porcentaje')
            ->assertActionVisible('fijarPorcentaje');
        Livewire::test(ComisionesPorLavanderia::class)
            ->assertTableColumnFormattedStateSet('aplica', 'Sin porcentaje', $this->primera);
    }

    #[TestDox('administracion-E11: al guardar en el panel el porcentaje global, aplica a toda lavandería sin porcentaje propio, de cualquier plaza: es uno solo para todo el sistema, sin plaza')]
    public function test_administracion_e11_el_porcentaje_global_aplica_a_las_lavanderias_sin_porcentaje_propio(): void
    {
        $deOtraPlaza = Lavanderia::factory()->en($this->plazaDePrueba('Plaza de prueba 2'))->create();
        $this->comoAdministracion();

        Livewire::test(ComisionGlobal::class)
            ->callAction('fijarPorcentaje', data: ['porcentaje' => '12.5'])
            ->assertHasNoActionErrors()
            ->assertSeeText('12.50 %');

        foreach ([$this->primera, $this->segunda, $deOtraPlaza] as $lavanderia) {
            $aplica = $this->porcentajes()->queAplica($lavanderia);
            $this->assertSame('12.50', $aplica?->porcentaje);
            $this->assertNull($aplica->id_lavanderias, 'Es la global.');
        }

        $global = Comision::sole();
        $this->assertSame([null, null, '12.50', '2026-12-01T12:00:00Z', null], [
            $global->id_plazas,
            $global->id_lavanderias,
            $global->porcentaje,
            $global->vigente_desde->toIso8601ZuluString(),
            $global->vigente_hasta,
        ]);
        $this->assertTrue($global->is($this->porcentajes()->globalVigente()));

        Livewire::test(ComisionesPorLavanderia::class)
            ->assertTableColumnFormattedStateSet('aplica', '12.50 % (global)', $this->primera)
            ->assertTableColumnFormattedStateSet('aplica', '12.50 % (global)', $deOtraPlaza)
            ->assertTableColumnFormattedStateSet('propio', 'Sin porcentaje propio', $this->primera);
    }

    #[TestDox('administracion-E11: el propio de una lavandería no cambia lo que aplica a las de otra plaza: a ellas les sigue aplicando el global')]
    public function test_administracion_e11_el_propio_de_una_lavanderia_no_cambia_el_global_de_otra_plaza(): void
    {
        $deOtraPlaza = Lavanderia::factory()->en($this->plazaDePrueba('Plaza de prueba 2'))->create();
        $this->porcentajes()->fijarGlobal('10');

        $this->travelTo(CarbonImmutable::parse('2026-12-02 12:00:00', 'UTC'));
        $this->porcentajes()->fijarPropio($deOtraPlaza, '6');

        $this->assertSame('6.00', $this->porcentajes()->queAplica($deOtraPlaza)?->porcentaje);
        $this->assertSame($deOtraPlaza->id_plazas, $this->porcentajes()->propioVigente($deOtraPlaza)?->id_plazas);
        foreach ([$this->primera, $this->segunda] as $lavanderia) {
            $this->assertSame('10.00', $this->porcentajes()->queAplica($lavanderia)?->porcentaje);
        }
    }

    #[TestDox('administracion-E12: con un porcentaje global guardado, al guardar uno propio para una lavandería le aplica el suyo y a las demás el global; al quitarlo, le vuelve a aplicar el global')]
    public function test_administracion_e12_el_porcentaje_propio_prevalece_y_al_quitarlo_vuelve_el_global(): void
    {
        $this->porcentajes()->fijarGlobal('10');
        $this->comoAdministracion();

        $this->travelTo(CarbonImmutable::parse('2026-12-02 12:00:00', 'UTC'));
        Livewire::test(ComisionesPorLavanderia::class)
            ->callTableAction('fijarPropio', $this->primera, data: ['porcentaje' => '7.25'])
            ->assertHasNoTableActionErrors()
            ->assertTableColumnFormattedStateSet('propio', '7.25 %', $this->primera)
            ->assertTableColumnFormattedStateSet('aplica', '7.25 % (propio)', $this->primera)
            ->assertTableColumnFormattedStateSet('aplica', '10.00 % (global)', $this->segunda);

        $this->assertSame('7.25', $this->porcentajes()->queAplica($this->primera)?->porcentaje);
        $this->assertSame('10.00', $this->porcentajes()->queAplica($this->segunda)?->porcentaje);

        $this->travelTo(CarbonImmutable::parse('2026-12-03 12:00:00', 'UTC'));
        Livewire::test(ComisionesPorLavanderia::class)
            ->assertTableActionVisible('quitarPropio', $this->primera)
            ->assertTableActionHidden('quitarPropio', $this->segunda)
            ->callTableAction('quitarPropio', $this->primera)
            ->assertHasNoTableActionErrors()
            ->assertTableColumnFormattedStateSet('aplica', '10.00 % (global)', $this->primera);

        $this->assertSame('10.00', $this->porcentajes()->queAplica($this->primera)?->porcentaje);
        $this->assertNull($this->porcentajes()->propioVigente($this->primera));

        // El propio que se quitó queda como historial, con su vigencia: el 2 de diciembre le aplicaba.
        $propio = Comision::query()->where('id_lavanderias', $this->primera->getKey())->sole();
        $this->assertSame('2026-12-03T12:00:00Z', $propio->vigente_hasta?->toIso8601ZuluString());
        $this->assertSame('7.25', $this->porcentajes()->queAplica($this->primera, CarbonImmutable::parse('2026-12-02 18:00:00', 'UTC'))?->porcentaje);
    }

    #[TestDox('administracion-E12: un cambio de porcentaje es una fila nueva y la anterior queda como historial con su fin de vigencia, en el global y en el propio')]
    public function test_administracion_e12_un_cambio_de_porcentaje_cierra_el_anterior_y_agrega_otro(): void
    {
        $this->porcentajes()->fijarGlobal('10');
        $this->porcentajes()->fijarPropio($this->primera, '8');

        $this->travelTo(CarbonImmutable::parse('2026-12-05 12:00:00', 'UTC'));
        $this->comoAdministracion();
        Livewire::test(ComisionGlobal::class)->callAction('fijarPorcentaje', data: ['porcentaje' => '11']);
        Livewire::test(ComisionesPorLavanderia::class)->callTableAction('fijarPropio', $this->primera, data: ['porcentaje' => '9']);

        $this->assertSame(4, Comision::count());
        $this->assertSame('11.00', $this->porcentajes()->globalVigente()?->porcentaje);
        $this->assertSame('9.00', $this->porcentajes()->propioVigente($this->primera)?->porcentaje);

        $antes = CarbonImmutable::parse('2026-12-03 00:00:00', 'UTC');
        $this->assertSame('10.00', $this->porcentajes()->globalVigente($antes)?->porcentaje);
        $this->assertSame('8.00', $this->porcentajes()->propioVigente($this->primera, $antes)?->porcentaje);

        foreach (Comision::query()->whereIn('porcentaje', ['10.00', '8.00'])->get() as $anterior) {
            $this->assertSame('2026-12-05T12:00:00Z', $anterior->vigente_hasta?->toIso8601ZuluString());
        }

        // Dos cambios en el mismo instante no dejan una vigencia que nunca rigió: el segundo reemplaza al primero.
        $this->porcentajes()->fijarGlobal('12');
        $this->assertSame(4, Comision::count());
        $this->assertSame('12.00', $this->porcentajes()->globalVigente()?->porcentaje);
    }

    #[TestDox('administracion-E13: con un porcentaje vigente, el panel rechaza con un mensaje uno menor que 0 o mayor que 100, en el global y en el propio, y el vigente no cambia')]
    public function test_administracion_e13_el_panel_rechaza_un_porcentaje_fuera_de_rango(): void
    {
        $this->porcentajes()->fijarGlobal('10');
        $this->porcentajes()->fijarPropio($this->primera, '8');
        $this->comoAdministracion();

        foreach (['-1', '-0.01', '100.01', '150'] as $porcentaje) {
            Livewire::test(ComisionGlobal::class)
                ->callAction('fijarPorcentaje', data: ['porcentaje' => $porcentaje])
                ->assertHasActionErrors(['porcentaje' => self::FUERA_DE_RANGO]);

            Livewire::test(ComisionesPorLavanderia::class)
                ->callTableAction('fijarPropio', $this->primera, data: ['porcentaje' => $porcentaje])
                ->assertHasTableActionErrors(['porcentaje' => self::FUERA_DE_RANGO]);
        }

        $this->assertSame(2, Comision::count());
        $this->assertSame('10.00', $this->porcentajes()->globalVigente()?->porcentaje);
        $this->assertSame('8.00', $this->porcentajes()->propioVigente($this->primera)?->porcentaje);
    }

    #[TestDox('administracion-E13: la regla vive en las comisiones y no solo en el panel: sin porcentaje, con texto, con más de dos decimales o fuera de 0 a 100 se rechaza; 0 y 100 entran')]
    public function test_administracion_e13_la_regla_del_porcentaje_vive_en_las_comisiones(): void
    {
        foreach ([null, '', 'diez', '12.345', '-1', '100.5'] as $porcentaje) {
            try {
                $this->porcentajes()->fijarGlobal($porcentaje);
                $this->fail('Se guardó un porcentaje que no vale: '.var_export($porcentaje, true));
            } catch (ValidationException $error) {
                $this->assertArrayHasKey('porcentaje', $error->errors());
            }
        }

        $this->assertSame(0, Comision::count());

        $this->porcentajes()->fijarGlobal('0');
        $this->assertSame('0.00', $this->porcentajes()->globalVigente()?->porcentaje);
        $this->travelTo(CarbonImmutable::parse('2026-12-02 12:00:00', 'UTC'));
        $this->porcentajes()->fijarPropio($this->primera, 100);
        $this->assertSame('100.00', $this->porcentajes()->queAplica($this->primera)?->porcentaje);
    }

    #[TestDox('administracion-E11 a E13: solo la administración, que es una para todas las plazas, fija la comisión global; otra cuenta, también la del socio de una lavandería de una plaza, no abre las páginas de comisiones y el componente no le da ninguna lavandería')]
    public function test_administracion_e11_a_e13_solo_la_administracion_fija_comisiones(): void
    {
        $autorizacion = app(Autorizacion::class);

        foreach ([Rol::USUARIO, Rol::LAVANDERIA, 'repartidor'] as $rol) {
            $cuenta = Usuario::factory()->conRol($rol)->create();
            $this->actingAs($cuenta, 'web');

            $this->get('/admin/comision-global')->assertForbidden();
            $this->get('/admin/comision-por-lavanderia')->assertForbidden();
            $this->assertFalse($autorizacion->puedeFijarLaComisionGlobal($cuenta), $rol);
            $this->assertSame(0, $autorizacion->lavanderiasDelPanel($cuenta)->count(), $rol);
        }

        $socio = $this->primera->administrador;
        $this->assertFalse($autorizacion->puedeFijarLaComisionGlobal($socio));
        $this->actingAs($socio, 'web')->get('/admin/comision-global')->assertForbidden();
        $this->actingAs($socio, 'web')->get('/admin/comision-por-lavanderia')->assertForbidden();

        $bloqueada = Usuario::factory()->conRol(Rol::ADMINISTRACION)->create(['estado' => Usuario::BLOQUEADO]);
        $this->assertFalse($autorizacion->puedeFijarLaComisionGlobal($bloqueada), 'Una cuenta bloqueada no fija nada.');

        $administracion = $this->comoAdministracion();
        $this->assertTrue($autorizacion->puedeFijarLaComisionGlobal($administracion));
        $this->get('/admin/comision-global')->assertOk()->assertSee('Sin porcentaje');
        $this->get('/admin/comision-por-lavanderia')->assertOk()->assertSee($this->primera->nombre);
        $this->assertSame(0, Comision::count());
    }

    private function porcentajes(): PorcentajesDeComision
    {
        return app(PorcentajesDeComision::class);
    }
}
