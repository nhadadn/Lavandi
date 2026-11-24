<?php

namespace Tests\Feature\Direcciones;

use App\Esquema\RevisorDeEsquema;
use App\Esquema\RevisorDeModelos;
use App\Esquema\Violacion;
use App\Models\Colonia;
use App\Models\Direccion;
use App\Models\EntidadFederativa;
use App\Models\Municipio;
use App\Models\Usuario;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * El catálogo del territorio para la dirección asistida: entidades_federativas, municipios y colonias, y el cambio en
 * direcciones, que deja el municipio de texto y apunta al del catálogo con id_municipios (openspec/changes/direcciones/
 * design.md, "La dirección asistida: el catálogo y el cambio en direcciones"; decisión 7; C-238; ADR-0016).
 *
 * Los tres catálogos no llevan plaza: el territorio no es de una plaza (config/modelo.php, sin_plaza). Las filas se
 * insertan con el constructor de consultas cuando la prueba pide que la base las rechace aunque el código no las haya
 * validado. Los códigos y los nombres son de prueba.
 *
 * Escenarios: modelo-E01 a modelo-E17 sobre las tablas nuevas y el cambio en direcciones · Spec:
 * openspec/changes/direcciones/specs/direcciones/spec.md · Tarea 4.1
 */
class MigracionDelCatalogoTest extends TestCase
{
    use RefreshDatabase;

    private const CATALOGOS = ['entidades_federativas', 'municipios', 'colonias'];

    /** SQLSTATE de PostgreSQL cuando una columna no nula queda vacía. */
    private const VALOR_VACIO = '23502';

    /** SQLSTATE de PostgreSQL cuando una llave foránea no encuentra su fila padre, o su padre tiene hijas. */
    private const LLAVE_FORANEA_ROTA = '23503';

    /** SQLSTATE de PostgreSQL cuando se repite un valor único. */
    private const UNICO_REPETIDO = '23505';

    /** SQLSTATE de PostgreSQL cuando una fila no cumple un CHECK. */
    private const CHECK_INCUMPLIDO = '23514';

    #[TestDox('modelo-E01 a modelo-E17: los tres catálogos y direcciones existen y cumplen la prueba de esquema')]
    public function test_modelo_e01_a_e17_los_tres_catalogos_y_direcciones_cumplen_la_prueba_de_esquema(): void
    {
        $tablas = [...self::CATALOGOS, 'direcciones'];

        foreach ($tablas as $tabla) {
            $this->assertTrue(Schema::hasTable($tabla), "Falta la tabla {$tabla}.");
        }

        $violaciones = array_filter(
            (new RevisorDeEsquema)->revisar(),
            fn (Violacion $violacion): bool => in_array($violacion->tabla, $tablas, true),
        );

        $this->assertSame([], array_values(array_map('strval', $violaciones)));
    }

    #[TestDox('modelo-E05: los tres catálogos están en la lista sin_plaza y no llevan plaza')]
    public function test_modelo_e05_los_tres_catalogos_estan_en_la_lista_sin_plaza(): void
    {
        foreach (self::CATALOGOS as $tabla) {
            $this->assertContains($tabla, config('modelo.sin_plaza'), $tabla);
            $this->assertFalse(Schema::hasColumn($tabla, 'id_plazas'), $tabla);
        }
    }

    #[TestDox('modelo-E01 a modelo-E17: las columnas de los catálogos son las del diseño, en su orden')]
    public function test_modelo_e01_a_e17_las_columnas_de_los_catalogos_son_las_del_diseno(): void
    {
        $this->assertSame(
            ['id_entidades_federativas', 'codigo', 'nombre', 'created_at', 'updated_at'],
            Schema::getColumnListing('entidades_federativas'),
        );
        $this->assertSame(
            ['id_municipios', 'id_entidades_federativas', 'codigo', 'nombre', 'created_at', 'updated_at'],
            Schema::getColumnListing('municipios'),
        );
        $this->assertSame(
            ['id_colonias', 'id_municipios', 'codigo_postal', 'nombre', 'tipo', 'created_at', 'updated_at'],
            Schema::getColumnListing('colonias'),
        );

        // Todo dato del catálogo es obligatorio; created_at y updated_at siguen la regla de Laravel, como en las demás
        // tablas.
        foreach (self::CATALOGOS as $tabla) {
            $columnas = array_column(Schema::getColumns($tabla), 'nullable', 'name');
            unset($columnas['created_at'], $columnas['updated_at']);

            foreach ($columnas as $columna => $admiteVacio) {
                $this->assertFalse($admiteVacio, "{$tabla}.{$columna} no admite vacío");
            }
        }
    }

    #[TestDox('direcciones-E17: direcciones deja la columna municipio y apunta al catálogo con id_municipios, opcional, con su llave y su índice')]
    public function test_direcciones_e17_direcciones_apunta_al_municipio_del_catalogo(): void
    {
        $this->assertFalse(Schema::hasColumn('direcciones', 'municipio'), 'La columna municipio sale de direcciones.');
        $this->assertTrue(Schema::hasColumn('direcciones', 'id_municipios'));
        $this->assertTrue(array_column(Schema::getColumns('direcciones'), 'nullable', 'name')['id_municipios']);
        $this->assertContains([['id_municipios'], 'municipios', ['id_municipios'], 'restrict'], $this->llaves('direcciones'));
        $this->assertContains(['id_municipios'], $this->indices('direcciones'));

        // La colonia y el código postal siguen siendo texto en la dirección (decisión 8).
        $this->assertFalse(array_column(Schema::getColumns('direcciones'), 'nullable', 'name')['colonia']);
        $this->assertTrue(array_column(Schema::getColumns('direcciones'), 'nullable', 'name')['codigo_postal']);
    }

    #[TestDox('modelo-E06 y modelo-E09: los únicos son los códigos, las llaves van con su índice y ON DELETE RESTRICT, y el código postal tiene índice')]
    public function test_modelo_e06_y_e09_unicos_llaves_e_indices_de_los_catalogos(): void
    {
        $this->assertSame([['codigo']], $this->unicos('entidades_federativas'));
        $this->assertSame([['codigo']], $this->unicos('municipios'));
        // Sin único por nombre: dos asentamientos pueden llamarse igual en el mismo código postal.
        $this->assertSame([], $this->unicos('colonias'));

        $this->assertSame([], $this->llaves('entidades_federativas'));
        $this->assertSame(
            [[['id_entidades_federativas'], 'entidades_federativas', ['id_entidades_federativas'], 'restrict']],
            $this->llaves('municipios'),
        );
        $this->assertSame([[['id_municipios'], 'municipios', ['id_municipios'], 'restrict']], $this->llaves('colonias'));

        $this->assertContains(['id_entidades_federativas'], $this->indices('municipios'));
        $this->assertContains(['id_municipios'], $this->indices('colonias'));
        // Así se buscan las colonias (direcciones-E19).
        $this->assertContains(['codigo_postal'], $this->indices('colonias'));
    }

    #[TestDox('modelo-E06: la base rechaza dos estados o dos municipios con el mismo código')]
    public function test_modelo_e06_la_base_rechaza_dos_estados_o_dos_municipios_con_el_mismo_codigo(): void
    {
        $estado = $this->insertarEstado('99');
        $this->insertarMunicipio($estado, '99001');

        $this->assertRechazada(fn () => $this->insertarEstado('99'), self::UNICO_REPETIDO);
        $this->assertRechazada(fn () => $this->insertarMunicipio($estado, '99001'), self::UNICO_REPETIDO);

        $this->assertSame([1, 1], [DB::table('entidades_federativas')->count(), DB::table('municipios')->count()]);
    }

    #[TestDox('modelo-E07: la base rechaza un código de estado que no son dos números, uno de municipio que no son cinco y un código postal que no son cinco')]
    public function test_modelo_e07_la_base_rechaza_codigos_con_otra_forma(): void
    {
        foreach (['9', '999', 'AB', ' 9'] as $codigo) {
            $this->assertRechazada(fn () => $this->insertarEstado($codigo), self::CHECK_INCUMPLIDO);
        }

        $estado = $this->insertarEstado('99');

        foreach (['9900', '990011', '99OO1'] as $codigo) {
            $this->assertRechazada(fn () => $this->insertarMunicipio($estado, $codigo), self::CHECK_INCUMPLIDO);
        }

        $municipio = $this->insertarMunicipio($estado, '99001');

        foreach (['0000', '000000', 'ABCDE'] as $codigoPostal) {
            $this->assertRechazada(
                fn () => $this->insertarColonia($municipio, ['codigo_postal' => $codigoPostal]),
                self::CHECK_INCUMPLIDO,
            );
        }

        $this->assertRechazada(fn () => $this->insertarColonia($municipio, ['tipo' => null]), self::VALOR_VACIO);
        $this->assertRechazada(fn () => $this->insertarColonia($municipio, ['nombre' => null]), self::VALOR_VACIO);

        $this->insertarColonia($municipio, ['codigo_postal' => '00000']);
        $this->assertSame(1, DB::table('colonias')->count());
    }

    #[TestDox('modelo-E09: la base no borra un estado con municipios, ni un municipio con colonias o con direcciones')]
    public function test_modelo_e09_la_base_no_borra_un_estado_ni_un_municipio_en_uso(): void
    {
        $estado = $this->insertarEstado('99');
        $conColonias = $this->insertarMunicipio($estado, '99001');
        $conDireccion = $this->insertarMunicipio($estado, '99002');
        $this->insertarColonia($conColonias);
        $this->insertarDireccion(['id_municipios' => $conDireccion]);

        $this->assertRechazada(
            fn () => DB::table('entidades_federativas')->where('id_entidades_federativas', $estado)->delete(),
            self::LLAVE_FORANEA_ROTA,
        );

        foreach ([$conColonias, $conDireccion] as $municipio) {
            $this->assertRechazada(
                fn () => DB::table('municipios')->where('id_municipios', $municipio)->delete(),
                self::LLAVE_FORANEA_ROTA,
            );
        }

        // Una dirección no apunta a un municipio que no existe.
        $this->assertRechazada(fn () => $this->insertarDireccion(['id_municipios' => 999999]), self::LLAVE_FORANEA_ROTA);

        $this->assertSame(2, DB::table('municipios')->count());
    }

    #[TestDox('modelo-E16: EntidadFederativa, Municipio y Colonia declaran su tabla y su llave igual que la base')]
    public function test_modelo_e16_los_tres_modelos_declaran_su_tabla_y_su_llave(): void
    {
        $this->assertSame(
            [
                ['entidades_federativas', 'id_entidades_federativas'],
                ['municipios', 'id_municipios'],
                ['colonias', 'id_colonias'],
            ],
            array_map(
                fn (string $clase): array => [(new $clase)->getTable(), (new $clase)->getKeyName()],
                [EntidadFederativa::class, Municipio::class, Colonia::class],
            ),
        );

        $this->assertSame(
            [],
            array_map('strval', (new RevisorDeModelos)->revisar([EntidadFederativa::class, Municipio::class, Colonia::class])),
        );
    }

    #[TestDox('modelo-E16: el municipio llega a su estado y a sus colonias, y la dirección a su municipio, por sus llaves')]
    public function test_modelo_e16_el_municipio_llega_a_su_estado_y_a_sus_colonias_y_la_direccion_a_su_municipio(): void
    {
        $estado = EntidadFederativa::create(['codigo' => '99', 'nombre' => 'Estado de prueba']);
        $municipio = $estado->municipios()->create(['codigo' => '99001', 'nombre' => 'Municipio de prueba']);
        $municipio->colonias()->create(['codigo_postal' => '00000', 'nombre' => 'Colonia de prueba', 'tipo' => 'Colonia']);

        $direccion = Direccion::create([
            'id_usuarios' => Usuario::factory()->create()->getKey(),
            'id_municipios' => $municipio->getKey(),
            'colonia' => 'Colonia de prueba',
            'ubicacion' => ['latitud' => 10.5, 'longitud' => -119.5],
        ]);

        $this->assertTrue($estado->is($municipio->fresh()->entidadFederativa));
        $this->assertSame(['Colonia de prueba'], $municipio->colonias()->pluck('nombre')->all());
        $this->assertTrue($municipio->is(Colonia::sole()->municipio));
        $this->assertTrue($municipio->is($direccion->fresh()->municipio));
        $this->assertSame('id_entidades_federativas', $municipio->entidadFederativa()->getForeignKeyName());
        $this->assertSame('id_municipios', $direccion->municipio()->getForeignKeyName());
    }

    private function insertarEstado(string $codigo): int
    {
        return DB::table('entidades_federativas')->insertGetId([
            'codigo' => $codigo,
            'nombre' => 'Estado de prueba',
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id_entidades_federativas');
    }

    private function insertarMunicipio(int $estado, string $codigo): int
    {
        return DB::table('municipios')->insertGetId([
            'id_entidades_federativas' => $estado,
            'codigo' => $codigo,
            'nombre' => 'Municipio de prueba',
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id_municipios');
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function insertarColonia(int $municipio, array $datos = []): int
    {
        return DB::table('colonias')->insertGetId([
            'id_municipios' => $municipio,
            'codigo_postal' => '00000',
            'nombre' => 'Colonia de prueba',
            'tipo' => 'Colonia',
            'created_at' => now(),
            'updated_at' => now(),
            ...$datos,
        ], 'id_colonias');
    }

    /**
     * Una dirección de una cuenta nueva, sin plaza, con las columnas que se pasan.
     *
     * @param  array<string, mixed>  $datos
     */
    private function insertarDireccion(array $datos = []): int
    {
        $usuario = DB::table('usuarios')->insertGetId([
            'id_publico' => (string) Str::ulid(),
            'correo' => Str::lower((string) Str::ulid()).'@ejemplo.com',
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id_usuarios');

        return DB::table('direcciones')->insertGetId([
            'id_publico' => (string) Str::ulid(),
            'id_usuarios' => $usuario,
            'colonia' => 'Colonia de prueba',
            'ubicacion' => DB::raw("ST_GeogFromText('SRID=4326;POINT(-119.5 10.5)')"),
            'created_at' => now(),
            'updated_at' => now(),
            ...$datos,
        ], 'id_direcciones');
    }

    /**
     * Las llaves foráneas de la tabla: columnas, tabla padre, columnas padre y qué hacen al borrar.
     *
     * @return list<array{0: list<string>, 1: string, 2: list<string>, 3: string}>
     */
    private function llaves(string $tabla): array
    {
        return array_map(
            fn (array $llave): array => [
                $llave['columns'],
                $llave['foreign_table'],
                $llave['foreign_columns'],
                strtolower($llave['on_delete']),
            ],
            Schema::getForeignKeys($tabla),
        );
    }

    /**
     * Las columnas de cada índice de la tabla que no es único ni la llave primaria.
     *
     * @return list<list<string>>
     */
    private function indices(string $tabla): array
    {
        return array_values(array_map(
            fn (array $indice): array => $indice['columns'],
            array_filter(Schema::getIndexes($tabla), fn (array $indice): bool => ! $indice['unique']),
        ));
    }

    /**
     * Las columnas de cada índice único de la tabla, sin la llave primaria.
     *
     * @return list<list<string>>
     */
    private function unicos(string $tabla): array
    {
        return array_values(array_map(
            fn (array $indice): array => $indice['columns'],
            array_filter(Schema::getIndexes($tabla), fn (array $indice): bool => $indice['unique'] && ! $indice['primary']),
        ));
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
