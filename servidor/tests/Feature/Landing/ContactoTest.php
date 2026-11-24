<?php

namespace Tests\Feature\Landing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Landing\Soporte\Portada;
use Tests\TestCase;

/**
 * "Contacto & Soporte": el correo de contacto, el enlace a WhatsApp Business y el formulario de contacto
 * (NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2, sección 11). El correo y el número salen de la configuración: el
 * correo de config/landing.php y el número de config/soporte.php, el mismo del soporte de la app.
 *
 * El número de WhatsApp Business no existe todavía (DD-082) y el correo lo confirma EL CLIENTE (DD-117): sin ellos, la
 * sección no publica un enlace vacío y muestra sus marcadores. Los de aquí son inventados.
 *
 * Escenario: landing-E23 · Spec: openspec/changes/landing/specs/landing/spec.md · Tarea 5.3
 */
class ContactoTest extends TestCase
{
    use Portada;
    use RefreshDatabase;

    #[TestDox('landing-E23: con un correo configurado, tocarlo abre un mensaje nuevo dirigido a ese correo')]
    public function test_landing_e23_el_correo_abre_un_mensaje_a_ese_correo(): void
    {
        config(['landing.contacto.correo' => 'contacto@ejemplo.test']);
        $portada = $this->portada();

        $correo = $this->en($portada, 'contacto', './/a[@data-contacto="correo"]');
        $this->assertCount(1, $correo);
        $this->assertSame('mailto:contacto@ejemplo.test', $correo[0]->getAttribute('href'));
        $this->assertStringContainsString('contacto@ejemplo.test', $this->texto($correo[0]));
    }

    #[TestDox('landing-E23: con un número de WhatsApp Business configurado, tocarlo abre una conversación con ese número')]
    public function test_landing_e23_whatsapp_abre_una_conversacion_con_ese_numero(): void
    {
        config(['soporte.whatsapp' => '+52 (55) 0000-0000']);
        $portada = $this->portada();

        $whatsapp = $this->en($portada, 'contacto', './/a[@data-contacto="whatsapp"]');
        $this->assertCount(1, $whatsapp);
        $this->assertSame('https://wa.me/525500000000', $whatsapp[0]->getAttribute('href'));
        $this->assertStringContainsString('WhatsApp', $this->texto($whatsapp[0]));
    }

    #[TestDox('landing-E23: el correo y el número salen de la configuración: al cambiarla, cambian los enlaces')]
    public function test_landing_e23_el_correo_y_el_numero_salen_de_la_configuracion(): void
    {
        config(['landing.contacto.correo' => 'otro-contacto@ejemplo.test', 'soporte.whatsapp' => '5500001111']);
        $portada = $this->portada();

        $this->assertSame('mailto:otro-contacto@ejemplo.test', $this->en($portada, 'contacto', './/a[@data-contacto="correo"]')[0]->getAttribute('href'));
        $this->assertSame('https://wa.me/5500001111', $this->en($portada, 'contacto', './/a[@data-contacto="whatsapp"]')[0]->getAttribute('href'));
    }

    #[TestDox('landing-E23: sin correo ni número configurados, o con un correo mal escrito, la sección no publica enlaces vacíos y muestra sus marcadores')]
    public function test_landing_e23_sin_configuracion_no_publica_enlaces_vacios(): void
    {
        foreach ([[null, null], ['no-es-un-correo', '   '], ['', 'sin dígitos']] as [$correo, $numero]) {
            config(['landing.contacto.correo' => $correo, 'soporte.whatsapp' => $numero]);
            $portada = $this->portada();

            $this->assertSame([], $this->en($portada, 'contacto', './/a[@data-contacto]'));
            $this->assertSame([], $this->en($portada, 'contacto', './/a[starts-with(@href, "mailto:") or contains(@href, "wa.me")]'));
            $this->assertCount(2, $this->en($portada, 'contacto', './/*[@data-contacto-de-prueba]'));
        }
    }

    #[TestDox('landing-E23: la sección ofrece también el formulario de contacto, con nombre, correo y mensaje')]
    public function test_landing_e23_la_seccion_ofrece_el_formulario_de_contacto(): void
    {
        $portada = $this->portada();

        $formularios = $this->en($portada, 'contacto', './/form[@data-formulario="contacto"]');
        $this->assertCount(1, $formularios);
        $this->assertSame(route('portada.contacto'), $formularios[0]->getAttribute('action'));
        $this->assertSame('post', strtolower($formularios[0]->getAttribute('method')));
        $this->assertSame(1, $portada->query('.//input[@name="_token"]', $formularios[0])->length);

        $nombres = [];
        foreach ($portada->query('.//input[not(@type="hidden")] | .//textarea', $formularios[0]) as $campo) {
            $nombres[] = $campo->getAttribute('name');
        }
        $this->assertSame(['contacto[nombre]', 'contacto[correo]', 'contacto[mensaje]'], $nombres);
    }
}
