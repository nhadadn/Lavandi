<?php

namespace Tests\Feature\Avisos;

use App\Esquema\RevisorDeEsquema;
use App\Esquema\RevisorDeModelos;
use App\Esquema\Violacion;
use App\Models\Dispositivo;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * La tabla dispositivos y su modelo de Eloquent, Dispositivo: el token de Firebase Cloud Messaging de cada teléfono,
 * ligado a la cuenta, con su plataforma y su último uso (openspec/changes/avisos/design.md, "Tablas y migración";
 * vault/arquitectura/modelo-de-datos.md; ADR-0016; ADR-0008).
 *
 * Sin plaza: está en la lista sin_plaza de config/modelo.php, porque el teléfono es de la cuenta y la cuenta no
 * pertenece a una plaza. El token es único: un teléfono es de una sola cuenta a la vez (avisos-E21).
 *
 * Las filas se insertan con el constructor de consultas, sin pasar por el modelo, cuando la prueba pide que la base
 * rechace la fila aunque el código no la haya validado.
 *
 * Escenarios: modelo-E01 a modelo-E17 sobre la tabla nueva · Spec: openspec/changes/avisos/specs/avisos/spec.md ·
 * Tarea 0.1 de openspec/changes/avisos/tasks.md
 */
class MigracionTest extends TestCase
{
    use RefreshDatabase;

    /** SQLSTATE de PostgreSQL cuando una columna no nula queda vacía. */
    private const VALOR_VACIO = '23502';

    /** SQLSTATE de PostgreSQL cuando una llave foránea no encuentra su fila padre, o su padre tiene hijas. */
    private const LLAVE_FORANEA_ROTA = '23503';

    /** SQLSTATE de PostgreSQL cuando un valor repite un único. */
    private const VALOR_REPETIDO = '23505';

    /** SQLSTATE de PostgreSQL cuando una fila no cumple un CHECK. */
    private const CHECK_INCUMPLIDO = '23514';

    #[TestDox('modelo-E01 a modelo-E17: la tabla dispositivos existe y cumple la prueba de esquema')]
    public function test_modelo_e01_a_e17_la_tabla_dispositivos_existe_y_cumple_la_prueba_de_esquema(): void
    {
        $this->assertTrue(Schema::hasTable('dispositivos'), 'Falta la tabla dispositivos.');

        $violaciones = array_filter(
            (new RevisorDeEsquema)->revisar(),
            fn (Violacion $violacion): bool => $violacion->tabla === 'dispositivos',
        );

        $this->assertSame([], array_values(array_map('strval', $violaciones)));
        // Sin plaza por diseño: está en la lista cerrada del modelo de datos.
        $this->assertContains('dispositivos', config('modelo.sin_plaza'));
        $this->assertFalse(Schema::hasColumn('dispositivos', 'id_plazas'));
    }

    #[TestDox('modelo-E01 a modelo-E17: las columnas son las del modelo de datos, en su orden, sin identificador público ni borrado suave')]
    public function test_modelo_e01_a_e17_las_columnas_son_las_del_modelo_de_datos_en_su_orden(): void
    {
        $this->assertSame(
            ['id_dispositivos', 'id_usuarios', 'token_fcm', 'plataforma', 'ultimo_uso_en', 'created_at', 'updated_at'],
            Schema::getColumnListing('dispositivos'),
        );
    }

    #[TestDox('modelo-E07, modelo-E08 y modelo-E17: el token es texto, la plataforma es texto corto y el último uso y las fechas de alta y de cambio son instantes')]
    public function test_modelo_e07_e08_y_e17_las_columnas_llevan_su_tipo(): void
    {
        $this->assertSame('bigint', $this->tipo('id_dispositivos'));
        $this->assertSame('bigint', $this->tipo('id_usuarios'));
        $this->assertSame('text', $this->tipo('token_fcm'));
        $this->assertSame('character varying(255)', $this->tipo('plataforma'));

        foreach (['ultimo_uso_en', 'created_at', 'updated_at'] as $columna) {
            $this->assertSame('timestamp(0) with time zone', $this->tipo($columna), $columna);
        }
    }

    #[TestDox('modelo-E07: la cuenta, el token, la plataforma y el último uso son no nulos')]
    public function test_modelo_e07_la_cuenta_el_token_la_plataforma_y_el_ultimo_uso_son_no_nulos(): void
    {
        foreach (['id_usuarios', 'token_fcm', 'plataforma', 'ultimo_uso_en'] as $columna) {
            $this->assertFalse($this->admiteVacio($columna), "{$columna} es no nula.");
            $this->assertRechazada(fn () => $this->insertarDispositivo([$columna => null]), self::VALOR_VACIO);
        }

        $this->assertSame(0, DB::table('dispositivos')->count());
    }

    #[TestDox('modelo-E09: la llave a la cuenta lleva su índice y ON DELETE RESTRICT; el único es el token')]
    public function test_modelo_e09_la_llave_a_la_cuenta_y_el_unico_del_token(): void
    {
        $this->assertSame([[['id_usuarios'], 'usuarios', ['id_usuarios'], 'restrict']], $this->llaves());
        $this->assertSame([['token_fcm']], $this->unicos());
        $this->assertContains(['id_usuarios'], array_column(Schema::getIndexes('dispositivos'), 'columns'));
    }

    #[TestDox('avisos-E21: la base rechaza el mismo token dos veces, aunque sea de otra cuenta')]
    public function test_avisos_e21_la_base_rechaza_el_mismo_token_dos_veces(): void
    {
        $this->insertarDispositivo(['token_fcm' => 'token-de-prueba']);

        $this->assertRechazada(fn () => $this->insertarDispositivo(['token_fcm' => 'token-de-prueba']), self::VALOR_REPETIDO);
        $this->assertSame(1, DB::table('dispositivos')->count());
    }

    #[TestDox('modelo-E07: la plataforma es ios o android, y la base rechaza cualquier otra')]
    public function test_modelo_e07_la_plataforma_es_ios_o_android(): void
    {
        $this->insertarDispositivo(['plataforma' => 'ios']);
        $this->insertarDispositivo(['plataforma' => 'android']);

        foreach (['web', 'Android', ''] as $plataforma) {
            $this->assertRechazada(fn () => $this->insertarDispositivo(['plataforma' => $plataforma]), self::CHECK_INCUMPLIDO);
        }

        $this->assertSame(2, DB::table('dispositivos')->count());
    }

    #[TestDox('modelo-E09: la base no borra la cuenta de un teléfono registrado; la tabla no lleva el trigger del historial')]
    public function test_modelo_e09_la_base_no_borra_la_cuenta_de_un_telefono(): void
    {
        $cuenta = $this->cuenta();
        $this->insertarDispositivo(['id_usuarios' => $cuenta->getKey()]);

        $this->assertRechazada(
            fn () => DB::table('usuarios')->where('id_usuarios', $cuenta->getKey())->delete(),
            self::LLAVE_FORANEA_ROTA,
        );

        // El token que ya no sirve se borra (avisos-E05, E06): no es historial.
        $this->assertSame(0, DB::table('information_schema.triggers')
            ->where('event_object_schema', 'public')
            ->where('event_object_table', 'dispositivos')
            ->count());
        $this->assertSame(1, DB::table('dispositivos')->delete());
    }

    #[TestDox('modelo-E16: Dispositivo declara su tabla y su llave igual que la base, y llega a su cuenta por id_usuarios')]
    public function test_modelo_e16_dispositivo_declara_su_tabla_y_su_llave_y_llega_a_su_cuenta(): void
    {
        $modelo = new Dispositivo;
        $this->assertSame(['dispositivos', 'id_dispositivos'], [$modelo->getTable(), $modelo->getKeyName()]);
        $this->assertSame([], array_map('strval', (new RevisorDeModelos)->revisar([Dispositivo::class])));
        $this->assertSame('id_usuarios', $modelo->usuario()->getForeignKeyName());

        $cuenta = $this->cuenta();
        $dispositivo = Dispositivo::create([
            'id_usuarios' => $cuenta->getKey(),
            'token_fcm' => 'token-de-prueba',
            'plataforma' => Dispositivo::ANDROID,
            'ultimo_uso_en' => now(),
        ])->fresh();

        $this->assertTrue($cuenta->is($dispositivo->usuario));
        $this->assertSame(Dispositivo::ANDROID, $dispositivo->plataforma);
        $this->assertNotNull($dispositivo->ultimo_uso_en);
        $this->assertSame([Dispositivo::IOS, Dispositivo::ANDROID], Dispositivo::PLATAFORMAS);
    }

    private function cuenta(): Usuario
    {
        return Usuario::factory()->conRol(Rol::USUARIO)->create();
    }

    /**
     * Un teléfono de una cuenta nueva, con un token nuevo, con las columnas que se pasan.
     *
     * @param  array<string, mixed>  $datos
     */
    private function insertarDispositivo(array $datos = []): int
    {
        return DB::table('dispositivos')->insertGetId([
            'id_usuarios' => array_key_exists('id_usuarios', $datos) ? $datos['id_usuarios'] : $this->cuenta()->getKey(),
            'token_fcm' => 'token-'.Str::ulid(),
            'plataforma' => 'android',
            'ultimo_uso_en' => now(),
            'created_at' => now(),
            'updated_at' => now(),
            ...$datos,
        ], 'id_dispositivos');
    }

    /**
     * El tipo de la columna como lo escribe la base: text, timestamp(0) with time zone.
     */
    private function tipo(string $columna): ?string
    {
        return DB::scalar(
            'select format_type(a.atttypid, a.atttypmod)
               from pg_catalog.pg_attribute a
              where a.attrelid = to_regclass(?) and a.attname = ? and not a.attisdropped',
            ['dispositivos', $columna],
        );
    }

    private function admiteVacio(string $columna): bool
    {
        return array_column(Schema::getColumns('dispositivos'), 'nullable', 'name')[$columna];
    }

    /**
     * Las llaves foráneas: columnas, tabla padre, columnas padre y qué hacen al borrar.
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
            Schema::getForeignKeys('dispositivos'),
        );
    }

    /**
     * Las columnas de cada índice único, sin la llave primaria.
     *
     * @return list<list<string>>
     */
    private function unicos(): array
    {
        return array_values(array_map(
            fn (array $indice): array => $indice['columns'],
            array_filter(
                Schema::getIndexes('dispositivos'),
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
