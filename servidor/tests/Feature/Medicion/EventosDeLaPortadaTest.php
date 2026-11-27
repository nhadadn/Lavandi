<?php

namespace Tests\Feature\Medicion;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Medicion\Soporte\PaginasPublicas;
use Tests\TestCase;

/**
 * La parte del servidor de los eventos de la portada (NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2;
 * NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.2, A42-2): después de un envío completo de un formulario, la portada de
 * vuelta pone en la capa de datos del contenedor el evento del formulario, con solo su nombre; un envío rechazado no pone
 * ninguno. Y los botones de las tiendas dicen qué tienda son, para el evento de descarga.
 *
 * Que el contenedor de prueba mande esos eventos hacia GA4 y Meta Pixel, y el toque en los botones, lo prueba
 * tests/Browser/Medicion/Eventos.spec.js, que la integración continua corre en el trabajo landing-navegadores; esta
 * prueba corre en el trabajo servidor.
 *
 * Escenarios: medicion-E04 y medicion-E05 · Spec: openspec/changes/medicion-de-la-landing/specs/medicion/spec.md · Tarea
 * 1.2
 */
class EventosDeLaPortadaTest extends TestCase
{
    use PaginasPublicas;
    use RefreshDatabase;

    /** Lo que escribe la persona en cada formulario: inventado, y fácil de buscar en la página. */
    private const ESCRITO = [
        'interes' => [
            'nombre' => 'Nombre Secreto De Prueba',
            'lavanderia' => 'Lavanderia Secreta De Prueba',
            'correo' => 'correo.secreto@ejemplo.test',
        ],
        'contacto' => [
            'nombre' => 'Nombre Secreto De Prueba',
            'correo' => 'correo.secreto@ejemplo.test',
            'mensaje' => 'Mensaje secreto de prueba',
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config(['medicion.gtm' => self::CONTENEDOR_DE_PRUEBA]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function formularios(): array
    {
        return ['interés' => ['interes'], 'contacto' => ['contacto']];
    }

    #[DataProvider('formularios')]
    #[TestDox('medicion-E05: después de un envío completo, la portada pone en la capa de datos el evento del formulario, con solo su nombre')]
    public function test_medicion_e05_el_envio_completo_pone_el_evento_del_formulario(string $formulario): void
    {
        $respuesta = $this->enviar($formulario, self::ESCRITO[$formulario])->assertOk();

        $eventos = $this->eventosDeFormulario($respuesta);
        $this->assertSame([['event' => 'formulario_enviado', 'formulario' => $formulario]], $eventos);
    }

    #[DataProvider('formularios')]
    #[TestDox('medicion-E05: el código de medición de la portada de vuelta no lleva nada de lo que la persona escribió')]
    public function test_medicion_e05_la_medicion_no_lleva_lo_que_la_persona_escribio(string $formulario): void
    {
        $respuesta = $this->enviar($formulario, self::ESCRITO[$formulario])->assertOk();

        $medicion = $this->html($respuesta)->query('//script[@data-medicion]');
        $this->assertCount(1, $medicion);

        foreach (self::ESCRITO[$formulario] as $campo => $valor) {
            $this->assertStringNotContainsStringIgnoringCase($valor, $medicion->item(0)->textContent, "Sin {$campo}.");
        }
    }

    #[DataProvider('formularios')]
    #[TestDox('medicion-E05: un envío rechazado por incompleto no pone ningún evento de formulario')]
    public function test_medicion_e05_el_envio_rechazado_no_pone_evento(string $formulario): void
    {
        $campos = self::ESCRITO[$formulario];
        array_shift($campos);

        $respuesta = $this->enviar($formulario, $campos)->assertOk();

        $this->assertSame(1, $this->html($respuesta)->query('//*[@data-error-de]')->length);
        $this->assertSame([], $this->eventosDeFormulario($respuesta));
    }

    #[TestDox('medicion-E05: sin identificador de contenedor, un envío completo no pone ningún código de medición')]
    public function test_medicion_e05_sin_contenedor_el_envio_no_pone_medicion(): void
    {
        config(['medicion.gtm' => null]);

        $respuesta = $this->enviar('contacto', self::ESCRITO['contacto'])->assertOk();

        $this->assertSame(0, $this->html($respuesta)->query('//script[@data-medicion]')->length);
        $this->assertStringNotContainsString('formulario_enviado', (string) $respuesta->getContent());
    }

    #[TestDox('medicion-E04: cada botón de descarga dice qué tienda es, con o sin el enlace de su tienda')]
    public function test_medicion_e04_cada_boton_de_descarga_dice_que_tienda_es(): void
    {
        foreach ([null, 'https://tienda.prueba.test/'] as $enlace) {
            config(['landing.tiendas.app_store.enlace' => $enlace, 'landing.tiendas.google_play.enlace' => $enlace]);

            $tiendas = [];
            foreach ($this->html($this->pedirPagina('/')->assertOk())->query('//*[@data-seccion="descarga"]//*[@data-tienda]') as $boton) {
                $tiendas[] = $boton->getAttribute('data-tienda');
            }

            $this->assertSame(['app_store', 'google_play'], $tiendas);
        }
    }

    /**
     * El envío del formulario desde la portada, siguiendo la redirección de vuelta a ella.
     *
     * @param  array<string, string>  $campos
     */
    private function enviar(string $formulario, array $campos): TestResponse
    {
        $this->withoutVite();

        return $this->followingRedirects()
            ->from(route('portada'))
            ->post(route("portada.{$formulario}"), [$formulario => $campos]);
    }

    /**
     * Los eventos de formulario que la página pone en la capa de datos, leídos del código de medición.
     *
     * @return list<array<string, mixed>>
     */
    private function eventosDeFormulario(TestResponse $respuesta): array
    {
        $eventos = [];

        foreach ($this->html($respuesta)->query('//script[@data-medicion]') as $script) {
            preg_match_all('/dataLayer\.push\((\{[^}]*"event":"formulario_enviado"[^}]*\})\)/', $script->textContent, $empujes);

            foreach ($empujes[1] as $empuje) {
                $eventos[] = json_decode($empuje, true, flags: JSON_THROW_ON_ERROR);
            }
        }

        return $eventos;
    }
}
