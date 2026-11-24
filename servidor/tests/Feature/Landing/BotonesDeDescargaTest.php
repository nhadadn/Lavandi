<?php

namespace Tests\Feature\Landing;

use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Landing\Soporte\Portada;
use Tests\TestCase;

/**
 * "Descarga la app": los botones de App Store y de Google Play, cada uno con el enlace de su tienda que se configure, y
 * ninguno con una dirección vacía o rota (NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2, sección 4; design.md, lectura
 * 4). Los enlaces reales existen hasta que la app esté publicada (publicacion): aquí son de prueba. Qué dice el botón
 * de una tienda sin enlace lo decide EL CLIENTE (DD-117).
 *
 * Escenarios: landing-E08 y landing-E09 · Spec: openspec/changes/landing/specs/landing/spec.md · Tarea 2.3
 */
class BotonesDeDescargaTest extends TestCase
{
    use Portada;
    use RefreshDatabase;

    private const APP_STORE = 'https://tienda-de-prueba.ejemplo.test/app-store/app-de-prueba';

    private const GOOGLE_PLAY = 'https://tienda-de-prueba.ejemplo.test/google-play/app-de-prueba';

    /**
     * @return list<DOMElement>
     */
    private function boton(DOMXPath $portada, string $tienda): array
    {
        return $this->en($portada, 'descarga', sprintf('.//*[@data-tienda="%s"]', $tienda));
    }

    #[TestDox('landing-E08: con los dos enlaces configurados, el botón de App Store va al de App Store y el de Google Play al suyo')]
    public function test_landing_e08_cada_boton_va_al_enlace_de_su_tienda(): void
    {
        config([
            'landing.tiendas.app_store.enlace' => self::APP_STORE,
            'landing.tiendas.google_play.enlace' => self::GOOGLE_PLAY,
        ]);
        $portada = $this->portada();

        $appStore = $this->boton($portada, 'app_store');
        $this->assertCount(1, $appStore);
        $this->assertSame('a', $appStore[0]->tagName);
        $this->assertSame(self::APP_STORE, $appStore[0]->getAttribute('href'));
        $this->assertStringContainsString('App Store', $this->texto($appStore[0]));

        $googlePlay = $this->boton($portada, 'google_play');
        $this->assertCount(1, $googlePlay);
        $this->assertSame('a', $googlePlay[0]->tagName);
        $this->assertSame(self::GOOGLE_PLAY, $googlePlay[0]->getAttribute('href'));
        $this->assertStringContainsString('Google Play', $this->texto($googlePlay[0]));
    }

    #[TestDox('landing-E09: una tienda sin enlace configurado se muestra sin enlace y con el texto que se configure; la otra conserva el suyo')]
    public function test_landing_e09_la_tienda_sin_enlace_se_muestra_sin_enlace_con_su_texto(): void
    {
        config([
            'landing.tiendas.app_store.enlace' => null,
            'landing.tiendas.app_store.texto_sin_enlace' => 'Texto de prueba de la tienda sin enlace',
            'landing.tiendas.google_play.enlace' => self::GOOGLE_PLAY,
        ]);
        $portada = $this->portada();

        $appStore = $this->boton($portada, 'app_store');
        $this->assertCount(1, $appStore);
        $this->assertNotSame('a', $appStore[0]->tagName, 'Sin enlace no hay etiqueta de enlace.');
        $this->assertFalse($appStore[0]->hasAttribute('href'));
        $this->assertStringContainsString('App Store', $this->texto($appStore[0]));
        $this->assertStringContainsString('Texto de prueba de la tienda sin enlace', $this->texto($appStore[0]));

        $this->assertSame(self::GOOGLE_PLAY, $this->boton($portada, 'google_play')[0]->getAttribute('href'));
    }

    #[TestDox('landing-E09: sin el texto que decida EL CLIENTE, la tienda sin enlace muestra su marcador de contenido de prueba')]
    public function test_landing_e09_sin_texto_configurado_muestra_su_marcador(): void
    {
        config(['landing.tiendas.google_play.enlace' => null, 'landing.tiendas.google_play.texto_sin_enlace' => null]);
        $portada = $this->portada();

        $googlePlay = $this->boton($portada, 'google_play');
        $this->assertNotSame('a', $googlePlay[0]->tagName);
        $this->assertCount(1, $portada->query('.//*[@data-de-prueba]', $googlePlay[0]));
    }

    #[TestDox('landing-E09: un enlace configurado que no es una dirección http o https se trata como si no hubiera enlace')]
    public function test_landing_e09_un_enlace_que_no_es_una_direccion_no_se_publica(): void
    {
        foreach (['   ', 'no es una dirección', 'javascript:alert(1)', 'ftp://tienda-de-prueba.ejemplo.test/app', '/ruta-relativa'] as $enlace) {
            config(['landing.tiendas.app_store.enlace' => $enlace]);
            $portada = $this->portada();

            $appStore = $this->boton($portada, 'app_store');
            $this->assertNotSame('a', $appStore[0]->tagName, "«{$enlace}» no se publica como enlace.");
            $this->assertFalse($appStore[0]->hasAttribute('href'));
        }
    }

    #[TestDox('landing-E09: ningún enlace de la portada apunta a una dirección vacía, con o sin enlaces de las tiendas')]
    public function test_landing_e09_ningun_enlace_de_la_portada_apunta_a_una_direccion_vacia(): void
    {
        $sinEnlaces = ['landing.tiendas.app_store.enlace' => null, 'landing.tiendas.google_play.enlace' => null];
        $conEnlaces = ['landing.tiendas.app_store.enlace' => self::APP_STORE, 'landing.tiendas.google_play.enlace' => self::GOOGLE_PLAY];

        foreach ([$sinEnlaces, $conEnlaces] as $tiendas) {
            config($tiendas);
            $portada = $this->portada();

            foreach ($portada->query('//a') as $enlace) {
                $this->assertInstanceOf(DOMElement::class, $enlace);
                $direccion = trim($enlace->getAttribute('href'));
                $this->assertNotSame('', $direccion, 'Un enlace sin dirección: '.$this->texto($enlace));
                $this->assertNotSame('#', $direccion, 'Un enlace a "#": '.$this->texto($enlace));

                if (str_starts_with($direccion, '#')) {
                    $this->assertSame(1, $portada->query(sprintf('//*[@id="%s"]', substr($direccion, 1)))->length, "El ancla {$direccion} existe.");
                }
            }
        }
    }

    #[TestDox('landing-E08: la sección muestra el mockup de pantalla que se cargue como contenido, y sin él, su marcador')]
    public function test_landing_e08_la_seccion_muestra_el_mockup_de_pantalla(): void
    {
        config(['landing.descarga.imagen' => 'marca/pantalla-de-prueba.png', 'landing.descarga.imagen_texto' => 'Pantalla de prueba']);
        $imagen = $this->en($this->portada(), 'descarga', './/img[@data-imagen-descarga]');
        $this->assertCount(1, $imagen);
        $this->assertStringEndsWith('/marca/pantalla-de-prueba.png', $imagen[0]->getAttribute('src'));
        $this->assertSame('Pantalla de prueba', $imagen[0]->getAttribute('alt'));

        config(['landing.descarga.imagen' => null]);
        $portada = $this->portada();
        $this->assertSame([], $this->en($portada, 'descarga', './/img[@data-imagen-descarga]'));
        $this->assertNotSame([], $this->en($portada, 'descarga', './/*[@data-imagen-descarga-de-prueba]'));
    }
}
