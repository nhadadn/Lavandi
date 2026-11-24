<?php

namespace Tests\Feature\Landing;

use App\Autorizacion\Autorizacion;
use App\Filament\Resources\Testimonios\Pages\CreateTestimonio;
use App\Filament\Resources\Testimonios\Pages\EditTestimonio;
use App\Filament\Resources\Testimonios\Pages\ListTestimonios;
use App\Filament\Resources\Testimonios\TestimonioResource;
use App\Models\Rol;
use App\Models\Testimonio;
use App\Models\Usuario;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\AltaLavanderias\Soporte\PanelDePrueba;
use Tests\Feature\Landing\Soporte\Portada;
use Tests\TestCase;

/**
 * "Testimonios": de 3 a 5 tarjetas de opiniones de usuarios beta, con marcador de posición en el lanzamiento, editables
 * desde el panel con su interruptor de activo y su orden, solo por quien tenga permiso de administración
 * (NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2, sección 8; ADR-0011; design.md, lectura 2).
 *
 * Quién edita lo decide el componente único de autorización (App\Autorizacion\Autorizacion), con el rol: los
 * testimonios no son de una plaza. El texto del marcador lo decide EL CLIENTE (DD-117). Los testimonios de aquí son
 * inventados.
 *
 * Escenarios: landing-E15, landing-E16, landing-E19 y landing-E26 · Spec: openspec/changes/landing/specs/landing/spec.md ·
 * Tarea 4.1
 */
class TestimoniosTest extends TestCase
{
    use PanelDePrueba;
    use Portada;
    use RefreshDatabase;

    /** Los roles que no son de administración; repartidor todavía no tiene constante en Rol. */
    private const OTROS_ROLES = [Rol::USUARIO, Rol::LAVANDERIA, 'repartidor'];

    /**
     * Las tarjetas de testimonios de la portada: autor y texto de cada una, o "marcador" si es de posición.
     *
     * @return list<string>
     */
    private function tarjetas(): array
    {
        $portada = $this->portada();

        return array_map(
            fn ($tarjeta): string => $tarjeta->hasAttribute('data-marcador')
                ? 'marcador'
                : $this->textos($portada, 'testimonios', sprintf('.//*[@data-testimonio][%d]//*[@data-autor]', 1 + count($this->anteriores($tarjeta))))[0],
            $this->en($portada, 'testimonios', './/*[@data-testimonio]'),
        );
    }

    /**
     * @return list<\DOMNode>
     */
    private function anteriores(\DOMElement $tarjeta): array
    {
        $anteriores = [];
        for ($nodo = $tarjeta->previousSibling; $nodo !== null; $nodo = $nodo->previousSibling) {
            if ($nodo instanceof \DOMElement && $nodo->hasAttribute('data-testimonio')) {
                $anteriores[] = $nodo;
            }
        }

        return $anteriores;
    }

    #[TestDox('landing-E15: con seis testimonios activos y uno inactivo, se muestran cinco tarjetas de activos, en el orden fijado en el panel, y el inactivo no aparece')]
    public function test_landing_e15_solo_los_activos_hasta_cinco_en_su_orden(): void
    {
        foreach ([6 => 'F', 2 => 'B', 4 => 'D', 1 => 'A', 5 => 'E', 3 => 'C'] as $orden => $letra) {
            Testimonio::factory()->activo()->create(['autor' => "Persona de prueba {$letra}", 'orden' => $orden]);
        }
        Testimonio::factory()->create(['autor' => 'Persona de prueba inactiva', 'orden' => 0]);

        $this->assertSame(
            ['Persona de prueba A', 'Persona de prueba B', 'Persona de prueba C', 'Persona de prueba D', 'Persona de prueba E'],
            $this->tarjetas(),
        );

        $portada = $this->portada();
        $this->assertSame([], $this->en($portada, 'testimonios', './/*[@data-marcador]'));
        $this->assertStringNotContainsString('Persona de prueba inactiva', $this->texto($this->en($portada, 'testimonios')[0]));
    }

    #[TestDox('landing-E15: cada tarjeta muestra el texto y el autor del testimonio')]
    public function test_landing_e15_cada_tarjeta_muestra_texto_y_autor(): void
    {
        Testimonio::factory()->activo()->create(['autor' => 'Persona de prueba', 'texto' => 'Una opinión de prueba.']);
        $portada = $this->portada();

        $this->assertSame(['Una opinión de prueba.'], $this->textos($portada, 'testimonios', './/*[@data-testimonio][not(@data-marcador)]//*[@data-texto]'));
        $this->assertSame(['Persona de prueba'], $this->textos($portada, 'testimonios', './/*[@data-testimonio][not(@data-marcador)]//*[@data-autor]'));
    }

    #[TestDox('landing-E16: con menos de tres testimonios activos, la sección completa tres tarjetas con marcadores de posición')]
    public function test_landing_e16_con_menos_de_tres_completa_con_marcadores(): void
    {
        $this->assertSame(['marcador', 'marcador', 'marcador'], $this->tarjetas());

        Testimonio::factory()->activo()->create(['autor' => 'Persona de prueba A', 'orden' => 1]);
        Testimonio::factory()->create(['autor' => 'Persona de prueba inactiva']);
        $this->assertSame(['Persona de prueba A', 'marcador', 'marcador'], $this->tarjetas());

        Testimonio::factory()->activo()->create(['autor' => 'Persona de prueba B', 'orden' => 2]);
        $this->assertSame(['Persona de prueba A', 'Persona de prueba B', 'marcador'], $this->tarjetas());

        Testimonio::factory()->activo()->count(2)->create(['orden' => 3]);
        $this->assertCount(4, $this->tarjetas());
        $this->assertNotContains('marcador', $this->tarjetas(), 'Con tres o más activos no hay marcadores.');
    }

    #[TestDox('landing-E16: el marcador de posición muestra el texto que se configure, y sin él, su marcador de contenido de prueba')]
    public function test_landing_e16_el_marcador_muestra_el_texto_configurado(): void
    {
        config(['landing.testimonios.marcador' => 'Texto de prueba del marcador de posición']);
        $this->assertSame(
            array_fill(0, 3, 'Texto de prueba del marcador de posición'),
            $this->textos($this->portada(), 'testimonios', './/*[@data-marcador]'),
        );

        config(['landing.testimonios.marcador' => null]);
        $portada = $this->portada();
        $this->assertCount(3, $this->en($portada, 'testimonios', './/*[@data-marcador]//*[@data-de-prueba]'));
    }

    #[TestDox('landing-E15: la administración crea, edita, activa y ordena los testimonios desde el panel, y la portada lo refleja')]
    public function test_landing_e15_la_administracion_edita_los_testimonios_desde_el_panel(): void
    {
        $this->comoAdministracion();
        $this->get('/admin/testimonios')->assertOk();
        $this->get('/admin/testimonios/create')->assertOk();

        Livewire::test(CreateTestimonio::class)
            ->fillForm(['autor' => 'Persona de prueba', 'texto' => 'Opinión de prueba.', 'activo' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $testimonio = Testimonio::sole();
        $this->assertSame(['Persona de prueba', 'Opinión de prueba.', true], [$testimonio->autor, $testimonio->texto, $testimonio->activo]);
        $this->assertSame(['Persona de prueba', 'marcador', 'marcador'], $this->tarjetas());

        Livewire::test(EditTestimonio::class, ['record' => $testimonio->getRouteKey()])
            ->fillForm(['autor' => 'Persona de prueba editada'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('Persona de prueba editada', $testimonio->refresh()->autor);

        $otro = Testimonio::factory()->activo()->create(['autor' => 'Persona de prueba segunda', 'orden' => 5]);
        Livewire::test(ListTestimonios::class)
            ->assertCanSeeTableRecords([$testimonio, $otro])
            ->call('reorderTable', [(string) $otro->getKey(), (string) $testimonio->getKey()]);
        $this->assertSame(['Persona de prueba segunda', 'Persona de prueba editada', 'marcador'], $this->tarjetas());

        Livewire::test(ListTestimonios::class)->call('updateTableColumnState', 'activo', (string) $otro->getKey(), false);
        $this->assertFalse($otro->refresh()->activo);
        $this->assertSame(['Persona de prueba editada', 'marcador', 'marcador'], $this->tarjetas());
    }

    #[TestDox('landing-E26: la administración borra un testimonio activo desde la lista y otro desde su edición: dejan de existir, la portada ya no los muestra y su lugar lo toma el siguiente activo o un marcador de posición')]
    public function test_landing_e26_la_administracion_borra_un_testimonio_desde_el_panel(): void
    {
        $primero = Testimonio::factory()->activo()->create(['autor' => 'Persona de prueba uno', 'orden' => 1]);
        $segundo = Testimonio::factory()->activo()->create(['autor' => 'Persona de prueba dos', 'orden' => 2]);
        $tercero = Testimonio::factory()->activo()->create(['autor' => 'Persona de prueba tres', 'orden' => 3]);
        $this->assertSame(['Persona de prueba uno', 'Persona de prueba dos', 'Persona de prueba tres'], $this->tarjetas());
        $this->comoAdministracion();

        Livewire::test(ListTestimonios::class)
            ->assertTableActionVisible('delete', $primero)
            ->callTableAction('delete', $primero)
            ->assertHasNoTableActionErrors()
            ->assertCanNotSeeTableRecords([$primero]);

        $this->assertModelMissing($primero);
        $this->assertSame(['Persona de prueba dos', 'Persona de prueba tres', 'marcador'], $this->tarjetas());

        Livewire::test(EditTestimonio::class, ['record' => $segundo->getRouteKey()])
            ->callAction('delete')
            ->assertHasNoActionErrors();

        $this->assertModelMissing($segundo);
        $this->assertModelExists($tercero);
        $this->assertSame(['Persona de prueba tres', 'marcador', 'marcador'], $this->tarjetas());
    }

    #[TestDox('landing-E26: sin permiso de administración no se borra ningún testimonio: el recurso no deja borrar y la portada no cambia')]
    public function test_landing_e26_sin_permiso_no_se_borra_ningun_testimonio(): void
    {
        Filament::setCurrentPanel('admin');
        $testimonio = Testimonio::factory()->activo()->create(['autor' => 'Persona de prueba activa', 'orden' => 1]);
        $antes = $this->tarjetas();

        foreach (self::OTROS_ROLES as $rol) {
            $this->actingAs(Usuario::factory()->conRol($rol)->create(), 'web');

            $this->assertFalse(TestimonioResource::canDelete($testimonio), $rol);
            $this->assertFalse(TestimonioResource::canDeleteAny(), $rol);
            Livewire::test(ListTestimonios::class)->assertForbidden();
        }

        $this->assertModelExists($testimonio);
        $this->assertSame($antes, $this->tarjetas());
    }

    #[TestDox('landing-E16: el panel no deja guardar un testimonio sin autor o sin texto')]
    public function test_landing_e16_el_panel_no_guarda_un_testimonio_incompleto(): void
    {
        $this->comoAdministracion();

        Livewire::test(CreateTestimonio::class)
            ->fillForm(['autor' => '', 'texto' => '   '])
            ->call('create')
            ->assertHasFormErrors(['autor' => 'required', 'texto' => 'required']);

        $this->assertSame(0, Testimonio::count());
    }

    #[TestDox('landing-E19: una cuenta con sesión que no es de administración no abre la lista, ni el alta, ni la edición de testimonios')]
    public function test_landing_e19_sin_permiso_el_panel_rechaza_las_paginas(): void
    {
        $testimonio = Testimonio::factory()->activo()->create(['autor' => 'Persona de prueba']);

        foreach (self::OTROS_ROLES as $rol) {
            $this->actingAs(Usuario::factory()->conRol($rol)->create(), 'web');

            $this->get('/admin/testimonios')->assertForbidden();
            $this->get('/admin/testimonios/create')->assertForbidden();
            $this->get("/admin/testimonios/{$testimonio->getRouteKey()}/edit")->assertForbidden();
        }
    }

    #[TestDox('landing-E19: sin permiso de administración, crear, editar o activar un testimonio se rechaza y nada cambia en la portada')]
    public function test_landing_e19_sin_permiso_no_se_crea_edita_ni_activa_y_la_portada_no_cambia(): void
    {
        Filament::setCurrentPanel('admin');
        $activo = Testimonio::factory()->activo()->create(['autor' => 'Persona de prueba activa', 'orden' => 1]);
        $inactivo = Testimonio::factory()->create(['autor' => 'Persona de prueba inactiva', 'orden' => 2]);
        $antes = $this->tarjetas();

        foreach (self::OTROS_ROLES as $rol) {
            $this->actingAs(Usuario::factory()->conRol($rol)->create(), 'web');

            Livewire::test(CreateTestimonio::class)->assertForbidden();
            Livewire::test(ListTestimonios::class)->assertForbidden();
            // Sin pasar por el panel, la edición ni siquiera encuentra el testimonio: la consulta del componente no le
            // da ninguno, y responde como si no existiera (ADR-0019, decisión 3).
            Livewire::test(EditTestimonio::class, ['record' => $activo->getRouteKey()])->assertNotFound();
        }

        $this->assertSame(2, Testimonio::count());
        $this->assertSame(['Persona de prueba activa', true], [$activo->refresh()->autor, $activo->activo]);
        $this->assertFalse($inactivo->refresh()->activo);
        $this->assertSame($antes, $this->tarjetas());
    }

    #[TestDox('landing-E19: el componente único de autorización da la edición de la portada y sus testimonios solo a la administración')]
    public function test_landing_e19_el_componente_da_la_edicion_solo_a_la_administracion(): void
    {
        Testimonio::factory()->count(2)->create();
        $autorizacion = app(Autorizacion::class);

        $administracion = Usuario::factory()->conRol(Rol::ADMINISTRACION)->create();
        $this->assertTrue($autorizacion->puedeEditarLaPortada($administracion));
        $this->assertSame(2, $autorizacion->testimoniosDelPanel($administracion)->count());

        foreach (self::OTROS_ROLES as $rol) {
            $cuenta = Usuario::factory()->conRol($rol)->create();
            $this->assertFalse($autorizacion->puedeEditarLaPortada($cuenta), $rol);
            $this->assertSame(0, $autorizacion->testimoniosDelPanel($cuenta)->count(), $rol);
        }

        $bloqueada = Usuario::factory()->conRol(Rol::ADMINISTRACION)->create(['estado' => Usuario::BLOQUEADO]);
        $this->assertFalse($autorizacion->puedeEditarLaPortada($bloqueada), 'Una cuenta bloqueada no edita.');
    }
}
