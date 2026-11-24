<?php

namespace Tests\Feature\Plazas;

use App\Models\Plaza;
use App\Models\Zona;
use App\Plazas\PuntoEnPlaza;
use App\Plazas\ResolucionDePunto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * Una zona que no está activa no da servicio, aunque su plaza sí lo esté (DD-134, punto 2), y al recargar el archivo
 * de una plaza, la zona que ya no viene se desactiva sin borrarse, porque las filas de operación que vengan pueden
 * apuntar a ella; si una carga posterior la vuelve a traer, se reactiva sin duplicarse (DD-134, punto 3). C-234.
 *
 * Las plazas y zonas son ficticias: cuadrados de un grado en el océano Pacífico.
 *
 * Escenarios: plazas-E13 y plazas-E14 · Spec: openspec/changes/plazas-y-zonas/specs/plazas/spec.md
 */
class ZonaInactivaTest extends TestCase
{
    use PlazasDePrueba;
    use RefreshDatabase;

    #[TestDox('plazas-E13: en una plaza activa, el punto de la zona activa resuelve y el de la zona que no está activa no tiene servicio')]
    public function test_plazas_e13_en_una_plaza_activa_el_punto_de_la_zona_activa_resuelve_y_el_de_la_zona_inactiva_no_tiene_servicio(): void
    {
        $plaza = $this->plazaDePrueba('Plaza de prueba 1');
        // La que no está activa va primero, con el menor id_zonas, para que no gane por orden.
        $inactiva = $this->zonaDePrueba(
            $plaza, 'Zona de prueba Oriente', $this->cuadrado(oeste: -119.0, sur: 10.0), activa: false,
        );
        $activa = $this->zonaDePrueba($plaza, 'Zona de prueba Centro', $this->cuadrado(oeste: -120.0, sur: 10.0));

        $enLaActiva = $this->resolver(latitud: 10.5, longitud: -119.5);
        $this->assertInstanceOf(PuntoEnPlaza::class, $enLaActiva);
        $this->assertTrue($activa->is($enLaActiva->zona));
        $this->assertTrue($plaza->is($enLaActiva->plaza));

        $this->assertNull($this->resolver(latitud: 10.5, longitud: -118.5));

        // En el borde que comparten, el punto es de la zona activa.
        $this->assertTrue($activa->is($this->resolver(latitud: 10.5, longitud: -119.0)?->zona));

        // La misma zona da servicio en cuanto se activa.
        $inactiva->update(['activa' => true]);
        $this->assertTrue($inactiva->is($this->resolver(latitud: 10.5, longitud: -118.5)?->zona));
    }

    #[TestDox('plazas-E14: al recargar, la zona que ya no viene queda guardada pero no activa, y no da servicio')]
    public function test_plazas_e14_al_recargar_la_zona_que_ya_no_viene_queda_guardada_pero_no_activa_y_no_da_servicio(): void
    {
        $centro = $this->cuadrado(oeste: -120.0, sur: 10.0);
        $oriente = $this->cuadrado(oeste: -119.0, sur: 10.0);

        $this->artisan('plazas:cargar', ['archivo' => $this->archivoDePlaza('Plaza de prueba 1', [
            'Zona de prueba Centro' => $centro,
            'Zona de prueba Oriente' => $oriente,
        ])])->assertSuccessful();

        // Otra plaza, con su zona: la recarga de la primera no la toca.
        $otra = $this->plazaDePrueba('Plaza de prueba 2');
        $zonaDeOtra = $this->zonaDePrueba($otra, 'Zona de prueba Oriente', $this->cuadrado(oeste: -110.0, sur: 20.0));

        $oriental = Zona::where('nombre', 'Zona de prueba Oriente')->where('id_plazas', '!=', $otra->getKey())->sole();
        $this->assertTrue($oriental->is($this->resolver(latitud: 10.5, longitud: -118.5)?->zona));

        $this->artisan('plazas:cargar', ['archivo' => $this->archivoDePlaza('Plaza de prueba 1', [
            'Zona de prueba Centro' => $centro,
        ])])
            ->expectsOutputToContain('Plaza de prueba 1: plaza actualizada y activa, con 2 zona(s), 1 de ellas no activa(s).')
            ->assertSuccessful();

        // Queda guardada, la misma fila, pero no activa.
        $this->assertSame(3, Zona::count());
        $this->assertFalse($oriental->fresh()->activa);
        $this->assertNull($this->resolver(latitud: 10.5, longitud: -118.5));

        // La que sí vino sigue dando servicio, y la otra plaza no cambió.
        $plaza = Plaza::where('nombre', 'Plaza de prueba 1')->sole();
        $this->assertTrue($plaza->is($this->resolver(latitud: 10.5, longitud: -119.5)?->plaza));
        $this->assertTrue($zonaDeOtra->fresh()->activa);
        $this->assertTrue($zonaDeOtra->is($this->resolver(latitud: 20.5, longitud: -109.5)?->zona));
    }

    #[TestDox('plazas-E14: si una carga posterior vuelve a traer la zona activa, se reactiva sin duplicarse')]
    public function test_plazas_e14_si_una_carga_posterior_vuelve_a_traer_la_zona_activa_se_reactiva_sin_duplicarse(): void
    {
        $zonas = [
            'Zona de prueba Centro' => $this->cuadrado(oeste: -120.0, sur: 10.0),
            'Zona de prueba Oriente' => $this->cuadrado(oeste: -119.0, sur: 10.0),
        ];

        $this->artisan('plazas:cargar', ['archivo' => $this->archivoDePlaza('Plaza de prueba 1', $zonas)])
            ->assertSuccessful();
        $oriental = Zona::where('nombre', 'Zona de prueba Oriente')->sole();

        $this->artisan('plazas:cargar', ['archivo' => $this->archivoDePlaza('Plaza de prueba 1', [
            'Zona de prueba Centro' => $zonas['Zona de prueba Centro'],
        ])])->assertSuccessful();
        $this->assertFalse($oriental->fresh()->activa);

        $this->artisan('plazas:cargar', ['archivo' => $this->archivoDePlaza('Plaza de prueba 1', $zonas)])
            ->expectsOutputToContain('Plaza de prueba 1: plaza actualizada y activa, con 2 zona(s).')
            ->assertSuccessful();

        $this->assertSame(2, Zona::count());
        $this->assertTrue($oriental->fresh()->activa);
        $this->assertTrue($oriental->is($this->resolver(latitud: 10.5, longitud: -118.5)?->zona));
    }

    private function resolver(float $latitud, float $longitud): ?PuntoEnPlaza
    {
        return app(ResolucionDePunto::class)->resolver($latitud, $longitud);
    }
}
