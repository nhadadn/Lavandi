<?php

namespace Tests\Feature\TextosLegales;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\PendingCommand;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\TextosLegales\Soporte\TextosLegalesDePrueba;
use Tests\TestCase;

/**
 * Una versión nueva de un documento legal entra sin modificar ni volver a desplegar el código, y su dirección no cambia
 * (NSV-2026-001-v8.pdf:31:ANEXO B, Sección B.7; NSV-2026-001-v8.pdf:10:Cláusula X). La versión vigente es un archivo en
 * el almacenamiento del entorno; se carga con el comando textos-legales:cargar, que reemplaza la anterior
 * (openspec/changes/textos-legales/design.md, "Cómo quedó implementado").
 *
 * Escenario: textos-legales-E04 · Spec: openspec/changes/textos-legales/specs/textos-legales/spec.md · Tarea 1.2
 */
class NuevaVersionTest extends TestCase
{
    use RefreshDatabase;
    use TextosLegalesDePrueba;

    private const VERSION_1 = "AVISO DE PRIVACIDAD DE PRUEBA, VERSIÓN 1\n\nTexto de prueba de la primera versión.";

    private const VERSION_2 = "AVISO DE PRIVACIDAD DE PRUEBA, VERSIÓN 2\n\nTexto de prueba de la segunda versión, con un párrafo más.\n\nFin.";

    private string $carpeta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->almacenamientoVacio();
        $this->carpeta = storage_path('framework/testing/textos-legales-'.uniqid());
        File::ensureDirectoryExists($this->carpeta);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->carpeta);

        parent::tearDown();
    }

    #[TestDox('textos-legales-E04: con un aviso de privacidad publicado, al cargar una versión nueva su dirección muestra la nueva y ya no la anterior, y la dirección es la misma de antes')]
    public function test_textos_legales_e04_una_version_nueva_reemplaza_a_la_anterior_en_la_misma_direccion(): void
    {
        $direccion = route('textos-legales.aviso-de-privacidad', absolute: false);
        $this->assertSame('/aviso-de-privacidad', $direccion);

        $this->cargar('aviso-de-privacidad', $this->archivo('aviso-v1.txt', self::VERSION_1))->assertSuccessful();
        $this->assertSame(self::VERSION_1, $this->textoMostrado($this->pedirDocumento('aviso-de-privacidad')->assertOk()));

        $this->cargar('aviso-de-privacidad', $this->archivo('aviso-v2.txt', self::VERSION_2))->assertSuccessful();

        $this->assertSame($direccion, route('textos-legales.aviso-de-privacidad', absolute: false));
        $respuesta = $this->pedirDocumento('aviso-de-privacidad')->assertOk();
        $this->assertSame(self::VERSION_2, $this->textoMostrado($respuesta));
        $respuesta->assertDontSee('VERSIÓN 1');
        $respuesta->assertDontSee('primera versión');
    }

    #[TestDox('textos-legales-E04: la versión nueva de los términos entra igual, en /terminos, y no toca el aviso de privacidad')]
    public function test_textos_legales_e04_la_version_nueva_de_los_terminos_no_toca_el_aviso(): void
    {
        $this->cargar('aviso-de-privacidad', $this->archivo('aviso.txt', self::VERSION_1))->assertSuccessful();
        $this->cargar('terminos', $this->archivo('terminos-v1.txt', 'TÉRMINOS DE PRUEBA, VERSIÓN 1'))->assertSuccessful();
        $this->cargar('terminos', $this->archivo('terminos-v2.txt', 'TÉRMINOS DE PRUEBA, VERSIÓN 2'))->assertSuccessful();

        $this->assertSame('/terminos', route('textos-legales.terminos', absolute: false));
        $this->assertSame('TÉRMINOS DE PRUEBA, VERSIÓN 2', $this->textoMostrado($this->pedirDocumento('terminos')->assertOk()));
        $this->assertSame(self::VERSION_1, $this->textoMostrado($this->pedirDocumento('aviso-de-privacidad')->assertOk()));
    }

    #[TestDox('textos-legales-E04: el comando guarda el archivo tal cual en el almacenamiento del entorno, donde lo lee la página')]
    public function test_textos_legales_e04_el_comando_guarda_el_archivo_tal_cual_en_el_almacenamiento(): void
    {
        $this->cargar('aviso-de-privacidad', $this->archivo('aviso.txt', self::VERSION_2))
            ->expectsOutputToContain('Aviso de privacidad')
            ->assertSuccessful();

        Storage::disk((string) config('textos_legales.disco'))->assertExists('aviso-de-privacidad.txt');
        $this->assertSame(
            self::VERSION_2,
            Storage::disk((string) config('textos_legales.disco'))->get('aviso-de-privacidad.txt'),
        );
    }

    #[TestDox('textos-legales-E04: un documento que no existe, un archivo que no existe, vacío o que no está en UTF-8 no se carga, y la versión publicada se queda')]
    public function test_textos_legales_e04_un_archivo_que_no_sirve_no_reemplaza_la_version_publicada(): void
    {
        $this->cargar('aviso-de-privacidad', $this->archivo('aviso.txt', self::VERSION_1))->assertSuccessful();

        $this->cargar('politica-de-cookies', $this->archivo('otro.txt', 'Documento de prueba.'))
            ->expectsOutputToContain('aviso-de-privacidad, terminos')
            ->assertFailed();
        $this->cargar('aviso-de-privacidad', $this->carpeta.'/no-existe.txt')->assertFailed();
        $this->cargar('aviso-de-privacidad', $this->archivo('vacio.txt', "  \n "))->assertFailed();
        $this->cargar('aviso-de-privacidad', $this->archivo('latin1.txt', mb_convert_encoding('Versión en Latin-1', 'ISO-8859-1', 'UTF-8')))
            ->assertFailed();

        $this->assertSame(self::VERSION_1, $this->textoMostrado($this->pedirDocumento('aviso-de-privacidad')->assertOk()));
        $this->assertSame(['aviso-de-privacidad.txt'], Storage::disk((string) config('textos_legales.disco'))->allFiles());
    }

    /**
     * Un archivo de prueba con ese contenido, en la carpeta de la prueba.
     */
    private function archivo(string $nombre, string $contenido): string
    {
        $ruta = $this->carpeta.'/'.$nombre;
        File::put($ruta, $contenido);

        return $ruta;
    }

    private function cargar(string $documento, string $archivo): PendingCommand
    {
        return $this->artisan('textos-legales:cargar', ['documento' => $documento, 'archivo' => $archivo]);
    }
}
