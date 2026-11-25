<?php

namespace Tests\Feature\Cercanas;

use App\Models\Lavanderia;
use App\Models\Usuario;
use Database\Seeders\LavanderiasDePruebaSeeder;
use Database\Seeders\PlazasDePruebaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * La consulta de lavanderías cercanas: para un punto, las activas de las plazas activas, cada una con su distancia en
 * línea recta calculada en la base con PostGIS, por GET /api/lavanderias/cercanas, con sesión y por el componente de
 * autorización (NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #3; :19:Módulo D #1; ADR-0004 y ADR-0009).
 *
 * Las plazas y las lavanderías son ficticias, en el océano Pacífico. A 10.5 de latitud, una centésima de grado de
 * longitud mide unos 1,095 m.
 *
 * Escenarios: cercanas-E06, cercanas-E07, cercanas-E10 y cercanas-E11 · Spec:
 * openspec/changes/lavanderias-cercanas/specs/cercanas/spec.md
 */
class ConsultaDeCercanasTest extends TestCase
{
    use LavanderiasDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    private const DIRECCION = '/api/lavanderias/cercanas';

    /** El punto de la consulta, dentro de la zona de prueba 1 Centro. */
    private const PUNTO = ['latitud' => 10.5, 'longitud' => -119.5];

    #[TestDox('cercanas-E06: cada lavandería trae su distancia en línea recta desde el punto, calculada en la base')]
    public function test_cercanas_e06_cada_lavanderia_trae_su_distancia_en_linea_recta_calculada_en_la_base(): void
    {
        Http::preventStrayRequests();
        $plaza = $this->plazaDePrueba();
        $aqui = Lavanderia::factory()->en($plaza, latitud: 10.5, longitud: -119.5)->create();
        $cerca = Lavanderia::factory()->en($plaza, latitud: 10.5, longitud: -119.48)->create();
        $lejos = Lavanderia::factory()->en($plaza, latitud: 10.55, longitud: -119.45)->create();

        $respuesta = $this->pedirComoUsuario(self::DIRECCION, self::PUNTO)->assertOk();

        $lavanderias = collect($respuesta->json('lavanderias'))->keyBy('id_publico');
        $this->assertEqualsCanonicalizing(
            [$aqui->id_publico, $cerca->id_publico, $lejos->id_publico],
            $lavanderias->keys()->all(),
        );

        foreach ([$aqui, $cerca, $lejos] as $lavanderia) {
            $fila = $lavanderias[$lavanderia->id_publico];

            // La misma distancia que da PostGIS, redondeada al metro.
            $this->assertSame($this->distanciaEnLaBase($lavanderia), $fila['distancia_metros']);
            $this->assertSame($lavanderia->nombre, $fila['nombre']);
        }

        $this->assertSame(0, $lavanderias[$aqui->id_publico]['distancia_metros']);
        // 0.02 grados de longitud a 10.5 de latitud: unos 2,190 m.
        $this->assertEqualsWithDelta(2190, $lavanderias[$cerca->id_publico]['distancia_metros'], 10);

        // La ubicación de cada una, para su pin en el mapa (cercanas-E12).
        $this->assertEqualsWithDelta(10.55, $lavanderias[$lejos->id_publico]['latitud'], 1e-9);
        $this->assertEqualsWithDelta(-119.45, $lavanderias[$lejos->id_publico]['longitud'], 1e-9);

        // Sin servicios externos de rutas ni de mapas.
        Http::assertNothingSent();
    }

    #[TestDox('cercanas-E06: cada lavandería sale por su identificador público, sin la llave interna ni la ubicación cruda')]
    public function test_cercanas_e06_cada_lavanderia_sale_por_su_identificador_publico(): void
    {
        Lavanderia::factory()->en($this->plazaDePrueba())->create();

        $fila = $this->pedirComoUsuario(self::DIRECCION, self::PUNTO)->assertOk()->json('lavanderias.0');

        $this->assertSame(
            ['id_publico', 'nombre', 'latitud', 'longitud', 'distancia_metros'],
            array_slice(array_keys($fila), 0, 5),
        );
        $this->assertArrayNotHasKey('id_lavanderias', $fila);
        $this->assertArrayNotHasKey('ubicacion', $fila);
        $this->assertArrayNotHasKey('id_plazas', $fila);
    }

    #[TestDox('cercanas-E06: sin punto, o con un punto fuera de rango, la consulta no se hace y dice qué falta')]
    public function test_cercanas_e06_sin_punto_o_con_un_punto_fuera_de_rango_la_consulta_no_se_hace(): void
    {
        Lavanderia::factory()->en($this->plazaDePrueba())->create();

        $this->pedirComoUsuario(self::DIRECCION)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['latitud', 'longitud'])
            ->assertJsonMissingPath('lavanderias');

        $this->pedirComoUsuario(self::DIRECCION, ['latitud' => 91, 'longitud' => -181])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['latitud', 'longitud']);

        $this->pedirComoUsuario(self::DIRECCION, ['latitud' => 'norte', 'longitud' => -119.5])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['latitud']);
    }

    #[TestDox('cercanas-E07: una suspendida, una dada de baja y una de una plaza que no está activa no aparecen, aunque estén cerca')]
    public function test_cercanas_e07_suspendida_dada_de_baja_o_de_una_plaza_no_activa_no_aparece(): void
    {
        $plaza = $this->plazaDePrueba('Plaza de prueba 1');
        $noActiva = $this->plazaDePrueba('Plaza de prueba no activa', activa: false);

        $activa = Lavanderia::factory()->en($plaza, longitud: -119.499)->create();
        Lavanderia::factory()->en($plaza, longitud: -119.498)->suspendida()->create();
        Lavanderia::factory()->en($plaza, longitud: -119.497)->deBaja()->create();
        Lavanderia::factory()->en($noActiva, longitud: -119.496)->create();
        // Y una borrada en suave.
        Lavanderia::factory()->en($plaza, longitud: -119.495)->create()->delete();

        $respuesta = $this->pedirComoUsuario(self::DIRECCION, self::PUNTO)->assertOk();

        $this->assertSame([$activa->id_publico], array_column($respuesta->json('lavanderias'), 'id_publico'));
    }

    #[TestDox('cercanas-E10: sin sesión, el servidor rechaza la consulta sin devolver lavanderías')]
    public function test_cercanas_e10_sin_sesion_el_servidor_rechaza_la_consulta_sin_devolver_lavanderias(): void
    {
        $lavanderia = Lavanderia::factory()->en($this->plazaDePrueba())->create();

        $respuesta = $this->pedirSinSesion(self::DIRECCION, self::PUNTO);

        $respuesta->assertUnauthorized();
        $respuesta->assertJsonMissingPath('lavanderias');
        $this->assertStringNotContainsString($lavanderia->id_publico, $respuesta->getContent());
    }

    #[TestDox('cercanas-E10: una cuenta sin el rol de usuario no pasa el componente de autorización y no recibe lavanderías')]
    public function test_cercanas_e10_una_cuenta_sin_el_rol_de_usuario_no_recibe_lavanderias(): void
    {
        $lavanderia = Lavanderia::factory()->en($this->plazaDePrueba())->create();

        foreach ([$lavanderia->administrador, Usuario::factory()->create()] as $cuenta) {
            $respuesta = $this->pedirComoUsuario(self::DIRECCION, self::PUNTO, $cuenta);

            $respuesta->assertForbidden();
            $respuesta->assertJsonMissingPath('lavanderias');
            $this->assertStringNotContainsString($lavanderia->id_publico, $respuesta->getContent());
        }
    }

    #[TestDox('cercanas-E11: un punto sin lavanderías activas alrededor, como Monterrey, responde una lista vacía, sin error')]
    public function test_cercanas_e11_un_punto_sin_lavanderias_activas_alrededor_responde_una_lista_vacia(): void
    {
        // Monterrey: la app se puede descargar ahí, pero no se opera. Ninguna plaza cubre el punto, y la única
        // lavandería cercana está suspendida.
        $plaza = $this->plazaDePrueba();
        Lavanderia::factory()->en($plaza, latitud: 25.6866, longitud: -100.3161)->suspendida()->create();

        $respuesta = $this->pedirComoUsuario(self::DIRECCION, ['latitud' => 25.6866, 'longitud' => -100.3161]);

        $respuesta->assertOk();
        $this->assertSame(['lavanderias' => []], $respuesta->json());
    }

    #[TestDox('cercanas-E06 y cercanas-E07: las lavanderías de prueba sembradas aparecen con su plaza, su ubicación y su estado')]
    public function test_cercanas_e06_y_e07_las_lavanderias_de_prueba_sembradas_aparecen(): void
    {
        $this->seed([PlazasDePruebaSeeder::class, LavanderiasDePruebaSeeder::class]);

        // Cada una quedó en la plaza que contiene su punto.
        $plazas = DB::select(
            'select l.nombre, p.nombre as plaza
               from lavanderias l
               join plazas p on p.id_plazas = l.id_plazas
               join zonas z on z.id_plazas = l.id_plazas and ST_Covers(z.poligono, l.ubicacion)
              order by l.nombre',
        );
        $this->assertSame([
            ['nombre' => 'Lavandería de prueba 1', 'plaza' => 'Plaza de prueba 1'],
            ['nombre' => 'Lavandería de prueba 2', 'plaza' => 'Plaza de prueba 1'],
            ['nombre' => 'Lavandería de prueba 3', 'plaza' => 'Plaza de prueba 1'],
            ['nombre' => 'Lavandería de prueba 4', 'plaza' => 'Plaza de prueba 2'],
            ['nombre' => 'Lavandería de prueba Durango Centro', 'plaza' => 'Plaza de prueba Durango'],
            ['nombre' => 'Lavandería de prueba Durango Norte', 'plaza' => 'Plaza de prueba Durango'],
            ['nombre' => 'Lavandería de prueba Durango Sur', 'plaza' => 'Plaza de prueba Durango'],
            ['nombre' => 'Lavandería de prueba suspendida', 'plaza' => 'Plaza de prueba 1'],
        ], array_map(fn (object $fila): array => (array) $fila, $plazas));

        $respuesta = $this->pedirComoUsuario(self::DIRECCION, self::PUNTO)->assertOk();

        // Las activas, sin la suspendida. Sin el límite de cercanía de cercanas-E08 (DD-083), también llegan las de
        // Durango, con su distancia.
        $this->assertEqualsCanonicalizing(
            [
                'Lavandería de prueba 1', 'Lavandería de prueba 2', 'Lavandería de prueba 3', 'Lavandería de prueba 4',
                'Lavandería de prueba Durango Centro', 'Lavandería de prueba Durango Norte',
                'Lavandería de prueba Durango Sur',
            ],
            array_column($respuesta->json('lavanderias'), 'nombre'),
        );
    }

    /**
     * La distancia de PostGIS entre la lavandería y el punto de la consulta, en metros, redondeada.
     */
    private function distanciaEnLaBase(Lavanderia $lavanderia): int
    {
        return (int) DB::scalar(
            'select round(ST_Distance(ubicacion, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography))
               from lavanderias where id_lavanderias = ?',
            [self::PUNTO['longitud'], self::PUNTO['latitud'], $lavanderia->getKey()],
        );
    }
}
