<?php

namespace Tests\Feature\AltaLavanderias;

use App\Direcciones\GeocodificacionNoDisponible;
use App\Direcciones\Geocodificador;
use App\Direcciones\Punto;
use App\Models\Municipio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\AltaLavanderias\Soporte\AltaDePrueba;
use Tests\Feature\Direcciones\Soporte\CatalogoDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * La plaza de la lavandería sale de su dirección: el servidor la geocodifica una sola vez, guarda su punto y le asigna
 * la plaza de la zona de servicio que lo contiene. Una dirección fuera de las zonas, o que la geocodificación no ubica,
 * no se registra (NSV-2026-001-v8.pdf:4:Cláusula I; ADR-0004; ADR-0009).
 *
 * La geocodificación es simulada (App\Direcciones\Geocodificador): ninguna prueba gasta consultas ni necesita llave.
 *
 * Escenarios: alta-lavanderias-E09 y alta-lavanderias-E10 · Tarea 1.3 · Spec:
 * openspec/changes/alta-de-lavanderias/specs/alta-lavanderias/spec.md
 */
class PlazaPorDireccionTest extends TestCase
{
    use AltaDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararAlta();
    }

    #[TestDox('alta-lavanderias-E09: la dirección dentro de una zona se geocodifica una sola vez y la lavandería queda en la plaza de esa zona')]
    public function test_alta_lavanderias_e09_la_direccion_se_geocodifica_una_vez_y_queda_en_la_plaza_de_su_zona(): void
    {
        // Dos plazas cargadas; el punto cae en la zona de la segunda.
        $otra = $this->plazaDePrueba('Plaza de prueba 2');
        $this->zonaDePrueba($otra, 'Zona de prueba Norte', $this->cuadrado(-116.0, 10.0));
        $this->geocodificarEn([10.5, -115.5]);
        $cuenta = $this->cuentaDeLavanderia();

        $this->registrar($cuenta, $this->registroCompleto())->assertCreated();

        $lavanderia = $this->lavanderiaDe($cuenta);
        $this->assertSame($otra->getKey(), $lavanderia->id_plazas);
        $this->assertSame([10.5, -115.5], $this->ubicacionDe($lavanderia->getKey()));

        // Una sola consulta, con el municipio y el estado del catálogo en el texto.
        $this->assertSame(['Calle de prueba 123, Colonia de prueba, 99000 Municipio de prueba, Estado de prueba'], $this->geocodificador->consultas);
        $this->assertSame([$this->municipio->codigo], $this->geocodificador->municipios);

        // Las hijas llevan la misma plaza de su lavandería.
        foreach (['lavanderia_servicios', 'lavanderia_horarios', 'lavanderia_fotos'] as $tabla) {
            $this->assertSame([$otra->getKey()], DB::table($tabla)->distinct()->pluck('id_plazas')->all(), $tabla);
        }
    }

    #[TestDox('alta-lavanderias-E09: el municipio del catálogo va en el texto que se geocodifica, con su estado, para Matamoros, Coahuila')]
    public function test_alta_lavanderias_e09_el_municipio_y_su_estado_van_en_el_texto(): void
    {
        $matamoros = CatalogoDePrueba::territorio()[CatalogoDePrueba::MATAMOROS];
        $cuenta = $this->cuentaDeLavanderia();

        $this->registrar($cuenta, $this->registroCompleto(['direccion' => [
            ...$this->registroCompleto()['direccion'],
            'municipio' => $matamoros->codigo,
            'codigo_postal' => '27440',
        ]]))->assertCreated();

        $this->assertSame(['Calle de prueba 123, Colonia de prueba, 27440 Matamoros, Coahuila de Zaragoza'], $this->geocodificador->consultas);
        $this->assertTrue($matamoros->is(Municipio::find($this->lavanderiaDe($cuenta)->id_municipios)));
    }

    #[TestDox('alta-lavanderias-E10: una dirección fuera de todas las zonas se rechaza en el campo de la dirección y no se guarda nada')]
    public function test_alta_lavanderias_e10_una_direccion_fuera_de_las_zonas_se_rechaza(): void
    {
        $this->geocodificarEn([30.0, -100.0]);

        $this->registrar($this->cuentaDeLavanderia(), $this->registroCompleto())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['direccion'])
            ->assertJsonPath('motivo', 'fuera_de_la_zona_de_servicio')
            ->assertJsonPath('errors.direccion.0', 'La dirección está fuera de las zonas de servicio.');

        $this->assertNadaGuardado();
    }

    #[TestDox('alta-lavanderias-E10: una zona que no está activa, o de una plaza que no está activa, no da servicio')]
    public function test_alta_lavanderias_e10_una_zona_o_una_plaza_no_activas_no_dan_servicio(): void
    {
        $this->zona->update(['activa' => false]);

        $this->registrar($this->cuentaDeLavanderia(), $this->registroCompleto())
            ->assertUnprocessable()
            ->assertJsonPath('motivo', 'fuera_de_la_zona_de_servicio');

        $this->zona->update(['activa' => true]);
        $this->plaza->update(['activa' => false]);

        $this->registrar($this->cuentaDeLavanderia(), $this->registroCompleto())
            ->assertUnprocessable()
            ->assertJsonPath('motivo', 'fuera_de_la_zona_de_servicio');

        $this->assertNadaGuardado(consultas: 2);
    }

    #[TestDox('alta-lavanderias-E10: una dirección que la geocodificación no ubica se rechaza en el campo de la dirección y no se guarda nada')]
    public function test_alta_lavanderias_e10_una_direccion_que_no_se_ubica_se_rechaza(): void
    {
        $this->geocodificarEn(null);

        $this->registrar($this->cuentaDeLavanderia(), $this->registroCompleto())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['direccion'])
            ->assertJsonPath('motivo', 'direccion_no_encontrada')
            ->assertJsonPath('errors.direccion.0', 'No encontramos la dirección. Revisa la calle, el número, la colonia y el código postal.');

        $this->assertNadaGuardado();
    }

    #[TestDox('alta-lavanderias-E10: si la geocodificación no responde, no se guarda nada y se puede volver a intentar')]
    public function test_alta_lavanderias_e10_si_la_geocodificacion_no_responde_no_se_guarda_nada(): void
    {
        $this->app->instance(Geocodificador::class, new class implements Geocodificador
        {
            public function geocodificar(string $texto, Municipio $municipio): ?Punto
            {
                throw GeocodificacionNoDisponible::porque('sin red');
            }
        });

        $this->registrar($this->cuentaDeLavanderia(), $this->registroCompleto())
            ->assertServiceUnavailable()
            ->assertJsonPath('motivo', 'geocodificacion_no_disponible');

        $this->assertNadaGuardado(consultas: 0);
    }

    private function assertNadaGuardado(int $consultas = 1): void
    {
        foreach (['lavanderias', 'lavanderia_servicios', 'lavanderia_horarios', 'lavanderia_fotos'] as $tabla) {
            $this->assertSame(0, DB::table($tabla)->count(), "{$tabla} tiene filas.");
        }

        $this->assertSame([], $this->archivosDeFotos());
        $this->assertCount($consultas, $this->geocodificador->consultas);
    }

    /**
     * @return array{float, float}
     */
    private function ubicacionDe(int $lavanderia): array
    {
        $punto = DB::selectOne(
            'select ST_Y(ubicacion::geometry) as latitud, ST_X(ubicacion::geometry) as longitud
               from lavanderias where id_lavanderias = ?',
            [$lavanderia],
        );

        return [(float) $punto->latitud, (float) $punto->longitud];
    }
}
