<?php

namespace Tests\Feature\Direcciones;

use App\Esquema\RevisorDeEsquema;
use App\Esquema\RevisorDeModelos;
use App\Esquema\Violacion;
use App\Models\Direccion;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Direcciones\Soporte\CatalogoDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * La tabla direcciones y su modelo de Eloquent (openspec/changes/direcciones/design.md, "Tablas y migración";
 * vault/arquitectura/modelo-de-datos.md; ADR-0016).
 *
 * Es una de las cuatro tablas con la plaza opcional: queda vacía si el punto no cae en ninguna zona. Lleva el único
 * (id_direcciones, id_plazas) para que la llave compuesta de pedido_direcciones, que crea resumen-y-confirmacion, haga
 * que la base rechace una dirección de otra plaza.
 *
 * Las filas se insertan con el constructor de consultas, sin pasar por el modelo, cuando la prueba pide que la base
 * rechace la fila aunque el código no la haya validado.
 *
 * Escenarios: modelo-E01 a modelo-E17 sobre la tabla nueva, y la base de direcciones-E02 y direcciones-E12 · Spec:
 * openspec/changes/direcciones/specs/direcciones/spec.md
 */
class MigracionTest extends TestCase
{
    use PlazasDePrueba;
    use RefreshDatabase;

    /** SQLSTATE de PostgreSQL cuando una columna no nula queda vacía. */
    private const VALOR_VACIO = '23502';

    /** SQLSTATE de PostgreSQL cuando una llave foránea no encuentra su fila padre, o su padre tiene hijas. */
    private const LLAVE_FORANEA_ROTA = '23503';

    /** SQLSTATE de PostgreSQL cuando se repite un valor único. */
    private const UNICO_REPETIDO = '23505';

    #[TestDox('modelo-E01 a modelo-E17: la tabla direcciones existe y cumple la prueba de esquema')]
    public function test_modelo_e01_a_e17_la_tabla_direcciones_existe_y_cumple_la_prueba_de_esquema(): void
    {
        $this->assertTrue(Schema::hasTable('direcciones'), 'Falta la tabla direcciones.');

        $violaciones = array_filter(
            (new RevisorDeEsquema)->revisar(),
            fn (Violacion $violacion): bool => $violacion->tabla === 'direcciones',
        );

        $this->assertSame([], array_values(array_map('strval', $violaciones)));
    }

    #[TestDox('modelo-E01 a modelo-E17: las columnas son las del modelo de datos, en su orden')]
    public function test_modelo_e01_a_e17_las_columnas_son_las_del_modelo_de_datos_en_su_orden(): void
    {
        // id_municipios va al final: la agregó la segunda migración del cambio, que también quitó municipio (tarea 4.1).
        $this->assertSame(
            [
                'id_direcciones', 'id_publico', 'id_usuarios', 'id_plazas', 'alias', 'calle', 'numero_exterior',
                'numero_interior', 'colonia', 'codigo_postal', 'referencias', 'ubicacion', 'es_favorita',
                'created_at', 'updated_at', 'deleted_at', 'id_municipios',
            ],
            Schema::getColumnListing('direcciones'),
        );
    }

    #[TestDox('modelo-E07, modelo-E08 y modelo-E17: el punto, el identificador público, la favorita y los instantes llevan su tipo')]
    public function test_modelo_e07_e08_y_e17_las_columnas_llevan_su_tipo(): void
    {
        $this->assertSame('geography(Point,4326)', $this->tipo('ubicacion'));
        $this->assertSame('character(26)', $this->tipo('id_publico'));
        $this->assertSame('boolean', $this->tipo('es_favorita'));
        $this->assertSame('text', $this->tipo('referencias'));

        foreach (['created_at', 'updated_at', 'deleted_at'] as $columna) {
            $this->assertSame('timestamp(0) with time zone', $this->tipo($columna), $columna);
        }
    }

    #[TestDox('modelo-E05: la plaza es opcional, con su llave a plazas, y la cuenta es obligatoria, con su llave a usuarios')]
    public function test_modelo_e05_la_plaza_es_opcional_con_su_llave_a_plazas_y_la_cuenta_es_obligatoria(): void
    {
        $this->assertTrue($this->admiteVacio('id_plazas'));
        $this->assertContains('direcciones', config('modelo.plaza_opcional'));
        $this->assertContains([['id_plazas'], 'plazas', ['id_plazas'], 'restrict'], $this->llaves());

        $this->assertFalse($this->admiteVacio('id_usuarios'));
        $this->assertContains([['id_usuarios'], 'usuarios', ['id_usuarios'], 'restrict'], $this->llaves());

        // Y la del municipio del catálogo, opcional (tarea 4.1). Solo esas tres: la dirección no apunta a nada más.
        $this->assertTrue($this->admiteVacio('id_municipios'));
        $this->assertContains([['id_municipios'], 'municipios', ['id_municipios'], 'restrict'], $this->llaves());
        $this->assertCount(3, $this->llaves());
    }

    #[TestDox('modelo-E06 y direcciones-E12: los únicos son id_publico y (id_direcciones, id_plazas), el destino de la llave compuesta del pedido')]
    public function test_modelo_e06_y_direcciones_e12_los_unicos_son_id_publico_y_la_pareja_con_la_plaza(): void
    {
        $this->assertEqualsCanonicalizing(
            [['id_publico'], ['id_direcciones', 'id_plazas']],
            $this->unicos(),
        );
    }

    #[TestDox('direcciones-E12: una llave compuesta hacia (id_direcciones, id_plazas) hace que la base rechace la dirección con otra plaza')]
    public function test_direcciones_e12_una_llave_compuesta_hace_que_la_base_rechace_la_direccion_con_otra_plaza(): void
    {
        $plazaA = $this->insertarPlaza('Plaza de prueba A');
        $plazaB = $this->insertarPlaza('Plaza de prueba B');
        $direccion = $this->insertarDireccion(['id_plazas' => $plazaB]);

        // Una tabla como pedido_direcciones, que crea resumen-y-confirmacion, en un esquema propio. Nace dentro de la
        // transacción de la prueba y se deshace con ella (una tabla temporal no puede apuntar a una permanente).
        DB::unprepared(
            'create schema prueba_direcciones;
             create table prueba_direcciones.pedido_direcciones (
                 id_direcciones bigint not null,
                 id_plazas bigint not null,
                 foreign key (id_direcciones, id_plazas) references public.direcciones (id_direcciones, id_plazas)
             );',
        );

        $this->assertRechazada(
            fn () => DB::table('prueba_direcciones.pedido_direcciones')->insert([
                'id_direcciones' => $direccion,
                'id_plazas' => $plazaA,
            ]),
            self::LLAVE_FORANEA_ROTA,
        );

        // Con su misma plaza, entra.
        DB::table('prueba_direcciones.pedido_direcciones')->insert([
            'id_direcciones' => $direccion,
            'id_plazas' => $plazaB,
        ]);
        $this->assertSame(1, DB::table('prueba_direcciones.pedido_direcciones')->count());
    }

    #[TestDox('modelo-E07: la ubicación tiene índice GiST')]
    public function test_modelo_e07_la_ubicacion_tiene_indice_gist(): void
    {
        $indices = array_filter(
            Schema::getIndexes('direcciones'),
            fn (array $indice): bool => $indice['columns'] === ['ubicacion'],
        );

        $this->assertSame(['gist'], array_values(array_map(fn (array $indice): string => $indice['type'], $indices)));
    }

    #[TestDox('direcciones-E02: la base rechaza una dirección sin colonia, aunque el código no la haya validado')]
    public function test_direcciones_e02_la_base_rechaza_una_direccion_sin_colonia(): void
    {
        $this->assertFalse($this->admiteVacio('colonia'));

        $this->assertRechazada(fn () => $this->insertarDireccion(['colonia' => null]), self::VALOR_VACIO);

        $this->assertSame(0, DB::table('direcciones')->count());
    }

    #[TestDox('modelo-E07: la base rechaza una dirección sin punto o sin cuenta; las señas que no son la colonia son opcionales')]
    public function test_modelo_e07_la_base_rechaza_una_direccion_sin_punto_o_sin_cuenta(): void
    {
        $this->assertRechazada(fn () => $this->insertarDireccion(['ubicacion' => null]), self::VALOR_VACIO);
        $this->assertRechazada(fn () => $this->insertarDireccion(['id_usuarios' => null]), self::VALOR_VACIO);

        // Una marcada en el mapa puede no tener calle, número, código postal, municipio ni referencias (direcciones-E04).
        $this->insertarDireccion([
            'alias' => null,
            'calle' => null,
            'numero_exterior' => null,
            'numero_interior' => null,
            'codigo_postal' => null,
            'id_municipios' => null,
            'referencias' => null,
        ]);

        $fila = DB::table('direcciones')->sole();
        $this->assertFalse($fila->es_favorita);
        $this->assertNull($fila->id_plazas);
    }

    #[TestDox('modelo-E07: la base rechaza dos direcciones con el mismo identificador público')]
    public function test_modelo_e07_la_base_rechaza_dos_direcciones_con_el_mismo_identificador_publico(): void
    {
        $ulid = (string) Str::ulid();
        $this->insertarDireccion(['id_publico' => $ulid]);

        $this->assertRechazada(fn () => $this->insertarDireccion(['id_publico' => $ulid]), self::UNICO_REPETIDO);

        $this->assertSame(1, DB::table('direcciones')->count());
    }

    #[TestDox('modelo-E09: la base no borra una cuenta ni una plaza que tienen direcciones')]
    public function test_modelo_e09_la_base_no_borra_una_cuenta_ni_una_plaza_que_tienen_direcciones(): void
    {
        $plaza = $this->insertarPlaza('Plaza de prueba 1');
        $usuario = $this->insertarUsuario();
        $this->insertarDireccion(['id_usuarios' => $usuario, 'id_plazas' => $plaza]);

        $this->assertRechazada(
            fn () => DB::table('usuarios')->where('id_usuarios', $usuario)->delete(),
            self::LLAVE_FORANEA_ROTA,
        );
        $this->assertRechazada(
            fn () => DB::table('plazas')->where('id_plazas', $plaza)->delete(),
            self::LLAVE_FORANEA_ROTA,
        );

        $this->assertSame(1, DB::table('direcciones')->count());
    }

    #[TestDox('modelo-E11 y modelo-E12: direcciones no lleva el trigger del historial')]
    public function test_modelo_e11_y_e12_direcciones_no_lleva_el_trigger_del_historial(): void
    {
        $this->assertTrue(Schema::hasTable('direcciones'), 'Falta la tabla direcciones.');

        $triggers = DB::scalar(
            "select count(*) from pg_catalog.pg_trigger where tgrelid = to_regclass('direcciones') and not tgisinternal",
        );

        $this->assertSame(0, $triggers);
    }

    #[TestDox('modelo-E16: Direccion declara direcciones e id_direcciones, igual que la base')]
    public function test_modelo_e16_direccion_declara_su_tabla_y_su_llave_igual_que_la_base(): void
    {
        $this->assertSame(['direcciones', 'id_direcciones'], [(new Direccion)->getTable(), (new Direccion)->getKeyName()]);

        $this->assertSame([], array_map('strval', (new RevisorDeModelos)->revisar([Direccion::class])));
    }

    #[TestDox('modelo-E15: la dirección recibe su identificador público ULID, se borra en suave y su punto no sale en JSON')]
    public function test_modelo_e15_la_direccion_recibe_su_identificador_publico_y_se_borra_en_suave(): void
    {
        $direccion = Direccion::create([
            'id_usuarios' => Usuario::factory()->create()->getKey(),
            'colonia' => 'Colonia de prueba',
            'ubicacion' => ['latitud' => 10.5, 'longitud' => -119.5],
        ]);

        $this->assertTrue(Str::isUlid($direccion->id_publico));
        $this->assertSame('id_publico', $direccion->getRouteKeyName());
        $this->assertContains(SoftDeletes::class, class_uses_recursive(Direccion::class));
        $this->assertArrayNotHasKey('ubicacion', $direccion->toArray());
        $this->assertFalse($direccion->fresh()->es_favorita);

        $direccion->delete();

        $this->assertNull(Direccion::find($direccion->getKey()));
        $this->assertNotNull(
            DB::table('direcciones')->where('id_direcciones', $direccion->getKey())->value('deleted_at'),
        );
    }

    #[TestDox('modelo-E16: la dirección guarda su punto y llega a su cuenta y a su plaza por sus llaves')]
    public function test_modelo_e16_la_direccion_guarda_su_punto_y_llega_a_su_cuenta_y_a_su_plaza(): void
    {
        $plaza = $this->plazaDePrueba();
        $usuario = Usuario::factory()->create();

        $direccion = Direccion::create([
            'id_usuarios' => $usuario->getKey(),
            'id_plazas' => $plaza->getKey(),
            'alias' => 'Casa',
            'es_favorita' => true,
            'calle' => 'Calle de prueba',
            'numero_exterior' => '1',
            'colonia' => 'Colonia de prueba',
            'id_municipios' => CatalogoDePrueba::municipio()->getKey(),
            'ubicacion' => ['latitud' => 10.25, 'longitud' => -119.75],
        ]);

        $this->assertSame(
            ['latitud' => 10.25, 'longitud' => -119.75],
            array_map('floatval', (array) DB::selectOne(
                'select ST_Y(ubicacion::geometry) as latitud, ST_X(ubicacion::geometry) as longitud
                   from direcciones where id_direcciones = ?',
                [$direccion->getKey()],
            )),
        );

        $this->assertTrue($usuario->is($direccion->usuario));
        $this->assertTrue($plaza->is($direccion->plaza));
        $this->assertTrue(CatalogoDePrueba::municipio()->is($direccion->municipio));
        $this->assertSame('id_usuarios', $direccion->usuario()->getForeignKeyName());
        $this->assertSame('id_plazas', $direccion->plaza()->getForeignKeyName());
        $this->assertTrue($direccion->fresh()->es_favorita);
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

    private function insertarUsuario(): int
    {
        return DB::table('usuarios')->insertGetId([
            'id_publico' => (string) Str::ulid(),
            'correo' => Str::lower((string) Str::ulid()).'@ejemplo.com',
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id_usuarios');
    }

    /**
     * Una dirección de una cuenta nueva, sin plaza, con las columnas que se pasan.
     *
     * @param  array<string, mixed>  $datos
     */
    private function insertarDireccion(array $datos = []): int
    {
        return DB::table('direcciones')->insertGetId([
            'id_publico' => (string) Str::ulid(),
            'id_usuarios' => array_key_exists('id_usuarios', $datos) ? $datos['id_usuarios'] : $this->insertarUsuario(),
            'calle' => 'Calle de prueba',
            'numero_exterior' => '1',
            'colonia' => 'Colonia de prueba',
            'ubicacion' => DB::raw("ST_GeogFromText('SRID=4326;POINT(-119.5 10.5)')"),
            'created_at' => now(),
            'updated_at' => now(),
            ...$datos,
        ], 'id_direcciones');
    }

    /**
     * El tipo de la columna como lo escribe la base: geography(Point,4326), character(26), timestamp(0) with time zone.
     */
    private function tipo(string $columna): ?string
    {
        return DB::scalar(
            "select format_type(a.atttypid, a.atttypmod)
               from pg_catalog.pg_attribute a
              where a.attrelid = to_regclass('direcciones') and a.attname = ? and not a.attisdropped",
            [$columna],
        );
    }

    private function admiteVacio(string $columna): bool
    {
        $columnas = array_column(Schema::getColumns('direcciones'), 'nullable', 'name');

        return $columnas[$columna];
    }

    /**
     * Las llaves foráneas de direcciones: columnas, tabla padre, columnas padre y qué hacen al borrar.
     *
     * @return list<array{0: list<string>, 1: string, 2: list<string>, 3: string}>
     */
    private function llaves(): array
    {
        return array_map(
            fn (array $llave): array => [
                $llave['columns'],
                $llave['foreign_table'],
                $llave['foreign_columns'],
                strtolower($llave['on_delete']),
            ],
            Schema::getForeignKeys('direcciones'),
        );
    }

    /**
     * Las columnas de cada índice único de direcciones, sin la llave primaria.
     *
     * @return list<list<string>>
     */
    private function unicos(): array
    {
        return array_values(array_map(
            fn (array $indice): array => $indice['columns'],
            array_filter(
                Schema::getIndexes('direcciones'),
                fn (array $indice): bool => $indice['unique'] && ! $indice['primary'],
            ),
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
