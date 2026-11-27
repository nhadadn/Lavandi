<?php

namespace Tests\Feature\Plazas;

use App\Esquema\RevisorDeEsquema;
use App\Esquema\RevisorDeModelos;
use App\Esquema\Violacion;
use App\Models\ParametroPlaza;
use App\Models\Plaza;
use App\Models\Zona;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * Las tres tablas de la plaza, plazas, zonas y parametros_plaza, y sus modelos de Eloquent
 * (openspec/changes/plazas-y-zonas/design.md, "Tablas y migración"; vault/arquitectura/modelo-de-datos.md; ADR-0016).
 *
 * Escenarios: plazas-E03, y modelo-E01 a modelo-E17 sobre las tablas nuevas ·
 * Spec: openspec/changes/plazas-y-zonas/specs/plazas/spec.md
 */
class MigracionTest extends TestCase
{
    use PlazasDePrueba;
    use RefreshDatabase;

    private const TABLAS = ['plazas', 'zonas', 'parametros_plaza'];

    /** SQLSTATE de PostgreSQL cuando una columna no nula queda vacía. */
    private const VALOR_VACIO = '23502';

    /** SQLSTATE de PostgreSQL cuando una llave foránea no encuentra su fila padre, o su padre tiene hijas. */
    private const LLAVE_FORANEA_ROTA = '23503';

    /** SQLSTATE de PostgreSQL cuando se repite un valor único. */
    private const UNICO_REPETIDO = '23505';

    #[TestDox('modelo-E01 a modelo-E17: las tres tablas de la plaza existen y cumplen la prueba de esquema')]
    public function test_modelo_e01_a_e17_las_tres_tablas_de_la_plaza_existen_y_cumplen_la_prueba_de_esquema(): void
    {
        foreach (self::TABLAS as $tabla) {
            $this->assertTrue(Schema::hasTable($tabla), "Falta la tabla {$tabla}.");
        }

        $violaciones = array_filter(
            (new RevisorDeEsquema)->revisar(),
            fn (Violacion $violacion): bool => in_array($violacion->tabla, self::TABLAS, true),
        );

        $this->assertSame([], array_values(array_map('strval', $violaciones)));
    }

    #[TestDox('modelo-E01 a modelo-E17: las columnas son las del modelo de datos, en su orden')]
    public function test_modelo_e01_a_e17_las_columnas_son_las_del_modelo_de_datos_en_su_orden(): void
    {
        $this->assertSame(
            ['id_plazas', 'nombre', 'zona_horaria', 'activa', 'created_at', 'updated_at'],
            Schema::getColumnListing('plazas'),
        );
        $this->assertSame(
            ['id_zonas', 'id_plazas', 'nombre', 'poligono', 'activa', 'created_at', 'updated_at'],
            Schema::getColumnListing('zonas'),
        );
        $this->assertSame(
            [
                'id_parametros_plaza', 'id_plazas', 'clave', 'valor', 'vigente_desde', 'vigente_hasta',
                'created_at', 'updated_at',
            ],
            Schema::getColumnListing('parametros_plaza'),
        );
    }

    #[TestDox('modelo-E07 y modelo-E08: los tipos de las columnas son los del modelo y del diseño')]
    public function test_modelo_e07_y_e08_los_tipos_de_las_columnas_son_los_del_modelo_y_del_diseno(): void
    {
        $this->assertSame('geography(MultiPolygon,4326)', $this->tipo('zonas', 'poligono'));
        $this->assertSame('jsonb', $this->tipo('parametros_plaza', 'valor'));
        $this->assertSame('boolean', $this->tipo('plazas', 'activa'));
        $this->assertSame('boolean', $this->tipo('zonas', 'activa'));

        foreach (['vigente_desde', 'vigente_hasta', 'created_at', 'updated_at'] as $columna) {
            $this->assertSame('timestamp(0) with time zone', $this->tipo('parametros_plaza', $columna), $columna);
        }

        foreach (['plazas', 'zonas'] as $tabla) {
            foreach (['created_at', 'updated_at'] as $columna) {
                $this->assertSame('timestamp(0) with time zone', $this->tipo($tabla, $columna), "{$tabla}.{$columna}");
            }
        }
    }

    #[TestDox('modelo-E05: plazas no lleva plaza; zonas y parametros_plaza la llevan no nula, con llave a plazas')]
    public function test_modelo_e05_plazas_no_lleva_plaza_zonas_y_parametros_plaza_la_llevan_no_nula_con_llave_a_plazas(): void
    {
        // plazas no tiene más id_plazas que su llave primaria.
        $this->assertSame(['id_plazas'], $this->llavePrimaria('plazas'));
        $this->assertSame([], Schema::getForeignKeys('plazas'));

        foreach (['zonas', 'parametros_plaza'] as $tabla) {
            $this->assertFalse($this->admiteVacio($tabla, 'id_plazas'), "{$tabla}.id_plazas es no nula.");

            $llaves = array_map(
                fn (array $llave): array => [
                    $llave['columns'],
                    $llave['foreign_table'],
                    $llave['foreign_columns'],
                    strtolower($llave['on_delete']),
                ],
                Schema::getForeignKeys($tabla),
            );

            $this->assertSame([[['id_plazas'], 'plazas', ['id_plazas'], 'restrict']], $llaves, $tabla);
        }
    }

    #[TestDox('modelo-E07: el polígono de la zona tiene índice GiST')]
    public function test_modelo_e07_el_poligono_de_la_zona_tiene_indice_gist(): void
    {
        $indices = array_filter(
            Schema::getIndexes('zonas'),
            fn (array $indice): bool => $indice['columns'] === ['poligono'],
        );

        $this->assertSame(['gist'], array_values(array_map(fn (array $indice): string => $indice['type'], $indices)));
    }

    #[TestDox('modelo-E07: la base rechaza dos plazas con el mismo nombre')]
    public function test_modelo_e07_la_base_rechaza_dos_plazas_con_el_mismo_nombre(): void
    {
        $this->insertarPlaza('Plaza de prueba 1');

        $this->assertRechazada(fn () => $this->insertarPlaza('Plaza de prueba 1'), self::UNICO_REPETIDO);

        $this->assertSame(1, DB::table('plazas')->count());
    }

    #[TestDox('modelo-E07: la base rechaza dos zonas con el mismo nombre en la misma plaza, y lo acepta en otra plaza')]
    public function test_modelo_e07_la_base_rechaza_dos_zonas_con_el_mismo_nombre_en_la_misma_plaza_y_lo_acepta_en_otra_plaza(): void
    {
        $una = $this->insertarPlaza('Plaza de prueba 1');
        $otra = $this->insertarPlaza('Plaza de prueba 2');

        $this->insertarZona($una, 'Zona de prueba Centro');

        $this->assertRechazada(fn () => $this->insertarZona($una, 'Zona de prueba Centro'), self::UNICO_REPETIDO);

        $this->insertarZona($otra, 'Zona de prueba Centro');
        $this->assertSame(2, DB::table('zonas')->count());
    }

    #[TestDox('modelo-E07: la base rechaza dos parámetros con la misma plaza, clave y vigencia')]
    public function test_modelo_e07_la_base_rechaza_dos_parametros_con_la_misma_plaza_clave_y_vigencia(): void
    {
        $plaza = $this->insertarPlaza('Plaza de prueba 1');

        $this->insertarParametro($plaza, 'clave_de_prueba', '2026-12-01 00:00:00+00');

        $this->assertRechazada(
            fn () => $this->insertarParametro($plaza, 'clave_de_prueba', '2026-12-01 00:00:00+00'),
            self::UNICO_REPETIDO,
        );

        // La misma clave con otra vigencia es otra fila: el historial del parámetro.
        $this->insertarParametro($plaza, 'clave_de_prueba', '2027-01-01 00:00:00+00');
        $this->assertSame(2, DB::table('parametros_plaza')->count());
    }

    #[TestDox('modelo-E09: la base no borra una plaza que tiene zonas')]
    public function test_modelo_e09_la_base_no_borra_una_plaza_que_tiene_zonas(): void
    {
        $plaza = $this->insertarPlaza('Plaza de prueba 1');
        $this->insertarZona($plaza, 'Zona de prueba Centro');

        $this->assertRechazada(
            fn () => DB::table('plazas')->where('id_plazas', $plaza)->delete(),
            self::LLAVE_FORANEA_ROTA,
        );

        $this->assertSame(1, DB::table('plazas')->count());
    }

    #[TestDox('plazas-E03: una zona con su polígono y su plaza se guarda; una zona sin plaza se rechaza')]
    public function test_plazas_e03_una_zona_con_su_poligono_y_su_plaza_se_guarda_una_zona_sin_plaza_se_rechaza(): void
    {
        $plaza = $this->plazaDePrueba('Plaza de prueba 1');
        $poligono = $this->cuadrado(oeste: -120.0, sur: 10.0);

        $zona = Zona::create([
            'id_plazas' => $plaza->getKey(),
            'nombre' => 'Zona de prueba Centro',
            'poligono' => $poligono,
            'activa' => true,
        ]);

        $this->assertTrue($plaza->is($zona->plaza));
        // El polígono quedó como lo dio el GeoJSON, convertido a MultiPolygon.
        $this->assertTrue((bool) DB::scalar(
            'select ST_Equals(poligono::geometry, ST_Multi(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)))
               from zonas where id_zonas = ?',
            [json_encode($poligono), $zona->getKey()],
        ));
        $this->assertSame('ST_MultiPolygon', DB::scalar(
            'select ST_GeometryType(poligono::geometry) from zonas where id_zonas = ?',
            [$zona->getKey()],
        ));

        $this->assertRechazada(fn () => Zona::create([
            'nombre' => 'Zona de prueba sin plaza',
            'poligono' => $this->cuadrado(oeste: -118.0, sur: 10.0),
            'activa' => true,
        ]), self::VALOR_VACIO);

        $this->assertSame(['Zona de prueba Centro'], DB::table('zonas')->pluck('nombre')->all());
    }

    #[TestDox('modelo-E16: Plaza, Zona y ParametroPlaza declaran su tabla y su llave igual que la base')]
    public function test_modelo_e16_plaza_zona_y_parametro_plaza_declaran_su_tabla_y_su_llave_igual_que_la_base(): void
    {
        $this->assertSame(['plazas', 'id_plazas'], [(new Plaza)->getTable(), (new Plaza)->getKeyName()]);
        $this->assertSame(['zonas', 'id_zonas'], [(new Zona)->getTable(), (new Zona)->getKeyName()]);
        $this->assertSame(
            ['parametros_plaza', 'id_parametros_plaza'],
            [(new ParametroPlaza)->getTable(), (new ParametroPlaza)->getKeyName()],
        );

        $this->assertSame(
            [],
            array_map('strval', (new RevisorDeModelos)->revisar([Plaza::class, Zona::class, ParametroPlaza::class])),
        );
    }

    #[TestDox('modelo-E16: la plaza tiene sus zonas y sus parámetros por id_plazas, y cada uno su plaza')]
    public function test_modelo_e16_la_plaza_tiene_sus_zonas_y_sus_parametros_por_id_plazas_y_cada_uno_su_plaza(): void
    {
        $plaza = $this->plazaDePrueba('Plaza de prueba 1');
        $otra = $this->plazaDePrueba('Plaza de prueba 2');

        $zona = $this->zonaDePrueba($plaza, 'Zona de prueba Centro', $this->cuadrado(oeste: -120.0, sur: 10.0));
        $this->zonaDePrueba($otra, 'Zona de prueba Centro', $this->cuadrado(oeste: -118.0, sur: 10.0));

        $parametro = $plaza->parametros()->create([
            'clave' => 'clave_de_prueba',
            'valor' => ['monto' => '10.00'],
            'vigente_desde' => '2026-12-01 00:00:00+00',
        ]);

        $this->assertSame('id_plazas', $plaza->zonas()->getForeignKeyName());
        $this->assertSame('id_plazas', $plaza->parametros()->getForeignKeyName());
        $this->assertSame('id_plazas', $zona->plaza()->getForeignKeyName());
        $this->assertSame('id_plazas', $parametro->plaza()->getForeignKeyName());

        $this->assertSame([$zona->getKey()], $plaza->zonas()->pluck('id_zonas')->all());
        $this->assertTrue($plaza->is($zona->plaza));
        $this->assertTrue($plaza->is($parametro->plaza));
        $this->assertSame(['monto' => '10.00'], $parametro->fresh()->valor);
        $this->assertTrue($parametro->fresh()->vigente_desde->eq('2026-12-01 00:00:00+00'));
    }

    private function insertarPlaza(string $nombre): int
    {
        return DB::table('plazas')->insertGetId([
            'nombre' => $nombre,
            'zona_horaria' => 'America/Mexico_City',
            'activa' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id_plazas');
    }

    private function insertarZona(int $plaza, string $nombre): void
    {
        DB::table('zonas')->insert([
            'id_plazas' => $plaza,
            'nombre' => $nombre,
            'poligono' => DB::raw(
                "ST_GeogFromText('SRID=4326;MULTIPOLYGON(((-120 10, -119 10, -119 11, -120 11, -120 10)))')",
            ),
            'activa' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertarParametro(int $plaza, string $clave, string $vigenteDesde): void
    {
        DB::table('parametros_plaza')->insert([
            'id_plazas' => $plaza,
            'clave' => $clave,
            'valor' => json_encode(['monto' => '10.00']),
            'vigente_desde' => $vigenteDesde,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * El tipo de la columna como lo escribe la base: geography(MultiPolygon,4326), jsonb, timestamp(0) with time zone.
     */
    private function tipo(string $tabla, string $columna): ?string
    {
        return DB::scalar(
            'select format_type(a.atttypid, a.atttypmod)
               from pg_catalog.pg_attribute a
              where a.attrelid = to_regclass(?) and a.attname = ? and not a.attisdropped',
            [$tabla, $columna],
        );
    }

    private function admiteVacio(string $tabla, string $columna): bool
    {
        $columnas = array_column(Schema::getColumns($tabla), 'nullable', 'name');

        return $columnas[$columna];
    }

    /**
     * @return list<string>
     */
    private function llavePrimaria(string $tabla): array
    {
        $llave = collect(Schema::getIndexes($tabla))->firstWhere('primary', true);

        return $llave['columns'] ?? [];
    }

    /**
     * La operación falla con el SQLSTATE dado. Corre en una transacción anidada, un savepoint, para que el error no
     * aborte la transacción de la prueba.
     */
    private function assertRechazada(callable $operacion, string $sqlstate): void
    {
        try {
            DB::transaction($operacion);
            $this->fail("La base debió rechazar la operación con SQLSTATE {$sqlstate}.");
        } catch (QueryException $error) {
            $this->assertSame($sqlstate, $error->getCode(), $error->getMessage());
        }
    }
}
