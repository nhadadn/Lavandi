<?php

namespace Tests\Feature\Landing;

use DOMElement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Landing\Soporte\Portada;
use Tests\TestCase;

/**
 * "¿Cómo funciona?", con el paso a paso Pide → Recogemos → Lavamos → Entregamos, y "Beneficios para usuarios", con
 * los tres pilares comodidad, precio y confianza y sus iconos animados (NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2,
 * secciones 2 y 3). Los nombres de los pasos y los títulos de los pilares son del contrato; el texto que explica cada
 * uno es de EL CLIENTE (DD-117) y, mientras no llegue, va su marcador de contenido de prueba.
 *
 * Que los iconos se queden quietos con movimiento reducido lo prueba el navegador:
 * tests/Browser/Landing/MovimientoReducido.spec.js (landing-E07).
 *
 * Escenarios: landing-E05 y landing-E06 · Spec: openspec/changes/landing/specs/landing/spec.md · Tarea 2.2
 */
class ComoFuncionaYBeneficiosTest extends TestCase
{
    use Portada;
    use RefreshDatabase;

    #[TestDox('landing-E05: "¿Cómo funciona?" muestra cuatro pasos, en este orden: Pide, Recogemos, Lavamos y Entregamos')]
    public function test_landing_e05_cuatro_pasos_en_su_orden(): void
    {
        $portada = $this->portada();

        $this->assertCount(1, $this->en($portada, 'como-funciona', './/ol[@data-pasos]'));
        $this->assertSame(
            ['Pide', 'Recogemos', 'Lavamos', 'Entregamos'],
            $this->textos($portada, 'como-funciona', './/ol[@data-pasos]/li/*[@data-titulo]'),
        );
    }

    #[TestDox('landing-E05: cada paso lleva el texto que se cargue como contenido, y sin él, su marcador de contenido de prueba')]
    public function test_landing_e05_cada_paso_lleva_su_texto_o_su_marcador(): void
    {
        config(['landing.como_funciona' => [
            'pide' => 'Texto de prueba del paso uno',
            'recogemos' => null,
            'lavamos' => 'Texto de prueba del paso tres',
            'entregamos' => null,
        ]]);
        $portada = $this->portada();

        $pasos = $this->en($portada, 'como-funciona', './/ol[@data-pasos]/li');
        $this->assertCount(4, $pasos);
        $this->assertStringContainsString('Texto de prueba del paso uno', $this->texto($pasos[0]));
        $this->assertStringContainsString('Contenido de prueba', $this->texto($pasos[1]));
        $this->assertStringContainsString('Texto de prueba del paso tres', $this->texto($pasos[2]));
        $this->assertStringContainsString('Contenido de prueba', $this->texto($pasos[3]));
    }

    #[TestDox('landing-E06: "Beneficios para usuarios" muestra tres pilares titulados comodidad, precio y confianza')]
    public function test_landing_e06_tres_pilares_para_usuarios(): void
    {
        $portada = $this->portada();

        $this->assertSame(
            ['Comodidad', 'Precio', 'Confianza'],
            $this->textos($portada, 'beneficios-usuarios', './/*[@data-pilar]/*[@data-titulo]'),
        );
    }

    #[TestDox('landing-E06: cada pilar lleva su icono animado')]
    public function test_landing_e06_cada_pilar_lleva_su_icono_animado(): void
    {
        $portada = $this->portada();

        $pilares = $this->en($portada, 'beneficios-usuarios', './/*[@data-pilar]');
        $this->assertCount(3, $pilares);

        foreach ($pilares as $pilar) {
            $iconos = $portada->query('.//svg[@data-icono-animado]', $pilar);
            $this->assertSame(1, $iconos->length, 'Un icono animado por pilar.');

            $icono = $iconos->item(0);
            $this->assertInstanceOf(DOMElement::class, $icono);
            $this->assertContains('icono--animado', explode(' ', $icono->getAttribute('class')));
            $this->assertSame('true', $icono->getAttribute('aria-hidden'), 'El icono es decorativo: el título dice el pilar.');
        }
    }

    #[TestDox('landing-E06: el texto de cada pilar es contenido que se carga; sin él, su marcador; y el pilar "precio" no publica cifras')]
    public function test_landing_e06_el_texto_de_cada_pilar_es_contenido(): void
    {
        $portada = $this->portada();

        $precio = $this->en($portada, 'beneficios-usuarios', './/*[@data-pilar="precio"]');
        $this->assertCount(1, $precio);
        $this->assertDoesNotMatchRegularExpression('/[0-9$]/', $this->texto($precio[0]), 'Sin cifras mientras DD-010 siga abierta.');
        $this->assertCount(3, $this->en($portada, 'beneficios-usuarios', './/*[@data-pilar]//*[@data-de-prueba]'));

        config(['landing.beneficios_usuarios.comodidad' => 'Texto de prueba de la comodidad']);
        $portada = $this->portada();

        $comodidad = $this->en($portada, 'beneficios-usuarios', './/*[@data-pilar="comodidad"]');
        $this->assertStringContainsString('Texto de prueba de la comodidad', $this->texto($comodidad[0]));
        $this->assertCount(2, $this->en($portada, 'beneficios-usuarios', './/*[@data-pilar]//*[@data-de-prueba]'));
    }
}
