<?php

namespace Tests\Feature\Plazas;

use App\Models\Plaza;
use App\Plazas\ResolucionDePunto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Una plaza de prueba en tierra, para probar desde el teléfono: además de las plazas de prueba en el océano, el
 * sembrado de prueba deja una plaza con una zona sobre el centro de la ciudad de Durango, con su nombre de prueba. Es
 * solo para probar; no es el territorio inicial, que espera DD-093 y DD-094, y no cuenta para plazas-E10 (DD-134,
 * punto 7; C-234). En producción, ese sembrado no corre (ADR-0012).
 *
 * Escenario: plazas-E16 · Spec: openspec/changes/plazas-y-zonas/specs/plazas/spec.md
 */
class PlazaDePruebaEnTierraTest extends TestCase
{
    use RefreshDatabase;

    private const PLAZA = 'Plaza de prueba Durango';

    /** El centro de la ciudad de Durango, en latitud y longitud. */
    private const CENTRO_DE_DURANGO = [24.0277, -104.6532];

    #[TestDox('plazas-E16: fuera de producción, el sembrado deja una plaza de prueba con una zona sobre la ciudad de Durango')]
    public function test_plazas_e16_fuera_de_produccion_el_sembrado_deja_una_plaza_de_prueba_con_una_zona_sobre_la_ciudad_de_durango(): void
    {
        $this->assertFalse($this->app->isProduction());

        $this->seed();

        // Además de las del océano.
        $this->assertSame(
            ['Plaza de prueba 1', 'Plaza de prueba 2', self::PLAZA],
            Plaza::orderBy('nombre')->pluck('nombre')->all(),
        );

        $plaza = Plaza::where('nombre', self::PLAZA)->sole();
        $this->assertTrue($plaza->activa);
        $this->assertSame('America/Mexico_City', $plaza->zona_horaria);
        $this->assertSame(['Zona de prueba Durango Centro'], $plaza->zonas()->pluck('nombre')->all());

        // El centro de la ciudad resuelve a la plaza de prueba, y unas cuadras más allá también.
        [$latitud, $longitud] = self::CENTRO_DE_DURANGO;
        $this->assertTrue($plaza->is($this->plazaDelPunto($latitud, $longitud)));
        $this->assertTrue($plaza->is($this->plazaDelPunto($latitud - 0.01, $longitud - 0.01)));
        $this->assertTrue($plaza->is($this->plazaDelPunto($latitud + 0.01, $longitud + 0.01)));

        // No es el territorio inicial: a unos 20 km del centro ya no hay servicio, ni en la Comarca Lagunera.
        $this->assertNull($this->plazaDelPunto($latitud + 0.2, $longitud));
        $this->assertNull($this->plazaDelPunto($latitud, $longitud - 0.2));
        $this->assertNull($this->plazaDelPunto(25.54, -103.41));
    }

    #[TestDox('plazas-E16: en producción, el sembrado de prueba no deja la plaza de prueba de Durango')]
    public function test_plazas_e16_en_produccion_el_sembrado_de_prueba_no_deja_la_plaza_de_prueba_de_durango(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        $this->assertTrue($this->app->isProduction());

        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

        $this->assertFalse(Plaza::where('nombre', self::PLAZA)->exists());
        $this->assertNull($this->plazaDelPunto(...self::CENTRO_DE_DURANGO));
    }

    private function plazaDelPunto(float $latitud, float $longitud): ?Plaza
    {
        return app(ResolucionDePunto::class)->resolver($latitud, $longitud)?->plaza;
    }
}
