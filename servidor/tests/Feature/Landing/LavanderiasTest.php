<?php

namespace Tests\Feature\Landing;

use DOMElement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Landing\Soporte\Portada;
use Tests\TestCase;

/**
 * "Lavanderías partner", con la invitación a unirse y el formulario de interés, y "Beneficios para socios", con los tres
 * pilares más clientes, pagos seguros y cero gestión de cobranza (NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2,
 * secciones 5 y 6).
 *
 * El texto de la invitación es de EL CLIENTE (DD-117). Los títulos de los pilares son del contrato; el texto que explica
 * "cero gestión de cobranza" espera a DD-013 y no se escribe. Qué campos lleva el formulario y a dónde llega es DD-119:
 * mientras tanto pide lo mínimo para responder (design.md, lectura 5). Su validación es landing-E21 (FormulariosTest).
 *
 * Escenarios: landing-E10 y landing-E11 · Spec: openspec/changes/landing/specs/landing/spec.md · Tarea 2.4
 */
class LavanderiasTest extends TestCase
{
    use Portada;
    use RefreshDatabase;

    #[TestDox('landing-E10: "Lavanderías partner" muestra el texto de invitación que se cargue como contenido, y sin él, su marcador')]
    public function test_landing_e10_muestra_el_texto_de_invitacion(): void
    {
        config(['landing.lavanderias.invitacion' => 'Texto de prueba de la invitación a lavanderías']);
        $this->assertSame(
            ['Texto de prueba de la invitación a lavanderías'],
            $this->textos($this->portada(), 'lavanderias', './/*[@data-invitacion]'),
        );

        config(['landing.lavanderias.invitacion' => null]);
        $portada = $this->portada();
        $this->assertSame([], $this->en($portada, 'lavanderias', './/*[@data-invitacion]'));
        $this->assertNotSame([], $this->en($portada, 'lavanderias', './/*[@data-invitacion-de-prueba]'));
    }

    #[TestDox('landing-E10: "Lavanderías partner" muestra el formulario de interés de lavanderías, que se envía a su ruta con su protección contra peticiones de otro sitio')]
    public function test_landing_e10_muestra_el_formulario_de_interes(): void
    {
        $portada = $this->portada();

        $formularios = $this->en($portada, 'lavanderias', './/form[@data-formulario="interes"]');
        $this->assertCount(1, $formularios);
        $formulario = $formularios[0];

        $this->assertSame('post', strtolower($formulario->getAttribute('method')));
        $this->assertSame(route('portada.interes'), $formulario->getAttribute('action'));
        $this->assertSame(1, $portada->query('.//input[@type="hidden"][@name="_token"]', $formulario)->length);
        $this->assertSame(1, $portada->query('.//button[@type="submit"]', $formulario)->length);
    }

    #[TestDox('landing-E10: cada campo del formulario de interés tiene su etiqueta y se marca como obligatorio')]
    public function test_landing_e10_cada_campo_tiene_su_etiqueta(): void
    {
        $portada = $this->portada();
        $formulario = $this->en($portada, 'lavanderias', './/form[@data-formulario="interes"]')[0];

        $campos = [];
        foreach ($portada->query('.//input[not(@type="hidden")] | .//textarea', $formulario) as $campo) {
            $this->assertInstanceOf(DOMElement::class, $campo);
            $campos[] = $campo->getAttribute('name');

            $id = $campo->getAttribute('id');
            $this->assertNotSame('', $id);
            $this->assertSame(1, $portada->query(sprintf('.//label[@for="%s"]', $id), $formulario)->length, "El campo {$id} tiene su etiqueta.");
            $this->assertTrue($campo->hasAttribute('required'));
        }

        $this->assertSame(['interes[nombre]', 'interes[lavanderia]', 'interes[correo]'], $campos);
        $this->assertSame('email', $portada->query('.//input[@name="interes[correo]"]', $formulario)->item(0)?->getAttribute('type'));
    }

    #[TestDox('landing-E11: "Beneficios para socios" muestra tres pilares titulados más clientes, pagos seguros y cero gestión de cobranza')]
    public function test_landing_e11_tres_pilares_para_socios(): void
    {
        $portada = $this->portada();

        $this->assertSame(
            ['Más clientes', 'Pagos seguros', 'Cero gestión de cobranza'],
            $this->textos($portada, 'beneficios-socios', './/*[@data-pilar]/*[@data-titulo]'),
        );
    }

    #[TestDox('landing-E11: el texto de cada pilar para socios es contenido que se carga; sin él, su marcador, también el de cero gestión de cobranza, que espera a DD-013')]
    public function test_landing_e11_el_texto_de_cada_pilar_es_contenido(): void
    {
        $portada = $this->portada();
        $this->assertCount(3, $this->en($portada, 'beneficios-socios', './/*[@data-pilar]//*[@data-de-prueba]'));

        config(['landing.beneficios_socios.mas_clientes' => 'Texto de prueba de más clientes']);
        $portada = $this->portada();

        $masClientes = $this->en($portada, 'beneficios-socios', './/*[@data-pilar="mas_clientes"]');
        $this->assertStringContainsString('Texto de prueba de más clientes', $this->texto($masClientes[0]));
        $this->assertCount(1, $this->en($portada, 'beneficios-socios', './/*[@data-pilar="cero_gestion_de_cobranza"]//*[@data-de-prueba]'));
    }
}
