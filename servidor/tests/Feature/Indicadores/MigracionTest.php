<?php

namespace Tests\Feature\Indicadores;

use App\Esquema\RevisorDeEsquema;
use App\Esquema\RevisorDeModelos;
use App\Esquema\Violacion;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * La tabla actividad_usuarios: una fila por persona y día, que alimenta los usuarios activos
 * (openspec/changes/indicadores/design.md, "Tablas y migración"; vault/arquitectura/modelo-de-datos.md; ADR-0016;
 * NSV-2026-001-v8.pdf:46:ANEXO D, Sección D.5).
 *
 * Es tabla de enlace: su llave es (id_usuarios, fecha), sin id_actividad_usuarios ni created_at ni updated_at, y no
 * tiene modelo de Eloquent; se lee con el constructor de consultas, como rol_usuario se maneja con belongsToMany
 * (openspec/changes/modelo-de-datos/design.md, decisión 7). No lleva plaza: la persona no pertenece a una plaza
 * (config/modelo.php, sin_plaza). Qué la escribe es DD-111 (indicadores-E07, bloqueado): aquí la prueba inserta sus
 * filas.
 *
 * Escenarios: modelo-E01 a modelo-E17 sobre la tabla nueva; indicadores-E06 (una fila por persona y día) · Spec:
 * openspec/changes/indicadores/specs/indicadores/spec.md · Tarea 0.1 de openspec/changes/indicadores/tasks.md
 */
class MigracionTest extends TestCase
{
    use RefreshDatabase;

    private const TABLA = 'actividad_usuarios';

    /** SQLSTATE de PostgreSQL cuando una columna no nula queda vacía. */
    private const VALOR_VACIO = '23502';

    /** SQLSTATE de PostgreSQL cuando una llave foránea no encuentra su fila padre, o su padre tiene hijas. */
    private const LLAVE_FORANEA_ROTA = '23503';

    /** SQLSTATE de PostgreSQL cuando un valor repite un único o la llave primaria. */
    private const VALOR_REPETIDO = '23505';

    #[TestDox('modelo-E01 a modelo-E17: la tabla actividad_usuarios existe y cumple la prueba de esquema')]
    public function test_modelo_e01_a_e17_la_tabla_actividad_usuarios_existe_y_cumple_la_prueba_de_esquema(): void
    {
        $this->assertTrue(Schema::hasTable(self::TABLA), 'Falta la tabla actividad_usuarios.');

        $violaciones = array_filter(
            (new RevisorDeEsquema)->revisar(),
            fn (Violacion $violacion): bool => $violacion->tabla === self::TABLA,
        );

        $this->assertSame([], array_values(array_map('strval', $violaciones)));
        // Sin plaza por diseño y de enlace: está en las dos listas cerradas del modelo de datos.
        $this->assertContains(self::TABLA, config('modelo.sin_plaza'));
        $this->assertContains(self::TABLA, config('modelo.de_enlace'));
        $this->assertFalse(Schema::hasColumn(self::TABLA, 'id_plazas'));
    }

    #[TestDox('modelo-E17: las columnas son la cuenta y el día, sin llave propia ni fechas de alta y de cambio')]
    public function test_modelo_e17_las_columnas_son_la_cuenta_y_el_dia_sin_llave_propia_ni_fechas(): void
    {
        $this->assertSame(['id_usuarios', 'fecha'], Schema::getColumnListing(self::TABLA));

        $llave = collect(Schema::getIndexes(self::TABLA))->firstWhere('primary', true);
        $this->assertSame(['id_usuarios', 'fecha'], $llave['columns'] ?? null);
    }

    #[TestDox('modelo-E07: la cuenta es bigint y el día es date, y los dos son no nulos')]
    public function test_modelo_e07_la_cuenta_es_bigint_y_el_dia_es_date_no_nulos(): void
    {
        $this->assertSame('bigint', $this->tipo('id_usuarios'));
        $this->assertSame('date', $this->tipo('fecha'));

        foreach (['id_usuarios', 'fecha'] as $columna) {
            $this->assertFalse($this->admiteVacio($columna), "{$columna} es no nula.");
        }

        $this->assertRechazada(fn () => $this->insertar(null, '2026-01-05'), self::VALOR_VACIO);
        $this->assertRechazada(fn () => $this->insertar($this->cuenta()->getKey(), null), self::VALOR_VACIO);
        $this->assertSame(0, DB::table(self::TABLA)->count());
    }

    #[TestDox('modelo-E09 y modelo-E10: la llave a la cuenta lleva ON DELETE RESTRICT y su índice es la llave primaria, que empieza con ella')]
    public function test_modelo_e09_y_e10_la_llave_a_la_cuenta_y_su_indice(): void
    {
        $llaves = array_map(
            fn (array $llave): array => [
                $llave['columns'],
                $llave['foreign_table'],
                $llave['foreign_columns'],
                strtolower($llave['on_delete']),
            ],
            Schema::getForeignKeys(self::TABLA),
        );

        $this->assertSame([[['id_usuarios'], 'usuarios', ['id_usuarios'], 'restrict']], $llaves);

        $cuenta = $this->cuenta();
        $this->insertar($cuenta->getKey(), '2026-01-05');

        $this->assertRechazada(
            fn () => DB::table('usuarios')->where('id_usuarios', $cuenta->getKey())->delete(),
            self::LLAVE_FORANEA_ROTA,
        );
        $this->assertRechazada(fn () => $this->insertar($cuenta->getKey() + 1000, '2026-01-05'), self::LLAVE_FORANEA_ROTA);
    }

    #[TestDox('indicadores-E06: la base guarda una sola fila por persona y día; otro día u otra persona son otra fila')]
    public function test_indicadores_e06_una_fila_por_persona_y_dia(): void
    {
        $una = $this->cuenta();
        $otra = $this->cuenta();

        $this->insertar($una->getKey(), '2026-01-05');
        $this->insertar($una->getKey(), '2026-01-06');
        $this->insertar($otra->getKey(), '2026-01-05');

        $this->assertRechazada(fn () => $this->insertar($una->getKey(), '2026-01-05'), self::VALOR_REPETIDO);
        $this->assertSame(3, DB::table(self::TABLA)->count());
    }

    #[TestDox('modelo-E16: actividad_usuarios no tiene modelo de Eloquent ni lleva el trigger del historial')]
    public function test_modelo_e16_actividad_usuarios_no_tiene_modelo_ni_trigger(): void
    {
        $tablasConModelo = array_map(
            fn (string $clase): string => (new $clase)->getTable(),
            RevisorDeModelos::modelosDeLaAplicacion(),
        );

        $this->assertNotContains(self::TABLA, $tablasConModelo);
        $this->assertSame(0, DB::table('information_schema.triggers')
            ->where('event_object_schema', 'public')
            ->where('event_object_table', self::TABLA)
            ->count());
    }

    private function cuenta(): Usuario
    {
        return Usuario::factory()->conRol(Rol::USUARIO)->create();
    }

    private function insertar(?int $cuenta, ?string $fecha): void
    {
        DB::table(self::TABLA)->insert(['id_usuarios' => $cuenta, 'fecha' => $fecha]);
    }

    /**
     * El tipo de la columna como lo escribe la base: bigint, date.
     */
    private function tipo(string $columna): ?string
    {
        return DB::scalar(
            'select format_type(a.atttypid, a.atttypmod)
               from pg_catalog.pg_attribute a
              where a.attrelid = to_regclass(?) and a.attname = ? and not a.attisdropped',
            [self::TABLA, $columna],
        );
    }

    private function admiteVacio(string $columna): bool
    {
        return array_column(Schema::getColumns(self::TABLA), 'nullable', 'name')[$columna];
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
