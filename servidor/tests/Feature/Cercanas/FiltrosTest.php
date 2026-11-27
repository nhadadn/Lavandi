<?php

namespace Tests\Feature\Cercanas;

use App\Models\Calificacion;
use App\Models\Lavanderia;
use App\Models\Plaza;
use Database\Seeders\LavanderiasDePruebaSeeder;
use Database\Seeders\PlazasDePruebaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * Los filtros de la consulta de cercanas: distancia máxima en línea recta y calificación promedio mínima, combinables,
 * con el promedio de las calificaciones de cada lavandería calculado en la consulta
 * (NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #4; ADR-0004 y ADR-0009;
 * openspec/changes/lavanderias-cercanas/design.md, decisión 4).
 *
 * La distancia se mide al metro: una lavandería a 3 km pasa el filtro de 3,000 m. Los puntos a 1, 3 y 6 km los calcula
 * PostGIS con ST_Project, al oriente del punto de la consulta.
 *
 * Escenarios: cercanas-E16, cercanas-E17 y cercanas-E18 · Spec:
 * openspec/changes/lavanderias-cercanas/specs/cercanas/spec.md
 */
class FiltrosTest extends TestCase
{
    use LavanderiasDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    private const DIRECCION = '/api/lavanderias/cercanas';

    private const PUNTO = ['latitud' => 10.5, 'longitud' => -119.5];

    #[TestDox('cercanas-E16: con una distancia máxima de 3 km, el servidor devuelve solo las que están a 1 y a 3 km')]
    public function test_cercanas_e16_con_distancia_maxima_de_3_km_devuelve_solo_las_de_1_y_3_km(): void
    {
        $plaza = $this->plazaDePrueba();
        $aUno = $this->lavanderiaA($plaza, 1000);
        $aTres = $this->lavanderiaA($plaza, 3000);
        $this->lavanderiaA($plaza, 6000);

        $respuesta = $this->pedirComoUsuario(self::DIRECCION, [...self::PUNTO, 'distancia_maxima_metros' => 3000]);

        $respuesta->assertOk();
        $this->assertEqualsCanonicalizing(
            [$aUno->id_publico, $aTres->id_publico],
            array_column($respuesta->json('lavanderias'), 'id_publico'),
        );
        $this->assertEqualsCanonicalizing([1000, 3000], array_column($respuesta->json('lavanderias'), 'distancia_metros'));

        // Sin el filtro, salen las tres.
        $this->assertCount(3, $this->pedirComoUsuario(self::DIRECCION, self::PUNTO)->json('lavanderias'));
    }

    #[TestDox('cercanas-E17: con una calificación mínima de 4, el servidor devuelve solo las de promedio 4.2 y 4.8')]
    public function test_cercanas_e17_con_calificacion_minima_de_4_devuelve_solo_las_de_4_2_y_4_8(): void
    {
        $plaza = $this->plazaDePrueba();
        $deTresYMedio = Lavanderia::factory()->en($plaza)->create();
        $deCuatroDos = Lavanderia::factory()->en($plaza)->create();
        $deCuatroOcho = Lavanderia::factory()->en($plaza)->create();
        $this->calificar($deTresYMedio, [4, 3]);
        $this->calificar($deCuatroDos, [5, 4, 4, 4, 4]);
        $this->calificar($deCuatroOcho, [5, 5, 5, 5, 4]);

        $respuesta = $this->pedirComoUsuario(self::DIRECCION, [...self::PUNTO, 'calificacion_minima' => 4]);

        $respuesta->assertOk();
        $lavanderias = collect($respuesta->json('lavanderias'))->keyBy('id_publico');
        $this->assertEqualsCanonicalizing(
            [$deCuatroDos->id_publico, $deCuatroOcho->id_publico],
            $lavanderias->keys()->all(),
        );
        $this->assertSame(4.2, $lavanderias[$deCuatroDos->id_publico]['calificacion_promedio']);
        $this->assertSame(4.8, $lavanderias[$deCuatroOcho->id_publico]['calificacion_promedio']);
        $this->assertSame(5, $lavanderias[$deCuatroOcho->id_publico]['numero_de_calificaciones']);

        // Sin el filtro, salen las tres, cada una con su promedio.
        $todas = collect($this->pedirComoUsuario(self::DIRECCION, self::PUNTO)->json('lavanderias'))->keyBy('id_publico');
        $this->assertSame(3.5, $todas[$deTresYMedio->id_publico]['calificacion_promedio']);
        $this->assertSame(2, $todas[$deTresYMedio->id_publico]['numero_de_calificaciones']);
    }

    #[TestDox('cercanas-E17: cada lavandería trae el promedio y el número de sus calificaciones, sin mezclar las de otra')]
    public function test_cercanas_e17_cada_lavanderia_trae_el_promedio_y_el_numero_de_sus_calificaciones(): void
    {
        $plaza = $this->plazaDePrueba();
        $calificada = Lavanderia::factory()->en($plaza)->create();
        $sinCalificar = Lavanderia::factory()->en($plaza)->create();
        $this->calificar($calificada, [5, 4]);

        $lavanderias = collect($this->pedirComoUsuario(self::DIRECCION, self::PUNTO)->json('lavanderias'))->keyBy('id_publico');

        $this->assertSame(4.5, $lavanderias[$calificada->id_publico]['calificacion_promedio']);
        $this->assertSame(2, $lavanderias[$calificada->id_publico]['numero_de_calificaciones']);
        $this->assertNull($lavanderias[$sinCalificar->id_publico]['calificacion_promedio']);
        $this->assertSame(0, $lavanderias[$sinCalificar->id_publico]['numero_de_calificaciones']);
    }

    #[TestDox('cercanas-E18: con distancia máxima y calificación mínima a la vez, el servidor devuelve solo las que cumplen las dos')]
    public function test_cercanas_e18_los_filtros_se_combinan(): void
    {
        $plaza = $this->plazaDePrueba();
        $soloDistancia = $this->lavanderiaA($plaza, 1000);
        $soloCalificacion = $this->lavanderiaA($plaza, 6000);
        $lasDos = $this->lavanderiaA($plaza, 2000);
        $ninguna = $this->lavanderiaA($plaza, 7000);
        $this->calificar($soloDistancia, [3, 3]);
        $this->calificar($soloCalificacion, [5, 5]);
        $this->calificar($lasDos, [5, 4]);
        $this->calificar($ninguna, [2, 3]);

        $filtros = [...self::PUNTO, 'distancia_maxima_metros' => 3000, 'calificacion_minima' => 4];
        $respuesta = $this->pedirComoUsuario(self::DIRECCION, $filtros)->assertOk();

        $this->assertSame([$lasDos->id_publico], array_column($respuesta->json('lavanderias'), 'id_publico'));

        // Cada filtro solo, para ver que los dos cuentan.
        $this->assertEqualsCanonicalizing(
            [$soloDistancia->id_publico, $lasDos->id_publico],
            array_column($this->pedirComoUsuario(self::DIRECCION, [...self::PUNTO, 'distancia_maxima_metros' => 3000])->json('lavanderias'), 'id_publico'),
        );
        $this->assertEqualsCanonicalizing(
            [$soloCalificacion->id_publico, $lasDos->id_publico],
            array_column($this->pedirComoUsuario(self::DIRECCION, [...self::PUNTO, 'calificacion_minima' => 4])->json('lavanderias'), 'id_publico'),
        );
    }

    #[TestDox('cercanas-E16 y cercanas-E17: un filtro fuera de rango no se aplica a medias: la consulta dice qué está mal')]
    public function test_cercanas_e16_y_e17_un_filtro_fuera_de_rango_se_rechaza(): void
    {
        Lavanderia::factory()->en($this->plazaDePrueba())->create();

        foreach ([0, -5, 1.5, 'cerca'] as $distancia) {
            $this->pedirComoUsuario(self::DIRECCION, [...self::PUNTO, 'distancia_maxima_metros' => $distancia])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['distancia_maxima_metros'])
                ->assertJsonMissingPath('lavanderias');
        }

        foreach ([0, 5.5, 'buena'] as $calificacion) {
            $this->pedirComoUsuario(self::DIRECCION, [...self::PUNTO, 'calificacion_minima' => $calificacion])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['calificacion_minima'])
                ->assertJsonMissingPath('lavanderias');
        }
    }

    #[TestDox('cercanas-E17: el sembrado deja calificaciones de prueba de lavandería, y el filtro las usa')]
    public function test_cercanas_e17_el_sembrado_deja_calificaciones_de_prueba_de_lavanderia(): void
    {
        $this->seed([PlazasDePruebaSeeder::class, LavanderiasDePruebaSeeder::class]);

        // Todas las sembradas son de una lavandería, de 1 a 5, de una cuenta de prueba.
        $this->assertGreaterThan(0, Calificacion::count());
        $this->assertSame(0, Calificacion::whereNull('id_lavanderias')->count());
        $this->assertSame(0, Calificacion::whereNotBetween('estrellas', [1, 5])->count());

        $respuesta = $this->pedirComoUsuario(self::DIRECCION, [...self::PUNTO, 'calificacion_minima' => 4])->assertOk();

        $this->assertEqualsCanonicalizing(
            [
                ['Lavandería de prueba 1', 4.8], ['Lavandería de prueba 2', 4.2],
                // Las de Durango también llegan, sin límite de cercanía (DD-083): 4.75 pasa; 3.67 y la que no tiene
                // calificaciones, no.
                ['Lavandería de prueba Durango Centro', 4.75],
            ],
            array_map(
                fn (array $fila): array => [$fila['nombre'], $fila['calificacion_promedio']],
                $respuesta->json('lavanderias'),
            ),
        );
    }

    /**
     * Una lavandería activa a esa distancia del punto de la consulta, al oriente, calculada por PostGIS.
     */
    private function lavanderiaA(Plaza $plaza, int $metros): Lavanderia
    {
        $punto = DB::selectOne(
            'select ST_Y(p::geometry) as latitud, ST_X(p::geometry) as longitud
               from (select ST_Project(ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?::float8, radians(90)) as p) as proyectado',
            [self::PUNTO['longitud'], self::PUNTO['latitud'], $metros],
        );

        return Lavanderia::factory()->en($plaza, latitud: (float) $punto->latitud, longitud: (float) $punto->longitud)->create();
    }
}
