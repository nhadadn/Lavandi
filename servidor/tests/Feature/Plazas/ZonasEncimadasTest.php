<?php

namespace Tests\Feature\Plazas;

use App\Models\Plaza;
use App\Models\Zona;
use App\Plazas\ResolucionDePunto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * Dos zonas no se enciman: la carga rechaza un archivo con una zona que comparte área con otra, guardada o del mismo
 * archivo, de la misma plaza o de otra, y no deja nada de él. Dos zonas que solo se tocan en el borde sí se cargan.
 * Así, un punto cae a lo más en una zona (DD-134, punto 1; C-234).
 *
 * Las plazas y zonas son ficticias: cuadrados de un grado en el océano Pacífico. La zona guardada de cada prueba es
 * el cuadrado de -120 a -119 de longitud y de 10 a 11 de latitud.
 *
 * Escenario: plazas-E12 · Spec: openspec/changes/plazas-y-zonas/specs/plazas/spec.md
 */
class ZonasEncimadasTest extends TestCase
{
    use PlazasDePrueba;
    use RefreshDatabase;

    private const GUARDADA = 'Zona de prueba Centro';

    #[TestDox('plazas-E12: una zona que comparte área con una zona guardada de la misma plaza se rechaza, y no queda nada del archivo')]
    public function test_plazas_e12_una_zona_que_comparte_area_con_una_zona_guardada_de_la_misma_plaza_se_rechaza(): void
    {
        $this->guardarPlazaConSuZona();

        // La mitad oriental de la nueva cae sobre la guardada, y la nueva no la trae en su archivo.
        $archivo = $this->archivoDePlaza('Plaza de prueba 1', [
            'Zona de prueba Sur' => $this->cuadrado(oeste: -120.0, sur: 8.0),
            'Zona de prueba Encimada' => $this->cuadrado(oeste: -119.5, sur: 10.0),
        ]);

        $this->artisan('plazas:cargar', ['archivo' => $archivo])
            ->expectsOutputToContain('no quedó nada de él')
            ->expectsOutputToContain(
                'La zona Zona de prueba Encimada comparte área con la zona Zona de prueba Centro de la plaza Plaza de prueba 1',
            )
            ->assertFailed();

        // No quedó nada del archivo: ni sus zonas, ni la desactivación de la guardada que no venía en él.
        $this->assertSame([self::GUARDADA], Zona::pluck('nombre')->all());
        $this->assertTrue(Zona::sole()->activa);
    }

    #[TestDox('plazas-E12: una zona que comparte área con una zona guardada de otra plaza se rechaza, y no queda nada del archivo')]
    public function test_plazas_e12_una_zona_que_comparte_area_con_una_zona_guardada_de_otra_plaza_se_rechaza(): void
    {
        $guardada = $this->guardarPlazaConSuZona();

        // Que se cruza con la guardada, que la contiene, que está dentro de ella sin tocar su borde, y la misma.
        $encimadas = [
            'que se cruza con ella' => $this->cuadrado(oeste: -119.5, sur: 10.5),
            'que la contiene' => $this->cuadrado(oeste: -121.0, sur: 9.0, lado: 3.0),
            'que está dentro de ella' => $this->cuadrado(oeste: -119.75, sur: 10.25, lado: 0.5),
            'igual a ella' => $this->cuadrado(oeste: -120.0, sur: 10.0),
        ];

        foreach ($encimadas as $caso => $poligono) {
            $archivo = $this->archivoDePlaza('Plaza de prueba 2', ['Zona de prueba Encimada' => $poligono]);

            $this->artisan('plazas:cargar', ['archivo' => $archivo])
                ->expectsOutputToContain(
                    'La zona Zona de prueba Encimada comparte área con la zona Zona de prueba Centro de la plaza Plaza de prueba 1',
                )
                ->assertFailed();

            $this->assertSame(['Plaza de prueba 1'], Plaza::pluck('nombre')->all(), $caso);
            $this->assertSame(1, Zona::count(), $caso);
        }

        // Una zona guardada que no está activa sigue guardada: también cuenta, porque puede volver a activarse.
        $guardada->update(['activa' => false]);
        $archivo = $this->archivoDePlaza('Plaza de prueba 2', ['Zona de prueba Encimada' => $encimadas['igual a ella']]);
        $this->artisan('plazas:cargar', ['archivo' => $archivo])
            ->expectsOutputToContain('comparte área con la zona Zona de prueba Centro')
            ->assertFailed();
        $this->assertSame(1, Plaza::count());
    }

    #[TestDox('plazas-E12: un archivo con dos zonas que comparten área entre sí se rechaza, y no queda nada de él')]
    public function test_plazas_e12_un_archivo_con_dos_zonas_que_comparten_area_entre_si_se_rechaza(): void
    {
        $archivo = $this->archivoDePlaza('Plaza de prueba 1', [
            'Zona de prueba Poniente' => $this->cuadrado(oeste: -120.0, sur: 10.0),
            'Zona de prueba Encimada' => $this->cuadrado(oeste: -119.5, sur: 10.5),
        ]);

        $this->artisan('plazas:cargar', ['archivo' => $archivo])
            ->expectsOutputToContain('no quedó nada de él')
            ->expectsOutputToContain('Las zonas Zona de prueba Poniente y Zona de prueba Encimada del archivo comparten área')
            ->assertFailed();

        $this->assertSame(0, Plaza::count());
        $this->assertSame(0, Zona::count());
    }

    #[TestDox('plazas-E12: dos zonas que solo se tocan en el borde sí se cargan')]
    public function test_plazas_e12_dos_zonas_que_solo_se_tocan_en_el_borde_si_se_cargan(): void
    {
        $this->guardarPlazaConSuZona();

        // Del mismo archivo, de la misma plaza: un lado completo y una esquina.
        $this->artisan('plazas:cargar', ['archivo' => $this->archivoDePlaza('Plaza de prueba 1', [
            self::GUARDADA => $this->cuadrado(oeste: -120.0, sur: 10.0),
            'Zona de prueba Oriente' => $this->cuadrado(oeste: -119.0, sur: 10.0),
            'Zona de prueba Noreste' => $this->cuadrado(oeste: -118.0, sur: 11.0),
        ])])->assertSuccessful();

        // De otra plaza, contra las zonas guardadas: al norte de la Centro, con medio lado en común, y al sur de la
        // Oriente, con su lado completo.
        $this->artisan('plazas:cargar', ['archivo' => $this->archivoDePlaza('Plaza de prueba 2', [
            'Zona de prueba Norte' => $this->cuadrado(oeste: -119.5, sur: 11.0),
            'Zona de prueba Sur' => $this->cuadrado(oeste: -119.0, sur: 9.0),
        ])])->assertSuccessful();

        $this->assertSame(5, Zona::where('activa', true)->count());

        // Cada punto del interior cae en su zona.
        $resolucion = app(ResolucionDePunto::class);
        $this->assertSame('Zona de prueba Oriente', $resolucion->resolver(10.5, -118.5)?->zona->nombre);
        $this->assertSame('Zona de prueba Norte', $resolucion->resolver(11.5, -119.0)?->zona->nombre);
        $this->assertSame('Zona de prueba Sur', $resolucion->resolver(9.5, -118.5)?->zona->nombre);
    }

    #[TestDox('plazas-E12: la zona que se recarga no se compara consigo misma')]
    public function test_plazas_e12_la_zona_que_se_recarga_no_se_compara_consigo_misma(): void
    {
        $guardada = $this->guardarPlazaConSuZona();

        // Se mueve medio grado al oriente: comparte área con donde estaba, que es ella misma.
        $this->artisan('plazas:cargar', ['archivo' => $this->archivoDePlaza('Plaza de prueba 1', [
            self::GUARDADA => $this->cuadrado(oeste: -119.5, sur: 10.0),
        ])])->assertSuccessful();

        $this->assertTrue($guardada->is(Zona::sole()));
        $this->assertTrue($guardada->is(app(ResolucionDePunto::class)->resolver(10.5, -119.25)?->zona));
        $this->assertNull(app(ResolucionDePunto::class)->resolver(10.5, -119.75));
    }

    /**
     * La Plaza de prueba 1, cargada con el comando, con su zona guardada.
     */
    private function guardarPlazaConSuZona(): Zona
    {
        $this->artisan('plazas:cargar', ['archivo' => $this->archivoDePlaza('Plaza de prueba 1', [
            self::GUARDADA => $this->cuadrado(oeste: -120.0, sur: 10.0),
        ])])->assertSuccessful();

        return Zona::sole();
    }
}
