<?php

namespace Tests\Feature\Direcciones;

use App\Direcciones\GeocodificacionNoDisponible;
use App\Direcciones\Geocodificador;
use App\Direcciones\GeocodificadorDeGoogle;
use App\Direcciones\Punto;
use App\Models\Municipio;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * El servicio real de geocodificación: Google Geocoding (ADR-0009, decisión 4), detrás de la interfaz
 * App\Direcciones\Geocodificador. Aquí Google se imita con Http::fake y ninguna prueba sale a la red ni gasta
 * consultas.
 *
 * La llave es de cada entorno y vive solo en su .env (ADR-0012); la de estas pruebas es inventada.
 *
 * Escenarios: direcciones-E01 y direcciones-E03 · Spec: openspec/changes/direcciones/specs/direcciones/spec.md
 */
class GeocodificadorDeGoogleTest extends TestCase
{
    private const DIRECCION_DE_GOOGLE = 'https://geocodificacion.prueba.test/maps/api/geocode/json';

    private const LLAVE_DE_PRUEBA = 'llave-de-prueba-de-geocodificacion';

    private const TEXTO = 'Calle de prueba 123, Colonia de prueba, 00000 Municipio de prueba, Estado de prueba';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.geocodificacion.llave' => self::LLAVE_DE_PRUEBA,
            'services.geocodificacion.direccion' => self::DIRECCION_DE_GOOGLE,
        ]);

        Http::preventStrayRequests();
    }

    #[TestDox('direcciones-E01: con la llave, el servidor geocodifica con Google')]
    public function test_direcciones_e01_sin_otra_indicacion_el_servidor_geocodifica_con_google(): void
    {
        Http::fake([self::DIRECCION_DE_GOOGLE.'*' => Http::response([
            'status' => 'OK',
            'results' => [['geometry' => ['location' => ['lat' => 10.25, 'lng' => -119.75]]]],
        ])]);

        app(Geocodificador::class)->geocodificar(self::TEXTO, $this->municipio());

        Http::assertSent(fn (Request $peticion): bool => str_starts_with($peticion->url(), self::DIRECCION_DE_GOOGLE));
    }

    #[TestDox('direcciones-E01: una dirección que Google encuentra da el punto de su primer resultado, con una sola consulta')]
    public function test_direcciones_e01_una_direccion_que_google_encuentra_da_el_punto_de_su_primer_resultado(): void
    {
        Http::fake([self::DIRECCION_DE_GOOGLE.'*' => Http::response([
            'status' => 'OK',
            'results' => [
                ['geometry' => ['location' => ['lat' => 10.25, 'lng' => -119.75], 'location_type' => 'ROOFTOP']],
                ['geometry' => ['location' => ['lat' => 11.0, 'lng' => -118.0], 'location_type' => 'APPROXIMATE']],
            ],
        ])]);

        $punto = (new GeocodificadorDeGoogle)->geocodificar(self::TEXTO, $this->municipio());

        $this->assertEquals(new Punto(10.25, -119.75), $punto);

        Http::assertSentCount(1);
        Http::assertSent(function (Request $peticion): bool {
            parse_str((string) parse_url($peticion->url(), PHP_URL_QUERY), $parametros);

            return $peticion->method() === 'GET'
                && str_starts_with($peticion->url(), self::DIRECCION_DE_GOOGLE.'?')
                && $parametros === [
                    'address' => self::TEXTO,
                    // Solo direcciones de México: el territorio del contrato (NSV-2026-001-v8.pdf:4:Cláusula I).
                    'components' => 'country:MX',
                    'key' => self::LLAVE_DE_PRUEBA,
                ];
        });
    }

    #[TestDox('direcciones-E03: una dirección que Google no encuentra no da punto')]
    public function test_direcciones_e03_una_direccion_que_google_no_encuentra_no_da_punto(): void
    {
        Http::fake([self::DIRECCION_DE_GOOGLE.'*' => Http::response(['status' => 'ZERO_RESULTS', 'results' => []])]);

        $this->assertNull((new GeocodificadorDeGoogle)->geocodificar(self::TEXTO, $this->municipio()));
        Http::assertSentCount(1);
    }

    #[TestDox('direcciones-E03: si Google niega la consulta, pasa su límite o falla, no se inventa un punto: el servicio no está disponible')]
    public function test_direcciones_e03_si_google_niega_la_consulta_o_falla_el_servicio_no_esta_disponible(): void
    {
        $respuestas = [
            'REQUEST_DENIED' => Http::response(['status' => 'REQUEST_DENIED', 'error_message' => 'Llave no válida']),
            'OVER_QUERY_LIMIT' => Http::response(['status' => 'OVER_QUERY_LIMIT', 'results' => []]),
            'error 500' => Http::response('Error del servidor', 500),
            'OK sin punto' => Http::response(['status' => 'OK', 'results' => [['geometry' => []]]]),
            'sin red' => fn () => throw new ConnectionException('Sin conexión de prueba'),
        ];

        foreach ($respuestas as $caso => $respuesta) {
            Http::fake([self::DIRECCION_DE_GOOGLE.'*' => $respuesta]);

            try {
                (new GeocodificadorDeGoogle)->geocodificar(self::TEXTO, $this->municipio());
                $this->fail("Con {$caso} debió decir que el servicio no está disponible.");
            } catch (GeocodificacionNoDisponible $error) {
                // La llave no sale en el mensaje, que va a los registros.
                $this->assertStringNotContainsString(self::LLAVE_DE_PRUEBA, $error->getMessage(), $caso);
            }
        }
    }

    #[TestDox('direcciones-E03: sin llave configurada no se consulta a Google y el servicio no está disponible')]
    public function test_direcciones_e03_sin_llave_configurada_no_se_consulta_a_google(): void
    {
        config(['services.geocodificacion.llave' => null]);
        Http::fake();

        $this->expectException(GeocodificacionNoDisponible::class);

        try {
            (new GeocodificadorDeGoogle)->geocodificar(self::TEXTO, $this->municipio());
        } finally {
            Http::assertNothingSent();
        }
    }

    /**
     * Un municipio del catálogo, sin guardar: Google usa solo el texto, que ya lo nombra (design.md, decisión 10).
     */
    private function municipio(): Municipio
    {
        return new Municipio(['codigo' => '99001', 'nombre' => 'Municipio de prueba']);
    }
}
