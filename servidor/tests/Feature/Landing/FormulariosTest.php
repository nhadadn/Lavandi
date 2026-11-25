<?php

namespace Tests\Feature\Landing;

use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Landing\Soporte\Portada;
use Tests\TestCase;

/**
 * Los formularios de interés de lavanderías y de contacto rechazan un envío incompleto, o con un correo mal escrito,
 * diciendo qué campo falta, y no entregan nada (NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2, secciones 5 y 11).
 *
 * Qué campos lleva cada uno es DD-119: mientras tanto piden lo mínimo para responder (design.md, lectura 5). A dónde
 * llega un envío completo y su confirmación son landing-E22, bloqueado por DD-119 y BR-41 (tarea 5.2): hoy un envío
 * completo no se rechaza, no se entrega a nadie y quien lo envía lo sabe.
 *
 * Escenario: landing-E21 · Spec: openspec/changes/landing/specs/landing/spec.md · Tarea 5.1
 */
class FormulariosTest extends TestCase
{
    use Portada;
    use RefreshDatabase;

    /** Un envío completo de cada formulario, con datos inventados. */
    private const COMPLETOS = [
        'interes' => [
            'nombre' => 'Persona de prueba',
            'lavanderia' => 'Lavandería de prueba',
            'correo' => 'persona.de.prueba@ejemplo.test',
        ],
        'contacto' => [
            'nombre' => 'Persona de prueba',
            'correo' => 'persona.de.prueba@ejemplo.test',
            'mensaje' => 'Un mensaje de prueba.',
        ],
    ];

    /** La sección de la portada de cada formulario. */
    private const SECCION = ['interes' => 'lavanderias', 'contacto' => 'contacto'];

    /**
     * @return array<string, array{string, string}>
     */
    public static function camposObligatorios(): array
    {
        $casos = [];
        foreach (self::COMPLETOS as $formulario => $campos) {
            foreach (array_keys($campos) as $campo) {
                $casos["{$formulario}: {$campo}"] = [$formulario, $campo];
            }
        }

        return $casos;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Mail::fake();
        Notification::fake();
    }

    /**
     * @param  array<string, mixed>  $campos
     */
    private function enviar(string $formulario, array $campos): TestResponse
    {
        return $this->from(route('portada'))->post(route("portada.{$formulario}"), [$formulario => $campos]);
    }

    private function formulario(DOMXPath $portada, string $formulario): DOMElement
    {
        $encontrado = $this->en($portada, self::SECCION[$formulario], sprintf('.//form[@data-formulario="%s"]', $formulario));
        $this->assertCount(1, $encontrado);

        return $encontrado[0];
    }

    #[DataProvider('camposObligatorios')]
    #[TestDox('landing-E21: con un campo obligatorio vacío, el envío se rechaza, se señala ese campo y no se entrega nada')]
    public function test_landing_e21_un_campo_obligatorio_vacio_se_rechaza_y_se_senala(string $formulario, string $campo): void
    {
        foreach (['', '   ', null] as $vacio) {
            $campos = [...self::COMPLETOS[$formulario], $campo => $vacio];

            $this->enviar($formulario, $campos)
                ->assertRedirect(route('portada').'#'.self::SECCION[$formulario])
                ->assertSessionHasErrors(["{$formulario}.{$campo}"])
                ->assertSessionDoesntHaveErrors(array_map(
                    fn (string $otro): string => "{$formulario}.{$otro}",
                    array_values(array_diff(array_keys(self::COMPLETOS[$formulario]), [$campo])),
                ));
        }

        Mail::assertNothingOutgoing();
        Notification::assertNothingSent();
    }

    #[DataProvider('camposObligatorios')]
    #[TestDox('landing-E21: al volver a la portada, el campo que falta lleva su error escrito junto a él y los demás conservan lo que se escribió')]
    public function test_landing_e21_la_portada_senala_el_campo_que_falta(string $formulario, string $campo): void
    {
        $campos = [...self::COMPLETOS[$formulario], $campo => ''];

        $respuesta = $this->followingRedirects()->enviar($formulario, $campos)->assertOk();
        $portada = $this->documento((string) $respuesta->getContent());
        $form = $this->formulario($portada, $formulario);

        $control = $portada->query(sprintf('.//*[@name="%s[%s]"]', $formulario, $campo), $form)->item(0);
        $this->assertInstanceOf(DOMElement::class, $control);
        $this->assertSame('true', $control->getAttribute('aria-invalid'));

        $error = $portada->query(sprintf('.//*[@data-error-de="%s"]', $campo), $form)->item(0);
        $this->assertInstanceOf(DOMElement::class, $error);
        $this->assertNotSame('', $this->texto($error), 'El error se dice con texto.');
        $this->assertSame($error->getAttribute('id'), $control->getAttribute('aria-describedby'));
        $this->assertSame(1, $portada->query('.//*[@data-error-de]', $form)->length, 'Solo se señala el campo que falta.');

        foreach (self::COMPLETOS[$formulario] as $otro => $valor) {
            if ($otro === $campo) {
                continue;
            }
            $otroControl = $portada->query(sprintf('.//*[@name="%s[%s]"]', $formulario, $otro), $form)->item(0);
            $this->assertInstanceOf(DOMElement::class, $otroControl);
            $conservado = $otroControl->tagName === 'textarea' ? $otroControl->textContent : $otroControl->getAttribute('value');
            $this->assertSame($valor, $conservado, "{$otro} conserva lo que se escribió.");
        }
    }

    #[TestDox('landing-E21: con un correo mal escrito, el envío de cualquiera de los dos formularios se rechaza y se señala el correo')]
    public function test_landing_e21_un_correo_mal_escrito_se_rechaza(): void
    {
        foreach (array_keys(self::COMPLETOS) as $formulario) {
            foreach (['correo-mal-escrito', 'nombre@', '@ejemplo.test', 'dos@@ejemplo.test'] as $correo) {
                $this->enviar($formulario, [...self::COMPLETOS[$formulario], 'correo' => $correo])
                    ->assertSessionHasErrors(["{$formulario}.correo"]);
            }
        }

        Mail::assertNothingOutgoing();
    }

    #[TestDox('landing-E21: los errores de un formulario no aparecen en el otro')]
    public function test_landing_e21_los_errores_de_un_formulario_no_aparecen_en_el_otro(): void
    {
        $respuesta = $this->followingRedirects()->enviar('interes', [])->assertOk();
        $portada = $this->documento((string) $respuesta->getContent());

        $this->assertSame(3, $portada->query('.//*[@data-error-de]', $this->formulario($portada, 'interes'))->length);
        $this->assertSame(0, $portada->query('.//*[@data-error-de]', $this->formulario($portada, 'contacto'))->length);
        $this->assertSame(0, $portada->query('.//*[@aria-invalid]', $this->formulario($portada, 'contacto'))->length);
    }

    #[TestDox('landing-E21: un envío completo no se rechaza; mientras DD-119 no diga su destino, no se entrega a nadie y quien lo envía lo sabe')]
    public function test_landing_e21_un_envio_completo_no_se_rechaza(): void
    {
        foreach (self::COMPLETOS as $formulario => $campos) {
            $this->enviar($formulario, $campos)
                ->assertRedirect(route('portada').'#'.self::SECCION[$formulario])
                ->assertSessionHasNoErrors();

            $portada = $this->documento((string) $this->followingRedirects()->enviar($formulario, $campos)->getContent());
            $avisos = $portada->query('.//*[@data-envio-sin-destino]', $this->formulario($portada, $formulario));
            $this->assertSame(1, $avisos->length);
            $this->assertSame('status', $avisos->item(0)?->getAttribute('role'));
        }

        Mail::assertNothingOutgoing();
        Notification::assertNothingSent();
    }
}
