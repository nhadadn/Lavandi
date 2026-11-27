<?php

namespace Tests\Feature\Direcciones;

use App\Direcciones\GeocodificacionNoDisponible;
use App\Direcciones\Geocodificador;
use App\Models\Direccion;
use App\Models\Municipio;
use App\Plazas\ResolucionDePunto;
use Database\Seeders\PlazasDePruebaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Direcciones\Soporte\CatalogoDePrueba;
use Tests\Feature\Direcciones\Soporte\DireccionesDePrueba;
use Tests\TestCase;

/**
 * La geocodificación de prueba: fuera de producción y sin la llave de Google, una dirección del municipio de Durango cae
 * en la zona de la plaza de prueba de Durango, para probar el pedido desde un teléfono o el emulador (DD-072). Decide
 * por el municipio del catálogo, no por la palabra "durango" en el texto (openspec/changes/direcciones/design.md,
 * decisión 10). Con la llave, o en producción, nada cambia.
 *
 * Escenario: direcciones-E15 · Spec: openspec/changes/direcciones/specs/direcciones/spec.md · Tarea 4.5
 */
class GeocodificadorDePruebaTest extends TestCase
{
    use DireccionesDePrueba;
    use RefreshDatabase;

    private const EN_DURANGO = 'Calle de prueba 123, Col. Centro, Durango, Durango';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.geocodificacion.llave' => null]);
        CatalogoDePrueba::territorio();
        Http::preventStrayRequests();
    }

    #[TestDox('direcciones-E15: fuera de producción y sin llave, una dirección del municipio de Durango cae en la zona de prueba de Durango, siempre en el mismo punto')]
    public function test_direcciones_e15_fuera_de_produccion_una_direccion_de_durango_cae_en_su_zona_de_prueba(): void
    {
        $this->seed(PlazasDePruebaSeeder::class);
        $durango = $this->municipio(CatalogoDePrueba::DURANGO);

        $punto = app(Geocodificador::class)->geocodificar(self::EN_DURANGO, $durango);

        $this->assertNotNull($punto);
        $plaza = app(ResolucionDePunto::class)->resolver($punto->latitud, $punto->longitud);
        $this->assertNotNull($plaza);
        $this->assertSame('Plaza de prueba Durango', $plaza->plaza->nombre);
        $this->assertEquals($punto, app(Geocodificador::class)->geocodificar(self::EN_DURANGO, $durango));
        $this->assertNotEquals(
            $punto,
            app(Geocodificador::class)->geocodificar('Otra calle 45, Col. Centro, Durango, Durango', $durango),
        );
    }

    #[TestDox('direcciones-E15: fuera de producción y sin llave, una dirección de otro municipio no se encuentra, aunque su texto diga Durango')]
    public function test_direcciones_e15_otro_municipio_no_se_encuentra(): void
    {
        // Gómez Palacio y Torreón, con "Durango" en el texto; y un municipio que se llama Durango en otro estado.
        $casos = [
            'Calle de prueba 1, Col. Centro, 35000 Gómez Palacio, Durango' => $this->municipio(CatalogoDePrueba::GOMEZ_PALACIO),
            'Calle Durango 1, Col. Centro, Torreón, Coahuila de Zaragoza' => $this->municipio(CatalogoDePrueba::TORREON),
            'Calle de prueba 1, Col. Centro, Durango, Estado de prueba' => CatalogoDePrueba::municipio('99002', 'Durango'),
        ];

        foreach ($casos as $texto => $municipio) {
            $this->assertNull(app(Geocodificador::class)->geocodificar($texto, $municipio), $texto);
        }
    }

    #[TestDox('direcciones-E15: guardar desde la API una dirección escrita en Durango, sin llave, la deja en la plaza de prueba de Durango; una de Gómez Palacio no se encuentra')]
    public function test_direcciones_e15_desde_la_api_una_direccion_de_durango_queda_en_la_plaza_de_prueba(): void
    {
        $this->seed(PlazasDePruebaSeeder::class);
        $cuenta = $this->cuentaDeUsuario();

        $this->comoCuenta($cuenta)
            ->postJson('/api/direcciones', $this->direccionEscrita(['municipio' => CatalogoDePrueba::DURANGO]))
            ->assertCreated()
            ->assertJsonPath('direccion.municipio', 'Durango')
            ->assertJsonPath('direccion.estado', 'Durango');

        $this->assertSame('Plaza de prueba Durango', Direccion::sole()->plaza->nombre);

        $this->comoCuenta($cuenta)
            ->postJson('/api/direcciones', $this->direccionEscrita(['municipio' => CatalogoDePrueba::GOMEZ_PALACIO]))
            ->assertUnprocessable()
            ->assertJsonPath('motivo', 'direccion_no_encontrada');

        $this->assertSame(1, Direccion::count());
    }

    #[TestDox('direcciones-E15: en producción sin llave, la geocodificación sigue sin estar disponible')]
    public function test_direcciones_e15_en_produccion_sin_llave_no_esta_disponible(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        $this->expectException(GeocodificacionNoDisponible::class);

        app(Geocodificador::class)->geocodificar(self::EN_DURANGO, $this->municipio(CatalogoDePrueba::DURANGO));
    }

    #[TestDox('direcciones-E15: con la llave, se usa el servicio real aunque no sea producción')]
    public function test_direcciones_e15_con_llave_se_usa_el_servicio_real(): void
    {
        config([
            'services.geocodificacion.llave' => 'llave-de-prueba',
            'services.geocodificacion.direccion' => 'https://geocodificacion.prueba.test/json',
        ]);
        Http::fake(['geocodificacion.prueba.test/*' => Http::response([
            'status' => 'OK',
            'results' => [['geometry' => ['location' => ['lat' => 10.5, 'lng' => -119.5]]]],
        ])]);

        $punto = app(Geocodificador::class)->geocodificar(self::EN_DURANGO, $this->municipio(CatalogoDePrueba::DURANGO));

        $this->assertNotNull($punto);
        $this->assertSame(10.5, $punto->latitud);
        $this->assertSame(-119.5, $punto->longitud);
    }

    private function municipio(string $codigo): Municipio
    {
        return Municipio::query()->with('entidadFederativa')->where('codigo', $codigo)->sole();
    }
}
