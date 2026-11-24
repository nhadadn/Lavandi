<?php

namespace Tests\Feature\Plazas;

use App\Models\Plaza;
use App\Models\Zona;
use App\Plazas\ResolucionDePunto;
use Database\Seeders\PlazasDePruebaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Abrir una plaza es cargar datos: un comando lee el archivo GeoJSON de una plaza y sus zonas, sin migraciones de
 * esquema ni código nuevo, y sin pantalla, porque el Módulo D no la enumera (NSV-2026-001-v8.pdf:4:Cláusula I,
 * NSV-2026-001-v8.pdf:37:ANEXO C, Sección C.3 y NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D).
 *
 * Las plazas de prueba son ficticias y solo se siembran fuera de producción (design.md, decisión 6; ADR-0012).
 *
 * Escenario: plazas-E08 · Spec: openspec/changes/plazas-y-zonas/specs/plazas/spec.md
 */
class AbrirPlazaConDatosTest extends TestCase
{
    use RefreshDatabase;

    private const ARCHIVO = __DIR__.'/Datos/plaza-de-prueba-nueva.geojson';

    /** Las plazas que deja el sembrado de prueba, por nombre. */
    private const PLAZAS_DE_PRUEBA = ['Plaza de prueba 1', 'Plaza de prueba 2', 'Plaza de prueba Durango'];

    /** @var list<string> */
    private array $temporales = [];

    protected function tearDown(): void
    {
        foreach ($this->temporales as $archivo) {
            @unlink($archivo);
        }

        parent::tearDown();
    }

    #[TestDox('plazas-E08: una plaza nueva se abre cargando su archivo, sin una sola migración')]
    public function test_plazas_e08_una_plaza_nueva_se_abre_cargando_su_archivo_sin_una_sola_migracion(): void
    {
        $migraciones = $this->migraciones();
        $this->assertNull($this->plazaDelPunto(latitud: 5.5, longitud: -129.5));

        $this->artisan('plazas:cargar', ['archivo' => self::ARCHIVO])
            ->expectsOutputToContain('Plaza de prueba nueva: plaza nueva y activa, con 1 zona(s).')
            ->assertSuccessful();

        $plaza = Plaza::where('nombre', 'Plaza de prueba nueva')->sole();
        $this->assertTrue($plaza->activa);
        $this->assertSame('America/Mexico_City', $plaza->zona_horaria);
        $this->assertSame(['Zona de prueba nueva'], $plaza->zonas()->pluck('nombre')->all());

        // Un punto dentro de su zona resuelve a la plaza nueva.
        $this->assertTrue($plaza->is($this->plazaDelPunto(latitud: 5.5, longitud: -129.5)));

        // La tabla de migraciones tiene las mismas filas que antes de la carga.
        $this->assertSame($migraciones, $this->migraciones());
    }

    #[TestDox('plazas-E08: volver a cargar el mismo archivo no duplica la plaza ni su zona')]
    public function test_plazas_e08_volver_a_cargar_el_mismo_archivo_no_duplica_la_plaza_ni_su_zona(): void
    {
        $this->artisan('plazas:cargar', ['archivo' => self::ARCHIVO])->assertSuccessful();
        $plaza = Plaza::sole();
        $zona = Zona::sole();

        $this->artisan('plazas:cargar', ['archivo' => self::ARCHIVO])
            ->expectsOutputToContain('Plaza de prueba nueva: plaza actualizada y activa, con 1 zona(s).')
            ->assertSuccessful();

        $this->assertSame(1, Plaza::count());
        $this->assertSame(1, Zona::count());
        $this->assertTrue($plaza->is(Plaza::sole()));
        $this->assertTrue($zona->is(Zona::sole()));
        $this->assertTrue($plaza->is($this->plazaDelPunto(latitud: 5.5, longitud: -129.5)));
    }

    #[TestDox('plazas-E08: volver a cargar el archivo con cambios actualiza la plaza y su zona')]
    public function test_plazas_e08_volver_a_cargar_el_archivo_con_cambios_actualiza_la_plaza_y_su_zona(): void
    {
        $this->artisan('plazas:cargar', ['archivo' => self::ARCHIVO])->assertSuccessful();

        $datos = $this->datos();
        $datos['plaza']['zona_horaria'] = 'America/Tijuana';
        // La zona se mueve un grado al norte.
        $datos['features'][0]['geometry']['coordinates'] = [[
            [-130.0, 6.0], [-129.0, 6.0], [-129.0, 7.0], [-130.0, 7.0], [-130.0, 6.0],
        ]];

        $this->artisan('plazas:cargar', ['archivo' => $this->archivoCon($datos)])->assertSuccessful();

        $plaza = Plaza::sole();
        $this->assertSame('America/Tijuana', $plaza->zona_horaria);
        $this->assertSame(1, Zona::count());
        $this->assertTrue($plaza->is($this->plazaDelPunto(latitud: 6.5, longitud: -129.5)));
        $this->assertNull($this->plazaDelPunto(latitud: 5.5, longitud: -129.5));
    }

    #[TestDox('plazas-E08: un archivo que no se puede cargar no deja ni la plaza ni sus zonas')]
    public function test_plazas_e08_un_archivo_que_no_se_puede_cargar_no_deja_ni_la_plaza_ni_sus_zonas(): void
    {
        $sinZonaHorariaValida = $this->datos();
        $sinZonaHorariaValida['plaza']['zona_horaria'] = 'America/Ciudad_Inventada';

        // La primera zona es buena; la segunda es un punto, no un polígono.
        $conUnaZonaQueNoEsPoligono = $this->datos();
        $conUnaZonaQueNoEsPoligono['features'][] = [
            'type' => 'Feature',
            'properties' => ['nombre' => 'Zona de prueba que es un punto', 'activa' => true],
            'geometry' => ['type' => 'Point', 'coordinates' => [-129.5, 5.5]],
        ];

        $sinEstado = $this->datos();
        unset($sinEstado['plaza']['activa']);

        // Con el mismo nombre, la segunda zona sobrescribiría a la primera.
        $conDosZonasIguales = $this->datos();
        $conDosZonasIguales['features'][] = $conDosZonasIguales['features'][0];

        // PostGIS guarda sin quejarse un anillo que no cierra o que se cruza; ST_IsValid sí los detecta.
        $conUnPoligonoAbierto = $this->datos();
        $conUnPoligonoAbierto['features'][0]['geometry']['coordinates'] = [[
            [-130.0, 5.0], [-129.0, 5.0], [-129.0, 6.0], [-130.0, 6.0],
        ]];
        $conUnPoligonoQueSeCruza = $this->datos();
        $conUnPoligonoQueSeCruza['features'][0]['geometry']['coordinates'] = [[
            [-130.0, 5.0], [-129.0, 6.0], [-129.0, 5.0], [-130.0, 6.0], [-130.0, 5.0],
        ]];

        // Cada caso, con su archivo y lo que el comando dice del error.
        $casos = [
            'zona horaria que no existe' => [$this->archivoCon($sinZonaHorariaValida), 'base de husos IANA'],
            'zona que no es un polígono' => [$this->archivoCon($conUnaZonaQueNoEsPoligono), 'Polygon o un MultiPolygon'],
            'plaza sin estado' => [$this->archivoCon($sinEstado), 'La plaza necesita su estado'],
            'dos zonas con el mismo nombre' => [$this->archivoCon($conDosZonasIguales), 'no pueden llamarse igual'],
            'polígono que no cierra' => [$this->archivoCon($conUnPoligonoAbierto), 'closed linestring'],
            'polígono que se cruza' => [$this->archivoCon($conUnPoligonoQueSeCruza), 'Self-intersection'],
            'texto que no es JSON' => [$this->archivoConTexto('{"type": "FeatureCollection",'), 'no es JSON'],
            'archivo que no existe' => [__DIR__.'/Datos/no-existe.geojson', 'No existe el archivo'],
        ];

        foreach ($casos as $caso => [$archivo, $mensaje]) {
            $this->artisan('plazas:cargar', ['archivo' => $archivo])
                ->expectsOutputToContain($mensaje)
                ->assertFailed();

            $this->assertSame(0, Plaza::count(), $caso);
            $this->assertSame(0, Zona::count(), $caso);
        }
    }

    #[TestDox('plazas-E08: el sembrado carga las plazas de prueba, ficticias y activas, sin duplicarlas')]
    public function test_plazas_e08_el_sembrado_carga_las_plazas_de_prueba_ficticias_y_activas_sin_duplicarlas(): void
    {
        $this->seed(PlazasDePruebaSeeder::class);
        $this->seed(PlazasDePruebaSeeder::class);

        // Las dos del océano y la de Durango, en tierra (plazas-E16).
        $this->assertSame(self::PLAZAS_DE_PRUEBA, Plaza::orderBy('nombre')->pluck('nombre')->all());
        $this->assertSame(3, Plaza::where('activa', true)->count());
        $this->assertGreaterThan(0, Zona::count());

        foreach (Zona::with('plaza')->get() as $zona) {
            $this->assertStringContainsString('de prueba', $zona->nombre);
            // Cada zona de prueba da servicio: su plaza resuelve un punto de su interior.
            $interior = DB::selectOne(
                'select ST_Y(p) as latitud, ST_X(p) as longitud
                   from (select ST_PointOnSurface(poligono::geometry) as p from zonas where id_zonas = ?) as punto',
                [$zona->getKey()],
            );
            $this->assertTrue($zona->plaza->is($this->plazaDelPunto($interior->latitud, $interior->longitud)));
        }
    }

    #[TestDox('plazas-E08: el sembrado general trae las plazas de prueba fuera de producción')]
    public function test_plazas_e08_el_sembrado_general_trae_las_plazas_de_prueba_fuera_de_produccion(): void
    {
        $this->seed();

        $this->assertSame(self::PLAZAS_DE_PRUEBA, Plaza::orderBy('nombre')->pluck('nombre')->all());
    }

    #[TestDox('plazas-E08: en producción no se siembra ninguna plaza de prueba')]
    public function test_plazas_e08_en_produccion_no_se_siembra_ninguna_plaza_de_prueba(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        $this->assertTrue($this->app->isProduction());

        // Aunque alguien fuerce el sembrado en producción, el general y el de las plazas de prueba.
        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();
        $this->artisan('db:seed', ['--class' => PlazasDePruebaSeeder::class, '--force' => true])->assertSuccessful();

        $this->assertSame(0, Plaza::count());
        $this->assertSame(0, Zona::count());
    }

    private function plazaDelPunto(float $latitud, float $longitud): ?Plaza
    {
        return app(ResolucionDePunto::class)->resolver($latitud, $longitud)?->plaza;
    }

    /**
     * @return list<string>
     */
    private function migraciones(): array
    {
        return DB::table('migrations')->orderBy('id')->pluck('migration')->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function datos(): array
    {
        return json_decode((string) file_get_contents(self::ARCHIVO), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function archivoCon(array $datos): string
    {
        return $this->archivoConTexto(json_encode($datos, JSON_THROW_ON_ERROR));
    }

    private function archivoConTexto(string $texto): string
    {
        $archivo = (string) tempnam(sys_get_temp_dir(), 'plaza');
        file_put_contents($archivo, $texto);
        $this->temporales[] = $archivo;

        return $archivo;
    }
}
