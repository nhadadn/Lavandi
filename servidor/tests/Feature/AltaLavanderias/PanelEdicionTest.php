<?php

namespace Tests\Feature\AltaLavanderias;

use App\Filament\Resources\Lavanderias\LavanderiaResource;
use App\Filament\Resources\Lavanderias\Pages\EditLavanderia;
use App\Filament\Resources\Lavanderias\Pages\ListLavanderias;
use App\Lavanderias\DireccionNoUbicable;
use App\Lavanderias\FotosDeLaLavanderia;
use App\Models\Lavanderia;
use App\Models\Rol;
use App\Models\Usuario;
use Closure;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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
 * página de alta. Una dirección nueva en la zona de otra plaza se rechaza, como en la app: la lavandería no cambia de
 * plaza. La descripción y las fotos del perfil se editan con las reglas de la app (alta-lavanderias-E31).
 *
 * Escenarios: alta-lavanderias-E13, alta-lavanderias-E25 y alta-lavanderias-E31 · Tareas 3.1, 2.3 y 3.4 · Spec:
 * openspec/changes/alta-de-lavanderias/specs/alta-lavanderias/spec.md
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
                // La foto del registro se cambia en la lista de fotos (alta-lavanderias-E31).
                'fotos' => [$this->fotoDePrueba('desde-el-panel.jpg')],
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

        unset($antes['foto'], $antes['fotos']);
        $despues = $this->consultar($cuenta, $lavanderia->id_publico)->json('lavanderia');
        unset($despues['foto'], $despues['fotos']);
        $this->assertSame($antes, $despues);
    }

    #[TestDox('alta-lavanderias-E31: la administración cambia la descripción y las fotos desde el panel: quita una, cambia el orden y agrega otra al final, y la lavandería y el usuario lo ven')]
    public function test_alta_lavanderias_e31_el_panel_edita_la_descripcion_y_las_fotos(): void
    {
        $cuenta = $this->cuentaDeLavanderia();
        $lavanderia = $this->registrada($cuenta);
        [$primera, $segunda, $tercera] = $this->conFotos($lavanderia, 3);
        $this->comoAdministracion();

        Livewire::test(EditLavanderia::class, ['record' => $lavanderia->getRouteKey()])
            ->assertOk()
            ->assertSchemaStateSet(['descripcion' => null])
            ->fillForm([
                'descripcion' => 'Descripción de prueba desde el panel.',
                'fotos' => [$tercera, $primera],
            ])
            // La nueva se sube al final de la lista, como la agrega el selector (appendFiles()).
            ->set('data.fotos.nueva', $this->fotoDePrueba('desde-el-panel.jpg'))
            ->call('save')
            ->assertHasNoFormErrors();

        $rutas = $this->rutasEnOrden($lavanderia);
        $this->assertCount(3, $rutas);
        $this->assertSame([$tercera, $primera], array_slice($rutas, 0, 2));
        $this->assertSame([1, 2, 3], $lavanderia->fotos()->orderBy('orden')->pluck('orden')->all());
        // La que se quitó se borra del almacenamiento.
        $this->assertEqualsCanonicalizing($rutas, $this->archivosDeFotos());
        $this->assertNotContains($segunda, $this->archivosDeFotos());

        $this->consultar($cuenta, $lavanderia->id_publico)
            ->assertJsonPath('lavanderia.descripcion', 'Descripción de prueba desde el panel.');
        $perfil = $this->perfilDelUsuario($lavanderia)
            ->assertJsonPath('lavanderia.descripcion', 'Descripción de prueba desde el panel.');
        $this->assertSame($rutas, $this->rutasDe($perfil->json('lavanderia.fotos')));
    }

    #[TestDox('alta-lavanderias-E31: el panel borra la descripción y, si no cambia las fotos, no las toca')]
    public function test_alta_lavanderias_e31_el_panel_borra_la_descripcion_sin_tocar_las_fotos(): void
    {
        $lavanderia = $this->registrada();
        $lavanderia->update(['descripcion' => 'Descripción de prueba anterior.']);
        $antes = $this->conFotos($lavanderia, 2);
        $this->comoAdministracion();

        Livewire::test(EditLavanderia::class, ['record' => $lavanderia->getRouteKey()])
            ->assertSchemaStateSet(['descripcion' => 'Descripción de prueba anterior.'])
            ->fillForm(['descripcion' => ''])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($lavanderia->fresh()->descripcion);
        $this->assertSame($antes, $this->rutasEnOrden($lavanderia));
        $this->assertEqualsCanonicalizing($antes, $this->archivosDeFotos());
    }

    #[TestDox('alta-lavanderias-E31: el panel aplica las reglas de la descripción y de las fotos: sin fotos, con más del máximo o con una descripción demasiado larga se rechaza junto a su campo y nada cambia')]
    public function test_alta_lavanderias_e31_el_panel_aplica_las_reglas_de_la_descripcion_y_las_fotos(): void
    {
        config(['fotos.maximo_por_lavanderia' => 2]);
        $lavanderia = $this->registrada();
        $antes = $this->conFotos($lavanderia, 2);
        $this->comoAdministracion();

        Livewire::test(EditLavanderia::class, ['record' => $lavanderia->getRouteKey()])
            ->fillForm(['fotos' => []])
            ->call('save')
            ->assertHasFormErrors(['fotos' => 'La lavandería necesita al menos una foto.']);

        // El selector no deja subir más que el máximo, y su propia regla lo dice con el mensaje de la edición.
        Livewire::test(EditLavanderia::class, ['record' => $lavanderia->getRouteKey()])
            ->fillForm(['fotos' => [...$antes, $this->fotoDePrueba('una-de-mas.jpg')]])
            ->call('save')
            ->assertHasFormErrors(['fotos' => 'La lavandería puede tener hasta 2 fotos.']);

        Livewire::test(EditLavanderia::class, ['record' => $lavanderia->getRouteKey()])
            ->fillForm(['descripcion' => str_repeat('a', 1001)])
            ->call('save')
            ->assertHasFormErrors(['descripcion' => 'La descripción puede tener hasta 1000 caracteres.']);

        $this->assertNull($lavanderia->fresh()->descripcion);
        $this->assertSame($antes, $this->rutasEnOrden($lavanderia));
        $this->assertEqualsCanonicalizing($antes, $this->archivosDeFotos());
    }

    #[TestDox('alta-lavanderias-E31: con el máximo por debajo de las fotos que ya tiene, el panel guarda los demás datos, y una lista que cambia se rechaza con el mensaje del máximo')]
    public function test_alta_lavanderias_e31_con_el_maximo_por_debajo_de_sus_fotos_el_panel_edita_lo_demas(): void
    {
        $lavanderia = $this->registrada();
        [$primera, $segunda, $tercera] = $this->conFotos($lavanderia, 3);
        config(['fotos.maximo_por_lavanderia' => 2]);
        $this->comoAdministracion();

        Livewire::test(EditLavanderia::class, ['record' => $lavanderia->getRouteKey()])
            ->fillForm(['nombre' => 'Lavandería de prueba Renombrada'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Lavandería de prueba Renombrada', $lavanderia->fresh()->nombre);
        $this->assertSame([$primera, $segunda, $tercera], $this->rutasEnOrden($lavanderia));

        // La lista que arma la página pasa por la regla del máximo de la edición, como en la app.
        Livewire::test(EditLavanderia::class, ['record' => $lavanderia->getRouteKey()])
            ->fillForm(['fotos' => [$tercera, $primera, $segunda]])
            ->call('save')
            ->assertHasFormErrors(['fotos' => 'La lavandería puede tener hasta 2 fotos.']);

        $this->assertSame([$primera, $segunda, $tercera], $this->rutasEnOrden($lavanderia));
        $this->assertEqualsCanonicalizing([$primera, $segunda, $tercera], $this->archivosDeFotos());
    }

    #[TestDox('alta-lavanderias-E31: con el máximo por debajo de sus fotos, si la app quita una mientras el panel está abierto, cambiar solo el nombre se guarda y las fotos quedan como las dejó la app, sin el mensaje del máximo')]
    public function test_alta_lavanderias_e31_fotos_que_cambian_con_el_panel_abierto_y_el_maximo_por_debajo(): void
    {
        $lavanderia = $this->registrada();
        [$primera, $segunda, $tercera] = $this->conFotos($lavanderia, 3);
        config(['fotos.maximo_por_lavanderia' => 2]);
        $this->comoAdministracion();

        $pagina = Livewire::test(EditLavanderia::class, ['record' => $lavanderia->getRouteKey()]);

        // La app quita una foto mientras la página sigue abierta.
        $lavanderia->fotos()->where('ruta', $tercera)->delete();

        $pagina->fillForm(['nombre' => 'Lavandería de prueba Renombrada'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Lavandería de prueba Renombrada', $lavanderia->fresh()->nombre);
        $this->assertSame([$primera, $segunda], $this->rutasEnOrden($lavanderia));
    }

    #[TestDox('alta-lavanderias-E31: una foto cuyo archivo no está en el disco se queda, con su lugar, cuando la administración cambia solo el nombre; sin ningún archivo, el nombre también se guarda')]
    public function test_alta_lavanderias_e31_una_foto_sin_su_archivo_se_queda_al_cambiar_solo_el_nombre(): void
    {
        $lavanderia = $this->registrada();
        $antes = $this->conFotos($lavanderia, 3);
        $disco = Storage::disk(config('fotos.disco'));
        $disco->delete($antes[1]);
        $this->comoAdministracion();

        Livewire::test(EditLavanderia::class, ['record' => $lavanderia->getRouteKey()])
            ->fillForm(['nombre' => 'Lavandería de prueba Renombrada'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Lavandería de prueba Renombrada', $lavanderia->fresh()->nombre);
        $this->assertSame($antes, $this->rutasEnOrden($lavanderia));
        $this->assertSame([1, 2, 3], $lavanderia->fotos()->orderBy('orden')->pluck('orden')->all());

        $disco->delete($antes);

        Livewire::test(EditLavanderia::class, ['record' => $lavanderia->getRouteKey()])
            ->fillForm(['nombre' => 'Lavandería de prueba sin archivos'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Lavandería de prueba sin archivos', $lavanderia->fresh()->nombre);
        $this->assertSame($antes, $this->rutasEnOrden($lavanderia));
    }

    #[TestDox('alta-lavanderias-E31: los lugares de las fotos se cuentan sobre las que el panel leyó al abrir: si cambiaron después, guardar otro dato no las toca y una lista cambiada se rechaza sin cambiar nada')]
    public function test_alta_lavanderias_e31_el_panel_no_pisa_las_fotos_que_cambiaron_despues_de_abrir(): void
    {
        $cuenta = $this->cuentaDeLavanderia();
        $lavanderia = $this->registrada($cuenta);
        [$primera, $segunda] = $this->conFotos($lavanderia, 2);
        $this->comoAdministracion();

        $soloElNombre = Livewire::test(EditLavanderia::class, ['record' => $lavanderia->getRouteKey()]);
        $lasFotos = Livewire::test(EditLavanderia::class, ['record' => $lavanderia->getRouteKey()]);

        // Con las dos páginas abiertas, la lavandería agrega una foto desde la app.
        $tercera = $this->conFotos($lavanderia, 3)[2];

        $soloElNombre
            ->fillForm(['nombre' => 'Lavandería de prueba Renombrada'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([$primera, $segunda, $tercera], $this->rutasEnOrden($lavanderia));

        $lasFotos
            ->fillForm(['fotos' => [$segunda, $primera]])
            ->call('save')
            ->assertHasFormErrors(['fotos' => FotosDeLaLavanderia::FOTOS_QUE_CAMBIARON]);

        $this->assertSame([$primera, $segunda, $tercera], $this->rutasEnOrden($lavanderia));
        $this->assertEqualsCanonicalizing([$primera, $segunda, $tercera], $this->archivosDeFotos());
        $this->consultar($cuenta, $lavanderia->id_publico)
            ->assertJsonPath('lavanderia.nombre', 'Lavandería de prueba Renombrada')
            ->assertJsonCount(3, 'lavanderia.fotos');
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

    #[TestDox('alta-lavanderias-E25: desde el panel, una dirección nueva en la zona de otra plaza se rechaza junto a la dirección y la lavandería conserva su plaza')]
    public function test_alta_lavanderias_e25_desde_el_panel_una_direccion_en_otra_plaza_se_rechaza(): void
    {
        $lavanderia = $this->registrada();
        $ubicacion = $this->ubicacionDe($lavanderia);
        $otra = $this->plazaDePrueba('Plaza de prueba 2');
        $this->zonaDePrueba($otra, 'Zona de prueba Norte', $this->cuadrado(-116.0, 10.0));
        $this->geocodificarEn([10.5, -115.5]);
        $this->comoAdministracion();

        Livewire::test(EditLavanderia::class, ['record' => $lavanderia->getRouteKey()])
            ->fillForm(['direccion.calle' => 'Calle de otra plaza'])
            ->call('save')
            ->assertHasFormErrors(['direccion.calle' => DireccionNoUbicable::MENSAJES[DireccionNoUbicable::EN_OTRA_PLAZA]]);

        $lavanderia->refresh();
        $this->assertSame([$this->plaza->getKey(), 'Calle de prueba'], [$lavanderia->id_plazas, $lavanderia->calle]);
        $this->assertSame($ubicacion, $this->ubicacionDe($lavanderia));
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
