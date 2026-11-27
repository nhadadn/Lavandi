<?php

namespace Tests\Feature\Landing;

use App\Autorizacion\Autorizacion;
use App\Filament\Resources\LogosPrensa\LogoPrensaResource;
use App\Filament\Resources\LogosPrensa\Pages\CreateLogoPrensa;
use App\Filament\Resources\LogosPrensa\Pages\EditLogoPrensa;
use App\Filament\Resources\LogosPrensa\Pages\ListLogosPrensa;
use App\Models\LogoPrensa;
use App\Models\Rol;
use App\Models\Usuario;
use DOMElement;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\AltaLavanderias\Soporte\PanelDePrueba;
use Tests\Feature\Landing\Soporte\Portada;
use Tests\TestCase;

/**
 * "Prensa / Reconocimientos": "Logos de medios o menciones, activables conforme se obtengan", editables desde el panel
 * con su interruptor de activo y su orden, solo por quien tenga permiso de administración (NSV-2026-001-v8.pdf:15:ANEXO
 * A, Sección A.2, sección 10; ADR-0011). La imagen de cada logo vive en el disco privado de fotos y la portada la recibe
 * por una ruta pública del servidor, con caché y sin URL firmada (adenda del 2026-11-24 de ADR-0006).
 *
 * Quién edita lo decide el componente único de autorización (App\Autorizacion\Autorizacion), con el rol: los logos no
 * son de una plaza. Sin logos activos, la sección sigue presente con el texto que decide EL CLIENTE (DD-117). Los logos
 * de aquí son inventados.
 *
 * Escenarios: landing-E17, landing-E18 y landing-E19 · Spec: openspec/changes/landing/specs/landing/spec.md · Tarea 4.2
 */
class PrensaTest extends TestCase
{
    use PanelDePrueba;
    use Portada;
    use RefreshDatabase;

    /** Los roles que no son de administración. */
    private const OTROS_ROLES = [Rol::USUARIO, Rol::LAVANDERIA, Rol::REPARTIDOR];

    /** Un PNG de un píxel, como imagen de prueba de un logo. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    /**
     * Los logos de la sección de prensa de la portada, como imágenes.
     *
     * @return list<DOMElement>
     */
    private function logos(): array
    {
        return $this->en($this->portada(), 'prensa', './/*[@data-logo-prensa]//img');
    }

    /**
     * El texto alternativo de cada logo de la portada, en su orden.
     *
     * @return list<string>
     */
    private function nombresDeLosLogos(): array
    {
        return array_map(fn (DOMElement $imagen): string => $imagen->getAttribute('alt'), $this->logos());
    }

    /**
     * Pone en el disco de fotos la imagen de un logo y devuelve su contenido.
     */
    private function conImagen(LogoPrensa $logo): string
    {
        $contenido = (string) base64_decode(self::PNG, true);
        Storage::disk(config('fotos.disco'))->put($logo->imagen, $contenido);

        return $contenido;
    }

    #[TestDox('landing-E17: con dos logos activos y uno inactivo, Prensa / Reconocimientos muestra los dos activos, en su orden, con el nombre como texto alternativo, y no el inactivo')]
    public function test_landing_e17_solo_los_logos_activos_en_su_orden(): void
    {
        $segundo = LogoPrensa::factory()->activo()->create(['nombre' => 'Medio de prueba B', 'orden' => 2]);
        $primero = LogoPrensa::factory()->activo()->create(['nombre' => 'Medio de prueba A', 'orden' => 1]);
        $inactivo = LogoPrensa::factory()->create(['nombre' => 'Medio de prueba inactivo', 'orden' => 0]);
        // La ruta pública solo entrega archivos sueltos de la carpeta de prensa: un logo con la imagen en otra parte
        // saldría como imagen rota, así que la portada no lo muestra.
        LogoPrensa::factory()->activo()->create(['nombre' => 'Medio de prueba fuera', 'imagen' => 'otra/logo.png']);
        LogoPrensa::factory()->activo()->create(['nombre' => 'Medio de prueba anidado', 'imagen' => LogoPrensa::CARPETA.'/sub/logo.png']);

        $this->assertSame(['Medio de prueba A', 'Medio de prueba B'], $this->nombresDeLosLogos());

        $logos = $this->logos();
        $this->assertSame(
            [route('portada.prensa.imagen', $primero->archivo()), route('portada.prensa.imagen', $segundo->archivo())],
            array_map(fn (DOMElement $imagen): string => $imagen->getAttribute('src'), $logos),
        );
        foreach ($logos as $imagen) {
            $this->assertSame('lazy', $imagen->getAttribute('loading'), 'La sección queda abajo: el logo espera.');
            $this->assertStringNotContainsString('signature', $imagen->getAttribute('src'), 'Sin URL firmada que venza.');
        }

        $portada = $this->portada();
        $this->assertSame([], $this->en($portada, 'prensa', './/*[@data-de-prueba]'), 'Con logos activos no hay marcador.');
        $this->assertSame([], $this->en($portada, 'prensa', './/*[@data-sin-logos]'));
        $this->assertStringNotContainsString($inactivo->archivo(), (string) $this->pedirPortada()->getContent());
    }

    #[TestDox('landing-E17: la imagen de un logo activo se entrega sin sesión por su ruta pública, con caché, y la de uno inactivo o inexistente responde 404')]
    public function test_landing_e17_la_imagen_de_un_logo_activo_se_entrega_por_su_ruta_publica(): void
    {
        $activo = LogoPrensa::factory()->activo()->create();
        $contenido = $this->conImagen($activo);
        $inactivo = LogoPrensa::factory()->create();
        $this->conImagen($inactivo);
        $sinArchivo = LogoPrensa::factory()->activo()->create();

        $direccion = route('portada.prensa.imagen', $activo->archivo());
        $this->assertSame(url('/prensa/'.$activo->archivo()), $direccion, 'La ruta lleva el nombre del archivo.');
        $this->get('/prensa/'.$activo->getKey())->assertNotFound(); // Nunca la llave interna (ADR-0016, regla 5).

        $respuesta = $this->get($direccion)->assertOk();
        $this->assertSame($contenido, $respuesta->streamedContent());
        $this->assertSame('image/png', $respuesta->headers->get('Content-Type'));
        $this->assertStringContainsString('public', (string) $respuesta->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=', (string) $respuesta->headers->get('Cache-Control'));
        $this->assertSame([], $respuesta->headers->getCookies(), 'Con caché pública, sin cookies de sesión.');

        $this->get(route('portada.prensa.imagen', $inactivo->archivo()))->assertNotFound();
        $this->get(route('portada.prensa.imagen', $sinArchivo->archivo()))->assertNotFound();
        $this->get(route('portada.prensa.imagen', 'no-existe.png'))->assertNotFound();
        // Solo los archivos de la carpeta de prensa, y solo los de un logo: nunca otra foto del disco privado.
        Storage::disk(config('fotos.disco'))->put('lavanderias/foto-de-prueba.png', $contenido);
        $this->get('/prensa/..%2Flavanderias%2Ffoto-de-prueba.png')->assertNotFound();
        $this->get('/prensa/foto-de-prueba.png')->assertNotFound();

        // Al desactivarlo desde el panel, su imagen deja de entregarse.
        $activo->update(['activo' => false]);
        $this->get($direccion)->assertNotFound();
    }

    #[TestDox('landing-E17: la ruta de las imágenes de los logos no pide sesión y lo declara el componente de autorización, con su razón')]
    public function test_landing_e17_la_ruta_de_las_imagenes_se_declara_sin_sesion(): void
    {
        $sinSesion = app(Autorizacion::class)->rutasSinSesion();

        $this->assertArrayHasKey('GET prensa/{archivo}', $sinSesion);
        $this->assertStringContainsString('landing-E17', $sinSesion['GET prensa/{archivo}']);
    }

    #[TestDox('landing-E18: sin logos activos la sección sigue presente con el texto de LANDING_PRENSA_SIN_LOGOS, y sin ese texto, fuera de producción, con el marcador de contenido de prueba')]
    public function test_landing_e18_sin_logos_activos_la_seccion_sigue_presente(): void
    {
        LogoPrensa::factory()->create(['nombre' => 'Medio de prueba inactivo']);

        config(['landing.prensa.sin_logos' => 'Texto de prueba de la sección sin logos']);
        $portada = $this->portada();
        $this->assertSame([], $this->en($portada, 'prensa', './/*[@data-logo-prensa]'));
        $this->assertSame(['Texto de prueba de la sección sin logos'], $this->textos($portada, 'prensa', './/*[@data-sin-logos]'));
        $this->assertSame([], $this->en($portada, 'prensa', './/*[@data-de-prueba]'));

        config(['landing.prensa.sin_logos' => null]);
        $this->assertFalse(app()->isProduction());
        $portada = $this->portada();
        $this->assertSame([], $this->en($portada, 'prensa', './/*[@data-sin-logos]'));
        $this->assertCount(1, $this->en($portada, 'prensa', './/*[@data-sin-logos-de-prueba][@data-de-prueba]'));
        $this->assertSame(['Prensa / Reconocimientos'], $this->textos($portada, 'prensa', './/h2'));
    }

    #[TestDox('landing-E18: el texto de la sección sin logos sale de LANDING_PRENSA_SIN_LOGOS')]
    public function test_landing_e18_el_texto_sin_logos_sale_de_la_configuracion(): void
    {
        $configuracion = (string) file_get_contents(config_path('landing.php'));

        $this->assertStringContainsString("'sin_logos' => env('LANDING_PRENSA_SIN_LOGOS')", $configuracion);
        $this->assertStringContainsString('LANDING_PRENSA_SIN_LOGOS=', (string) file_get_contents(base_path('.env.example')));
    }

    #[TestDox('landing-E17: la administración crea, edita, activa y ordena los logos de prensa desde el panel, y la portada lo refleja')]
    public function test_landing_e17_la_administracion_edita_los_logos_desde_el_panel(): void
    {
        $this->comoAdministracion();
        $this->get('/admin/logos-prensa')->assertOk();
        $this->get('/admin/logos-prensa/create')->assertOk();

        Livewire::test(CreateLogoPrensa::class)
            ->fillForm([
                'nombre' => 'Medio de prueba',
                'imagen' => UploadedFile::fake()->image('logo-de-prueba.png', 200, 80),
                'activo' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $logo = LogoPrensa::sole();
        $this->assertSame(['Medio de prueba', true], [$logo->nombre, $logo->activo]);
        $this->assertStringStartsWith(LogoPrensa::CARPETA.'/', $logo->imagen);
        Storage::disk(config('fotos.disco'))->assertExists($logo->imagen);
        $this->assertSame(['Medio de prueba'], $this->nombresDeLosLogos());
        $this->get(route('portada.prensa.imagen', $logo->archivo()))->assertOk();

        Livewire::test(EditLogoPrensa::class, ['record' => $logo->getRouteKey()])
            ->fillForm(['nombre' => 'Medio de prueba editado'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('Medio de prueba editado', $logo->refresh()->nombre);

        $otro = LogoPrensa::factory()->activo()->create(['nombre' => 'Medio de prueba segundo', 'orden' => 5]);
        Livewire::test(ListLogosPrensa::class)
            ->assertCanSeeTableRecords([$logo, $otro])
            ->call('reorderTable', [(string) $otro->getKey(), (string) $logo->getKey()]);
        $this->assertSame(['Medio de prueba segundo', 'Medio de prueba editado'], $this->nombresDeLosLogos());

        Livewire::test(ListLogosPrensa::class)->call('updateTableColumnState', 'activo', (string) $otro->getKey(), false);
        $this->assertFalse($otro->refresh()->activo);
        $this->assertSame(['Medio de prueba editado'], $this->nombresDeLosLogos());

        // Un logo se retira de la portada desactivándolo: el panel no tiene borrado, ni en la lista ni en la edición.
        $this->assertFalse(LogoPrensaResource::canDelete($otro));
        $this->assertFalse(LogoPrensaResource::canDeleteAny());
        Livewire::test(ListLogosPrensa::class)->assertTableActionDoesNotExist('delete');
        Livewire::test(EditLogoPrensa::class, ['record' => $otro->getRouteKey()])->assertActionDoesNotExist('delete');
        $this->assertSame(2, LogoPrensa::count());
    }

    #[TestDox('landing-E17: el panel no deja guardar un logo de prensa sin nombre o sin imagen, ni una imagen de un tipo que no es de foto')]
    public function test_landing_e17_el_panel_no_guarda_un_logo_incompleto(): void
    {
        $this->comoAdministracion();

        Livewire::test(CreateLogoPrensa::class)
            ->fillForm(['nombre' => '   ', 'imagen' => null])
            ->call('create')
            ->assertHasFormErrors(['nombre' => 'required', 'imagen' => 'required']);

        Livewire::test(CreateLogoPrensa::class)
            ->fillForm(['nombre' => 'Medio de prueba', 'imagen' => UploadedFile::fake()->create('logo.svg', 2, 'image/svg+xml')])
            ->call('create')
            ->assertHasFormErrors(['imagen']);

        $this->assertSame(0, LogoPrensa::count());
    }

    #[TestDox('landing-E19: una cuenta sin permiso de administración no crea, edita ni activa un logo en el panel, y nada cambia en la portada')]
    public function test_landing_e19_sin_permiso_no_se_crea_edita_ni_activa_un_logo(): void
    {
        Filament::setCurrentPanel('admin');
        $activo = LogoPrensa::factory()->activo()->create(['nombre' => 'Medio de prueba activo', 'orden' => 1]);
        $inactivo = LogoPrensa::factory()->create(['nombre' => 'Medio de prueba inactivo', 'orden' => 2]);
        $antes = $this->nombresDeLosLogos();

        foreach (self::OTROS_ROLES as $rol) {
            $this->actingAs(Usuario::factory()->conRol($rol)->create(), 'web');

            $this->get('/admin/logos-prensa')->assertForbidden();
            $this->get('/admin/logos-prensa/create')->assertForbidden();
            $this->get("/admin/logos-prensa/{$activo->getRouteKey()}/edit")->assertForbidden();

            $this->assertFalse(LogoPrensaResource::canCreate(), $rol);
            $this->assertFalse(LogoPrensaResource::canEdit($inactivo), $rol);
            $this->assertFalse(LogoPrensaResource::canDelete($activo), $rol);
            Livewire::test(CreateLogoPrensa::class)->assertForbidden();
            Livewire::test(ListLogosPrensa::class)->assertForbidden();
            // Sin pasar por el panel, la edición ni siquiera encuentra el logo: la consulta del componente no le da
            // ninguno, y responde como si no existiera (ADR-0019, decisión 3).
            Livewire::test(EditLogoPrensa::class, ['record' => $inactivo->getRouteKey()])->assertNotFound();
        }

        $this->assertSame(2, LogoPrensa::count());
        $this->assertSame(['Medio de prueba activo', true], [$activo->refresh()->nombre, $activo->activo]);
        $this->assertFalse($inactivo->refresh()->activo);
        $this->assertSame($antes, $this->nombresDeLosLogos());
    }

    #[TestDox('landing-E19: el componente único de autorización da los logos de prensa del panel solo a la administración')]
    public function test_landing_e19_el_componente_da_los_logos_solo_a_la_administracion(): void
    {
        LogoPrensa::factory()->count(2)->create();
        $autorizacion = app(Autorizacion::class);

        $this->assertSame(2, $autorizacion->logosPrensaDelPanel(Usuario::factory()->conRol(Rol::ADMINISTRACION)->create())->count());

        foreach (self::OTROS_ROLES as $rol) {
            $this->assertSame(0, $autorizacion->logosPrensaDelPanel(Usuario::factory()->conRol($rol)->create())->count(), $rol);
        }

        $bloqueada = Usuario::factory()->conRol(Rol::ADMINISTRACION)->create(['estado' => Usuario::BLOQUEADO]);
        $this->assertSame(0, $autorizacion->logosPrensaDelPanel($bloqueada)->count(), 'Una cuenta bloqueada no edita.');
    }
}
