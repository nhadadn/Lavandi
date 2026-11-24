<?php

namespace Tests\Feature\Landing;

use App\Landing\PreguntasFrecuentes;
use DOMElement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Landing\Soporte\Portada;
use Tests\TestCase;

/**
 * "Preguntas frecuentes": de 6 a 8 preguntas en un acordeón expandible, con cada respuesta plegada al abrir la portada
 * (NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2, sección 9). Preguntas y respuestas son de EL CLIENTE (DD-117): entran
 * como un archivo de contenido, cuya ruta es LANDING_PREGUNTAS_FRECUENTES (design.md, pregunta abierta 4). Las de aquí
 * son inventadas.
 *
 * Que se desplieguen y se plieguen al tocarlas y con el teclado, con el foco visible, lo prueba el navegador:
 * tests/Browser/Landing/PreguntasFrecuentes.spec.js.
 *
 * Escenario: landing-E20 · Spec: openspec/changes/landing/specs/landing/spec.md · Tarea 4.3
 */
class PreguntasFrecuentesTest extends TestCase
{
    use Portada;
    use RefreshDatabase;

    /**
     * Un archivo de preguntas de prueba, que se borra al terminar la prueba.
     *
     * @param  array<mixed>|string  $contenido
     */
    private function archivoDePreguntas(array|string $contenido): string
    {
        $archivo = (string) tempnam(sys_get_temp_dir(), 'preguntas');
        file_put_contents($archivo, is_string($contenido) ? $contenido : json_encode($contenido, JSON_THROW_ON_ERROR));
        $this->beforeApplicationDestroyed(fn () => @unlink($archivo));

        return $archivo;
    }

    /**
     * @return list<array{pregunta: string, respuesta: string}>
     */
    private function preguntasDePrueba(int $cuantas): array
    {
        return array_map(
            fn (int $n): array => ['pregunta' => "¿Pregunta de prueba {$n}?", 'respuesta' => "Respuesta de prueba {$n}."],
            range(1, $cuantas),
        );
    }

    #[TestDox('landing-E20: con siete preguntas cargadas como contenido, la portada muestra las siete, en su orden, con sus respuestas plegadas')]
    public function test_landing_e20_siete_preguntas_con_sus_respuestas_plegadas(): void
    {
        config(['landing.preguntas_frecuentes' => $this->archivoDePreguntas($this->preguntasDePrueba(7))]);
        $portada = $this->portada();

        $preguntas = $this->en($portada, 'preguntas-frecuentes', './/details[@data-pregunta]');
        $this->assertCount(7, $preguntas);

        foreach ($preguntas as $i => $pregunta) {
            $n = $i + 1;
            $this->assertFalse($pregunta->hasAttribute('open'), "La respuesta {$n} empieza plegada.");

            $titulo = $portada->query('./summary', $pregunta);
            $this->assertSame(1, $titulo->length);
            $this->assertInstanceOf(DOMElement::class, $titulo->item(0));
            $this->assertSame("¿Pregunta de prueba {$n}?", $this->texto($titulo->item(0)));

            $respuesta = $portada->query('./*[@data-respuesta]', $pregunta)->item(0);
            $this->assertInstanceOf(DOMElement::class, $respuesta);
            $this->assertSame("Respuesta de prueba {$n}.", $this->texto($respuesta));
        }

        $this->assertSame([], $this->en($portada, 'preguntas-frecuentes', './/*[@data-de-prueba]'));
    }

    #[TestDox('landing-E20: el acordeón muestra a lo más ocho preguntas, las primeras del archivo')]
    public function test_landing_e20_a_lo_mas_ocho_preguntas(): void
    {
        config(['landing.preguntas_frecuentes' => $this->archivoDePreguntas($this->preguntasDePrueba(10))]);

        $titulos = $this->textos($this->portada(), 'preguntas-frecuentes', './/details[@data-pregunta]/summary');
        $this->assertCount(8, $titulos);
        $this->assertSame('¿Pregunta de prueba 8?', $titulos[7]);
    }

    #[TestDox('landing-E20: sin archivo de preguntas, o con uno que no se puede leer, la sección muestra su marcador de contenido de prueba')]
    public function test_landing_e20_sin_preguntas_muestra_su_marcador(): void
    {
        $invalidos = [
            null,
            base_path('no-existe.json'),
            $this->archivoDePreguntas('esto no es json'),
            $this->archivoDePreguntas(['pregunta' => 'no es una lista']),
        ];

        foreach ($invalidos as $archivo) {
            config(['landing.preguntas_frecuentes' => $archivo]);
            $portada = $this->portada();

            $this->assertSame([], $this->en($portada, 'preguntas-frecuentes', './/details'));
            $this->assertCount(1, $this->en($portada, 'preguntas-frecuentes', './/*[@data-de-prueba]'));
        }
    }

    #[TestDox('landing-E20: una pregunta sin su respuesta, o una respuesta sin su pregunta, no se muestra')]
    public function test_landing_e20_una_pregunta_incompleta_no_se_muestra(): void
    {
        config(['landing.preguntas_frecuentes' => $this->archivoDePreguntas([
            ['pregunta' => '¿Pregunta de prueba completa?', 'respuesta' => 'Respuesta de prueba.'],
            ['pregunta' => '¿Pregunta de prueba sin respuesta?', 'respuesta' => '  '],
            ['respuesta' => 'Respuesta de prueba sin pregunta.'],
            'ni siquiera es un par',
        ])]);

        $this->assertSame(
            ['¿Pregunta de prueba completa?'],
            $this->textos($this->portada(), 'preguntas-frecuentes', './/details[@data-pregunta]/summary'),
        );
    }

    #[TestDox('landing-E20: la ruta del archivo puede ser relativa a la carpeta del servidor')]
    public function test_landing_e20_la_ruta_puede_ser_relativa(): void
    {
        config(['landing.preguntas_frecuentes' => 'tests/Browser/Soporte/preguntas-frecuentes-de-prueba.json']);

        $this->assertCount(7, app(PreguntasFrecuentes::class)->todas());
    }
}
