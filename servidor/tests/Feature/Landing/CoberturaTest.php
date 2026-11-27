<?php

namespace Tests\Feature\Landing;

use App\Landing\ZonasDeLaCobertura;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Landing\Soporte\Portada;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * Las zonas que dibuja "Cobertura": las activas de las plazas activas, cada una con su nombre y su polígono simplificado
 * en el servidor antes de mandarlo (NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2, sección 7; ADR-0004; ADR-0009,
 * decisión 5; design.md, "Risks / Trade-offs"). Las zonas las carga plazas-y-zonas: aquí solo se leen.
 *
 * Las plazas y las zonas son ficticias, en el océano Pacífico (PlazasDePrueba): las del territorio inicial esperan
 * DD-093 y DD-094. El mapa interactivo, acercar, alejar y el nombre de cada zona, lo prueba el navegador:
 * tests/Browser/Landing/MapaDeCobertura.spec.js (tarea 3.2).
 *
 * Escenarios: landing-E12 y landing-E14 · Spec: openspec/changes/landing/specs/landing/spec.md · Tarea 3.1
 */
class CoberturaTest extends TestCase
{
    use PlazasDePrueba;
    use Portada;
    use RefreshDatabase;

    private function zonas(): ZonasDeLaCobertura
    {
        return app(ZonasDeLaCobertura::class);
    }

    #[TestDox('landing-E12: con las zonas activas cargadas, la cobertura trae cada zona activa de las plazas activas, con su nombre y su polígono')]
    public function test_landing_e12_trae_cada_zona_activa_con_su_nombre_y_su_poligono(): void
    {
        $primera = $this->plazaDePrueba('Plaza de prueba 1');
        $segunda = $this->plazaDePrueba('Plaza de prueba 2');
        $this->zonaDePrueba($primera, 'Zona de prueba Norte', $this->cuadrado(-120, 11));
        $this->zonaDePrueba($primera, 'Zona de prueba Centro', $this->cuadrado(-120, 10));
        $this->zonaDePrueba($segunda, 'Zona de prueba Sur', $this->cuadrado(-118, 10));

        $zonas = $this->zonas()->activas();

        $this->assertSame(
            [
                ['Plaza de prueba 1', 'Zona de prueba Centro'],
                ['Plaza de prueba 1', 'Zona de prueba Norte'],
                ['Plaza de prueba 2', 'Zona de prueba Sur'],
            ],
            array_map(fn (array $zona): array => [$zona['plaza'], $zona['nombre']], $zonas),
        );

        $centro = $zonas[0]['geometria'];
        $this->assertSame('MultiPolygon', $centro['type']);
        $this->assertEqualsCanonicalizing(
            [[-120.0, 10.0], [-119.0, 10.0], [-119.0, 11.0], [-120.0, 11.0], [-120.0, 10.0]],
            $centro['coordinates'][0][0],
        );
    }

    #[TestDox('landing-E14: una plaza cargada que no está activa no dibuja sus zonas, aunque estén activas')]
    public function test_landing_e14_una_plaza_no_activa_no_dibuja_sus_zonas(): void
    {
        $activa = $this->plazaDePrueba('Plaza de prueba activa');
        $inactiva = $this->plazaDePrueba('Plaza de prueba inactiva', activa: false);
        $this->zonaDePrueba($activa, 'Zona de prueba que sí', $this->cuadrado(-120, 10));
        $this->zonaDePrueba($inactiva, 'Zona de prueba de la plaza inactiva', $this->cuadrado(-110, 10));

        $nombres = array_column($this->zonas()->activas(), 'nombre');
        $this->assertSame(['Zona de prueba que sí'], $nombres);

        $portada = $this->portada();
        $this->assertSame(
            ['Zona de prueba que sí'],
            array_map(fn ($zona): string => $zona->getAttribute('data-zona'), $this->en($portada, 'cobertura', './/*[@data-zona]')),
        );
        $this->assertSame([], $this->en($portada, 'cobertura', './/*[@data-zona="Zona de prueba de la plaza inactiva"]'));
    }

    #[TestDox('landing-E12: una zona que no está activa no se dibuja, aunque su plaza sí lo esté')]
    public function test_landing_e12_una_zona_no_activa_no_se_dibuja(): void
    {
        $plaza = $this->plazaDePrueba();
        $this->zonaDePrueba($plaza, 'Zona de prueba activa', $this->cuadrado(-120, 10));
        $this->zonaDePrueba($plaza, 'Zona de prueba inactiva', $this->cuadrado(-119, 10), activa: false);

        $this->assertSame(['Zona de prueba activa'], array_column($this->zonas()->activas(), 'nombre'));
    }

    #[TestDox('landing-E12: el polígono sale simplificado en el servidor: sin los vértices que no cambian la forma y con cinco decimales')]
    public function test_landing_e12_el_poligono_sale_simplificado(): void
    {
        // Un cuadrado con 400 vértices de más sobre sus lados, y una esquina con más de cinco decimales.
        $anillo = [];
        foreach ([[-120, 10, 1, 0], [-119, 10, 0, 1], [-119, 11, -1, 0], [-120, 11, 0, -1]] as [$x, $y, $dx, $dy]) {
            for ($i = 0; $i < 100; $i++) {
                $anillo[] = [$x + $dx * $i / 100, $y + $dy * $i / 100];
            }
        }
        $anillo[0] = [-120.0000004, 10.0000004];
        $anillo[] = $anillo[0];

        $this->zonaDePrueba($this->plazaDePrueba(), 'Zona de prueba con vértices de más', ['type' => 'Polygon', 'coordinates' => [$anillo]]);

        $coordenadas = $this->zonas()->activas()[0]['geometria']['coordinates'][0][0];
        $this->assertCount(5, $coordenadas, 'Quedan las cuatro esquinas y el cierre.');

        foreach ($coordenadas as [$longitud, $latitud]) {
            foreach ([$longitud, $latitud] as $valor) {
                $this->assertEquals(round($valor, 5), $valor, 'A lo más cinco decimales.');
            }
        }
    }

    #[TestDox('landing-E12: sin zonas activas, la cobertura no trae ninguna y la sección lo dice sin dibujar el mapa')]
    public function test_landing_e12_sin_zonas_activas(): void
    {
        $this->zonaDePrueba($this->plazaDePrueba(activa: false), 'Zona de prueba', $this->cuadrado(-120, 10));

        $this->assertSame([], $this->zonas()->activas());

        $portada = $this->portada();
        $this->assertSame([], $this->en($portada, 'cobertura', './/*[@data-mapa]'));
        $this->assertCount(1, $this->en($portada, 'cobertura', './/*[@data-sin-zonas]'));
    }
}
