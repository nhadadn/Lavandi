<?php

namespace Tests\Feature\PerfilLavanderia;

use App\Models\Lavanderia;
use Carbon\CarbonImmutable;
use Database\Seeders\LavanderiasDePruebaSeeder;
use Database\Seeders\PlazasDePruebaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\PerfilLavanderia\Soporte\DiscoLocalDeFotos;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * Las fotos del perfil de la lavandería: viven en el almacenamiento privado del entorno y llegan a la app solo como URLs
 * firmadas que vencen; sin firma, o con la firma vencida, el almacenamiento no entrega la foto
 * (NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3, Almacenamiento de imágenes; ADR-0006; ADR-0012).
 *
 * Usan el disco local privado de desarrollo (fotos_local), que firma como el de producción: el falso no firma.
 *
 * Escenarios: perfil-lavanderia-E03 y perfil-lavanderia-E04 · Tarea 2.1 · Spec:
 * openspec/changes/perfil-de-lavanderia/specs/perfil-lavanderia/spec.md
 */
class FotosTest extends TestCase
{
    use DiscoLocalDeFotos;
    use LavanderiasDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    #[TestDox('perfil-lavanderia-E03: cada una de sus tres fotos llega, en su orden, como URL firmada que vence en el tiempo de la configuración')]
    public function test_perfil_lavanderia_e03_cada_foto_llega_con_url_firmada(): void
    {
        $disco = $this->usarDiscoLocalDeFotos();
        config(['fotos.vigencia_minutos' => 15]);
        $this->travelTo(CarbonImmutable::parse('2026-11-22 18:00:00', 'UTC'));

        $lavanderia = Lavanderia::factory()->en($this->plazaDePrueba())->create();
        // Guardadas fuera de orden: el perfil las entrega por su orden.
        foreach ([3, 1, 2] as $orden) {
            $ruta = "lavanderias/{$lavanderia->id_publico}/foto-{$orden}.jpg";
            $disco->put($ruta, "contenido de prueba de la foto {$orden}");
            $lavanderia->fotos()->create(['ruta' => $ruta, 'orden' => $orden]);
        }

        $fotos = $this->pedirComoUsuario($this->direccion($lavanderia))->assertOk()->json('lavanderia.fotos');

        $this->assertCount(3, $fotos);
        foreach ($fotos as $indice => $foto) {
            // Solo la URL y su vencimiento: ni la ruta ni la llave de la foto.
            $this->assertSame(['url', 'vence_en'], array_keys($foto));
            $this->assertSame('2026-11-22T18:15:00Z', $foto['vence_en']);
            $this->assertStringContainsString('signature=', $foto['url']);
            $this->assertStringContainsString('expires='.CarbonImmutable::parse('2026-11-22 18:15:00', 'UTC')->getTimestamp(), $foto['url']);

            $this->assertSame('contenido de prueba de la foto '.($indice + 1), $this->contenido($this->get($foto['url'])->assertOk()));
        }
    }

    #[TestDox('perfil-lavanderia-E03: la foto no se puede leer por su ruta sin firma, ni con una firma inventada')]
    public function test_perfil_lavanderia_e03_la_foto_no_se_lee_por_su_ruta_sin_firma(): void
    {
        $disco = $this->usarDiscoLocalDeFotos();
        $lavanderia = Lavanderia::factory()->en($this->plazaDePrueba())->create();
        $ruta = "lavanderias/{$lavanderia->id_publico}/foto-1.jpg";
        $disco->put($ruta, 'contenido de prueba');
        $lavanderia->fotos()->create(['ruta' => $ruta, 'orden' => 1]);

        $url = $this->pedirComoUsuario($this->direccion($lavanderia))->assertOk()->json('lavanderia.fotos.0.url');

        $sinFirma = strtok($url, '?');
        $this->get($sinFirma)->assertForbidden();
        $this->get('/archivos/fotos/'.$ruta)->assertForbidden();
        $this->get(preg_replace('/signature=[0-9a-f]+/', 'signature='.str_repeat('0', 64), $url))->assertForbidden();
        // Con la sesión de una cuenta tampoco: lo que abre la foto es la firma, no la sesión.
        $this->pedirComoUsuario($sinFirma)->assertForbidden();
    }

    #[TestDox('perfil-lavanderia-E03: una lavandería sin fotos trae la lista vacía')]
    public function test_perfil_lavanderia_e03_sin_fotos_la_lista_vacia(): void
    {
        $this->usarDiscoLocalDeFotos();
        $lavanderia = Lavanderia::factory()->en($this->plazaDePrueba())->create();

        $this->pedirComoUsuario($this->direccion($lavanderia))->assertOk()->assertJsonPath('lavanderia.fotos', []);
    }

    #[TestDox('perfil-lavanderia-E03: sin sesión, o de una lavandería suspendida, no llega ninguna URL de sus fotos')]
    public function test_perfil_lavanderia_e03_sin_sesion_o_suspendida_no_llega_ninguna_url(): void
    {
        $this->usarDiscoLocalDeFotos();
        $plaza = $this->plazaDePrueba();
        $activa = Lavanderia::factory()->en($plaza)->create();
        $suspendida = Lavanderia::factory()->en($plaza)->suspendida()->create();
        foreach ([$activa, $suspendida] as $lavanderia) {
            $lavanderia->fotos()->create(['ruta' => "lavanderias/{$lavanderia->id_publico}/foto-1.jpg", 'orden' => 1]);
        }

        foreach ([
            $this->pedirSinSesion($this->direccion($activa))->assertUnauthorized(),
            $this->pedirComoUsuario($this->direccion($suspendida))->assertNotFound(),
        ] as $respuesta) {
            $respuesta->assertJsonMissingPath('lavanderia');
            $this->assertStringNotContainsString('signature', $respuesta->getContent());
            $this->assertStringNotContainsString('archivos', $respuesta->getContent());
        }
    }

    #[TestDox('perfil-lavanderia-E04: una URL vencida no entrega la foto; antes de vencer, sí')]
    public function test_perfil_lavanderia_e04_una_url_vencida_no_entrega_la_foto(): void
    {
        $disco = $this->usarDiscoLocalDeFotos();
        $this->travelTo(CarbonImmutable::parse('2026-11-22 18:00:00', 'UTC'));
        $lavanderia = Lavanderia::factory()->en($this->plazaDePrueba())->create();
        $ruta = "lavanderias/{$lavanderia->id_publico}/foto-1.jpg";
        $disco->put($ruta, 'contenido de prueba');
        $lavanderia->fotos()->create(['ruta' => $ruta, 'orden' => 1]);

        $url = $this->pedirComoUsuario($this->direccion($lavanderia))->assertOk()->json('lavanderia.fotos.0.url');
        $vigencia = (int) config('fotos.vigencia_minutos');

        $this->travelTo(CarbonImmutable::parse('2026-11-22 18:00:00', 'UTC')->addMinutes($vigencia)->subSecond());
        $this->get($url)->assertOk();

        $this->travelTo(CarbonImmutable::parse('2026-11-22 18:00:00', 'UTC')->addMinutes($vigencia)->addSecond());
        $this->get($url)->assertForbidden();
    }

    #[TestDox('perfil-lavanderia-E03: el sembrado de desarrollo deja tres fotos de prueba en orden en cada lavandería, en el disco del entorno, y el perfil las entrega firmadas')]
    public function test_perfil_lavanderia_e03_el_sembrado_deja_tres_fotos_de_prueba_en_orden(): void
    {
        $disco = $this->usarDiscoLocalDeFotos();

        $this->seed([PlazasDePruebaSeeder::class, LavanderiasDePruebaSeeder::class]);

        $lavanderias = Lavanderia::all();
        $this->assertNotEmpty($lavanderias);
        foreach ($lavanderias as $lavanderia) {
            $fotos = $lavanderia->fotos()->orderBy('orden')->get();
            $this->assertSame([1, 2, 3], $fotos->pluck('orden')->all(), $lavanderia->nombre);

            foreach ($fotos as $foto) {
                // Una imagen de verdad, hecha en el momento, en el disco privado del entorno.
                $disco->assertExists($foto->ruta);
                $imagen = getimagesizefromstring((string) $disco->get($foto->ruta));
                $this->assertNotFalse($imagen, $foto->ruta);
                $this->assertSame('image/png', $imagen['mime']);
                // Cabe en el peso máximo de una foto (config/fotos.php).
                $this->assertLessThanOrEqual((int) config('fotos.peso_maximo_kb') * 1024, $disco->size($foto->ruta));
            }
        }

        $activa = Lavanderia::where('estado', Lavanderia::ACTIVA)->firstOrFail();
        $urls = array_column($this->pedirComoUsuario($this->direccion($activa))->assertOk()->json('lavanderia.fotos'), 'url');
        $this->assertCount(3, $urls);
        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }
    }

    #[TestDox('perfil-lavanderia-E03: sembrar dos veces no duplica las fotos de prueba ni sus archivos')]
    public function test_perfil_lavanderia_e03_sembrar_dos_veces_no_duplica_las_fotos(): void
    {
        $disco = $this->usarDiscoLocalDeFotos();

        $this->seed([PlazasDePruebaSeeder::class, LavanderiasDePruebaSeeder::class]);
        $filas = Lavanderia::all()->mapWithKeys(fn (Lavanderia $lavanderia) => [
            $lavanderia->getKey() => $lavanderia->fotos()->orderBy('orden')->pluck('ruta')->all(),
        ])->all();
        $archivos = $disco->allFiles();

        $this->seed(LavanderiasDePruebaSeeder::class);

        foreach (Lavanderia::all() as $lavanderia) {
            $this->assertSame($filas[$lavanderia->getKey()], $lavanderia->fotos()->orderBy('orden')->pluck('ruta')->all());
        }
        $this->assertEqualsCanonicalizing($archivos, $disco->allFiles());
    }

    private function direccion(Lavanderia $lavanderia): string
    {
        return '/api/lavanderias/'.$lavanderia->id_publico;
    }

    /**
     * Lo que entregó el disco: la respuesta que sirve el archivo es un flujo.
     */
    private function contenido(TestResponse $respuesta): string
    {
        return $respuesta->streamedContent();
    }
}
