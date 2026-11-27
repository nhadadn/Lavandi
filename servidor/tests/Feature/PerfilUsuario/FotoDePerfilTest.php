<?php

namespace Tests\Feature\PerfilUsuario;

use App\Models\Rol;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Filesystem as Disco;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\PerfilLavanderia\Soporte\DiscoLocalDeFotos;
use Tests\Feature\PerfilUsuario\Soporte\PerfilDePrueba;
use Tests\TestCase;

/**
 * La foto de perfil de la cuenta: se sube al almacenamiento privado del entorno, queda ligada a la cuenta y la API la
 * entrega solo como URL firmada que vence; un archivo que no es imagen, o más pesado que el máximo de la configuración,
 * se rechaza y la foto anterior se conserva (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo A #15, "foto";
 * NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3, Almacenamiento de imágenes; ADR-0006).
 *
 * La app la comprime antes de subirla (tarea 2.2); el servidor solo revisa el tipo y el peso. Usa el disco local privado
 * de desarrollo (fotos_local), que firma como el de producción.
 *
 * Escenarios: perfil-usuario-E03 y perfil-usuario-E04 · Tarea 1.2 · Spec:
 * openspec/changes/perfil-y-soporte/specs/perfil-usuario/spec.md
 */
class FotoDePerfilTest extends TestCase
{
    use DiscoLocalDeFotos;
    use PerfilDePrueba;
    use RefreshDatabase;

    private Disco $disco;

    protected function setUp(): void
    {
        parent::setUp();

        $this->disco = $this->usarDiscoLocalDeFotos();
    }

    #[TestDox('perfil-usuario-E03: la cuenta sin foto sube una; queda en el almacenamiento privado, ligada a su cuenta, y la API la entrega como URL firmada')]
    public function test_perfil_usuario_e03_la_cuenta_sube_su_foto_y_la_api_la_entrega_firmada(): void
    {
        config(['fotos.vigencia_minutos' => 15]);
        $this->travelTo(CarbonImmutable::parse('2026-11-22 18:00:00', 'UTC'));
        $ana = $this->cuentaDeUsuario(['correo' => 'ana@ejemplo.com']);
        $this->comoCuenta($ana)->getJson('/api/perfil')->assertOk()->assertJsonPath('perfil.foto', null);
        $foto = $this->fotoDePrueba();

        $respuesta = $this->subir($ana, ['foto' => $foto])->assertOk();

        // Ligada a la cuenta, en el disco privado y en su carpeta; en la base, solo la ruta.
        $ruta = $ana->fresh()->foto_ruta;
        $this->assertIsString($ruta);
        $this->assertStringStartsWith("usuarios/{$ana->id_publico}/", $ruta);
        $this->assertSame([$ruta], $this->disco->allFiles());
        $this->assertSame($foto->getContent(), $this->disco->get($ruta));

        // La respuesta es el perfil, con la foto como URL firmada que vence; sin la ruta como campo.
        $respuesta->assertJsonPath('perfil.id_publico', $ana->id_publico);
        $respuesta->assertJsonPath('perfil.correo', 'ana@ejemplo.com');
        $this->assertSame(['url', 'vence_en'], array_keys($respuesta->json('perfil.foto')));
        $respuesta->assertJsonPath('perfil.foto.vence_en', '2026-11-22T18:15:00Z');
        $respuesta->assertJsonMissingPath('perfil.foto_ruta');
        $this->assertFirmadaYServida($respuesta->json('perfil.foto.url'), $foto->getContent());

        // La siguiente consulta del perfil también la trae firmada.
        $consulta = $this->comoCuenta($ana)->getJson('/api/perfil')->assertOk();
        $this->assertSame(['url', 'vence_en'], array_keys($consulta->json('perfil.foto')));
        $this->assertFirmadaYServida($consulta->json('perfil.foto.url'), $foto->getContent());
        $this->assertStringNotContainsString('foto_ruta', $consulta->getContent());
    }

    #[TestDox('perfil-usuario-E03: la URL de la foto no se lee sin firma ni después de vencer')]
    public function test_perfil_usuario_e03_la_url_no_se_lee_sin_firma_ni_vencida(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-22 18:00:00', 'UTC'));
        $ana = $this->cuentaDeUsuario();
        $url = $this->subir($ana, ['foto' => $this->fotoDePrueba()])->assertOk()->json('perfil.foto.url');

        $this->get(strtok($url, '?'))->assertForbidden();
        $this->get('/archivos/fotos/'.$ana->fresh()->foto_ruta)->assertForbidden();

        $this->travel((int) config('fotos.vigencia_minutos'))->minutes();
        $this->travel(1)->seconds();
        $this->get($url)->assertForbidden();
    }

    #[TestDox('perfil-usuario-E03: una foto nueva reemplaza a la anterior, y el archivo anterior se borra')]
    public function test_perfil_usuario_e03_una_foto_nueva_reemplaza_a_la_anterior(): void
    {
        $ana = $this->cuentaDeUsuario();
        $this->subir($ana, ['foto' => $this->fotoDePrueba('primera.jpg')])->assertOk();
        $anterior = $ana->fresh()->foto_ruta;

        $nueva = $this->fotoDePrueba('segunda.png');
        $this->subir($ana, ['foto' => $nueva])->assertOk();

        $ruta = $ana->fresh()->foto_ruta;
        $this->assertNotSame($anterior, $ruta);
        $this->assertSame([$ruta], $this->disco->allFiles());
        $this->assertSame($nueva->getContent(), $this->disco->get($ruta));
    }

    #[TestDox('perfil-usuario-E03: cada cuenta pone solo su propia foto; la de otra cuenta no cambia ni se ve')]
    public function test_perfil_usuario_e03_cada_cuenta_pone_solo_su_propia_foto(): void
    {
        $ana = $this->cuentaDeUsuario();
        $beto = $this->cuentaDeUsuario();
        $this->subir($beto, ['foto' => $this->fotoDePrueba('beto.jpg')])->assertOk();
        $deBeto = $beto->fresh()->foto_ruta;

        $respuesta = $this->subir($ana, ['foto' => $this->fotoDePrueba('ana.jpg')])->assertOk();

        $respuesta->assertJsonPath('perfil.id_publico', $ana->id_publico);
        $this->assertSame($deBeto, $beto->fresh()->foto_ruta);
        $this->disco->assertExists($deBeto);
        $this->assertStringStartsWith("usuarios/{$ana->id_publico}/", $ana->fresh()->foto_ruta);
        $this->assertStringNotContainsString($beto->id_publico, $respuesta->getContent());
        $this->assertStringNotContainsString($beto->id_publico, $this->comoCuenta($ana)->getJson('/api/perfil')->getContent());
    }

    #[TestDox('perfil-usuario-E03: sin sesión, o con una cuenta sin el rol de usuario, la foto no se sube')]
    public function test_perfil_usuario_e03_sin_sesion_o_sin_el_rol_de_usuario_no_se_sube(): void
    {
        $this->app['auth']->forgetGuards();
        $this->withoutToken()->postJson('/api/perfil/foto', ['foto' => $this->fotoDePrueba()])->assertUnauthorized();

        $socio = Usuario::factory()->conRol(Rol::LAVANDERIA)->create();
        $this->subir($socio, ['foto' => $this->fotoDePrueba()])
            ->assertForbidden()
            ->assertJsonPath('message', 'Esta cuenta no tiene acceso.');

        $this->assertNull($socio->fresh()->foto_ruta);
        $this->assertSame([], $this->disco->allFiles());
    }

    /**
     * @return array<string, array{\Closure(): array<string, mixed>, string}>
     */
    public static function archivosQueNoSonFoto(): array
    {
        return [
            'un PDF' => [fn (): array => ['foto' => UploadedFile::fake()->create('documento.pdf', 50, 'application/pdf')], 'La foto debe ser una imagen: jpg, jpeg, png, webp.'],
            'un texto con nombre de foto' => [fn (): array => ['foto' => UploadedFile::fake()->create('foto.jpg', 10, 'text/plain')], 'La foto debe ser una imagen: jpg, jpeg, png, webp.'],
            'una imagen de un tipo que no se acepta' => [fn (): array => ['foto' => UploadedFile::fake()->image('foto.gif', 100, 100)], 'La foto debe ser una imagen: jpg, jpeg, png, webp.'],
            'texto en lugar de archivo' => [fn (): array => ['foto' => 'no es un archivo'], 'La foto debe ser una imagen: jpg, jpeg, png, webp.'],
            'sin foto' => [fn (): array => [], 'Elige una foto.'],
        ];
    }

    /**
     * @param  \Closure(): array<string, mixed>  $datos
     */
    #[DataProvider('archivosQueNoSonFoto')]
    #[TestDox('perfil-usuario-E04: un archivo que no es imagen ($_dataName) se rechaza y la foto anterior se conserva')]
    public function test_perfil_usuario_e04_un_archivo_que_no_es_imagen_se_rechaza(\Closure $datos, string $mensaje): void
    {
        [$ana, $anterior] = $this->cuentaConFoto();

        $this->subir($ana, $datos())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['foto' => $mensaje]);

        $this->assertFotoConservada($ana, $anterior);
    }

    #[TestDox('perfil-usuario-E04: una foto más pesada que el máximo de la configuración se rechaza y la anterior se conserva; una del máximo, no')]
    public function test_perfil_usuario_e04_una_foto_mas_pesada_que_el_maximo_se_rechaza(): void
    {
        [$ana, $anterior] = $this->cuentaConFoto();
        config(['fotos.peso_maximo_kb' => 100]);

        $this->subir($ana, ['foto' => $this->fotoDePrueba(kilobytes: 101)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['foto' => 'La foto pesa más de 100 KB.']);
        $this->assertFotoConservada($ana, $anterior);

        $this->subir($ana, ['foto' => $this->fotoDePrueba(kilobytes: 100)])->assertOk();
        $this->assertNotSame($anterior, $ana->fresh()->foto_ruta);
    }

    /**
     * Una foto de prueba, como la manda la app ya comprimida.
     */
    private function fotoDePrueba(string $nombre = 'foto.jpg', int $kilobytes = 120): UploadedFile
    {
        return UploadedFile::fake()->image($nombre, 800, 600)->size($kilobytes);
    }

    /**
     * La app sube la foto con la sesión de la cuenta, en multipart.
     *
     * @param  array<string, mixed>  $datos
     */
    private function subir(Usuario $cuenta, array $datos): TestResponse
    {
        return $this->comoCuenta($cuenta)->postJson('/api/perfil/foto', $datos);
    }

    /**
     * Una cuenta de usuario que ya puso su foto, y la ruta de esa foto.
     *
     * @return array{Usuario, string}
     */
    private function cuentaConFoto(): array
    {
        $cuenta = $this->cuentaDeUsuario();
        $this->subir($cuenta, ['foto' => $this->fotoDePrueba('anterior.jpg')])->assertOk();

        return [$cuenta, $cuenta->fresh()->foto_ruta];
    }

    /**
     * La cuenta conserva su foto anterior, en la base y en el disco, y no quedó ningún archivo más.
     */
    private function assertFotoConservada(Usuario $cuenta, string $anterior): void
    {
        $this->assertSame($anterior, $cuenta->fresh()->foto_ruta);
        $this->assertSame([$anterior], $this->disco->allFiles());
        $this->assertNotNull($this->comoCuenta($cuenta)->getJson('/api/perfil')->assertOk()->json('perfil.foto.url'));
    }

    /**
     * La URL lleva firma y vencimiento, y con ellos el almacenamiento entrega la foto.
     */
    private function assertFirmadaYServida(string $url, string $contenido): void
    {
        $this->assertStringContainsString('signature=', $url);
        $this->assertStringContainsString('expires=', $url);
        $this->assertSame($contenido, $this->get($url)->assertOk()->streamedContent());
    }
}
