<?php

namespace Tests\Feature\PerfilUsuario;

use App\Esquema\RevisorDeEsquema;
use App\Esquema\RevisorDeModelos;
use App\Esquema\Violacion;
use App\Models\ReporteSoporte;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\PerfilUsuario\Soporte\PerfilDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * La tabla reportes_soporte y su modelo de Eloquent, ReporteSoporte (openspec/changes/perfil-y-soporte/design.md,
 * "Tablas y migración"; vault/arquitectura/modelo-de-datos.md; ADR-0016).
 *
 * La plaza (plazas-E09): con pedido, la del pedido; sin pedido, la que resuelve el servidor (decisión 27 de
 * acta-2026-11-20-modelo-y-pasarela), o ninguna si la cuenta no tiene de dónde tomarla: el reporte se guarda igual
 * (perfil-usuario-E17; migración 2026_11_26_200000_admitir_reportes_soporte_sin_plaza). La llave compuesta
 * (id_pedidos, id_plazas) hace que la base rechace un reporte con otra plaza que la de su pedido, y un CHECK, uno con
 * pedido y sin plaza, que se la saltaría.
 *
 * Las filas se insertan con el constructor de consultas, sin pasar por el modelo, cuando la prueba pide que la base
 * rechace la fila aunque el código no la haya validado.
 *
 * Escenarios: modelo-E01 a modelo-E17 sobre la tabla nueva, y la base de perfil-usuario-E11 a E13 y E17 · Spec:
 * openspec/changes/perfil-y-soporte/specs/perfil-usuario/spec.md · Tarea 0.1 de
 * openspec/changes/perfil-y-soporte/tasks.md
 */
class MigracionTest extends TestCase
{
    use PerfilDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    /** SQLSTATE de PostgreSQL cuando una columna no nula queda vacía. */
    private const VALOR_VACIO = '23502';

    /** SQLSTATE de PostgreSQL cuando una llave foránea no encuentra su fila padre, o su padre tiene hijas. */
    private const LLAVE_FORANEA_ROTA = '23503';

    /** SQLSTATE de PostgreSQL cuando una fila no cumple un CHECK. */
    private const CHECK_INCUMPLIDO = '23514';

    #[TestDox('modelo-E01 a modelo-E17: la tabla reportes_soporte existe y cumple la prueba de esquema')]
    public function test_modelo_e01_a_e17_la_tabla_reportes_soporte_existe_y_cumple_la_prueba_de_esquema(): void
    {
        $this->assertTrue(Schema::hasTable('reportes_soporte'), 'Falta la tabla reportes_soporte.');

        $violaciones = array_filter(
            (new RevisorDeEsquema)->revisar(),
            fn (Violacion $violacion): bool => $violacion->tabla === 'reportes_soporte',
        );

        $this->assertSame([], array_values(array_map('strval', $violaciones)));
    }

    #[TestDox('modelo-E01 a modelo-E17: las columnas son las del modelo de datos, en su orden')]
    public function test_modelo_e01_a_e17_las_columnas_son_las_del_modelo_de_datos_en_su_orden(): void
    {
        $this->assertSame(
            [
                'id_reportes_soporte', 'id_usuarios', 'id_pedidos', 'id_plazas', 'mensaje', 'estado', 'atendido_en',
                'created_at', 'updated_at',
            ],
            Schema::getColumnListing('reportes_soporte'),
        );
    }

    #[TestDox('modelo-E07, modelo-E08 y modelo-E17: la descripción es texto, y la atención y las fechas de alta y de cambio son instantes')]
    public function test_modelo_e07_e08_y_e17_las_columnas_llevan_su_tipo(): void
    {
        $this->assertSame('bigint', $this->tipo('id_reportes_soporte'));
        $this->assertSame('text', $this->tipo('mensaje'));
        $this->assertSame('character varying(255)', $this->tipo('estado'));

        foreach (['atendido_en', 'created_at', 'updated_at'] as $columna) {
            $this->assertSame('timestamp(0) with time zone', $this->tipo($columna), $columna);
        }
    }

    #[TestDox('plazas-E09, perfil-usuario-E11 y perfil-usuario-E17: la cuenta, la descripción y el estado son no nulos; la plaza admite vacío, el reporte de una cuenta sin plaza, pero no con pedido; el pedido y la atención nacen vacíos')]
    public function test_plazas_e09_y_perfil_usuario_e11_y_e17_lo_obligatorio_y_lo_que_nace_vacio(): void
    {
        $this->assertContains('reportes_soporte', config('modelo.plaza_opcional'));

        foreach (['id_usuarios', 'mensaje', 'estado'] as $columna) {
            $this->assertFalse($this->admiteVacio($columna), "{$columna} es no nula.");
        }

        // El pedido va "si él quiere" (perfil-usuario-E11); la atención espera DD-108 (perfil-usuario-E14); la plaza
        // falta cuando la cuenta no tiene de dónde tomarla (perfil-usuario-E17).
        foreach (['id_pedidos', 'id_plazas', 'atendido_en'] as $columna) {
            $this->assertTrue($this->admiteVacio($columna), "{$columna} admite vacío.");
        }

        $this->assertRechazada(fn () => $this->insertarReporte(['mensaje' => null]), self::VALOR_VACIO);
        $this->assertSame(0, DB::table('reportes_soporte')->count());

        // Con pedido, sin plaza no: la llave compuesta hacia el pedido no se revisaría.
        $cuenta = $this->cuentaDeUsuario();
        $pedido = $this->pedidoDe($cuenta, $this->plazaDePrueba());
        $this->assertRechazada(
            fn () => $this->insertarReporte(['id_usuarios' => $cuenta->getKey(), 'id_pedidos' => $pedido->getKey(), 'id_plazas' => null]),
            self::CHECK_INCUMPLIDO,
        );

        $this->insertarReporte(['id_usuarios' => $cuenta->getKey(), 'id_plazas' => null]);
        $this->assertSame(1, DB::table('reportes_soporte')->whereNull('id_plazas')->whereNull('id_pedidos')->count());
    }

    #[TestDox('modelo-E06 y modelo-E09: la llave a la cuenta, la compuesta con la plaza hacia el pedido y la de la plaza, todas ON DELETE RESTRICT; sin únicos')]
    public function test_modelo_e06_y_e09_las_llaves_foraneas_y_ningun_unico(): void
    {
        $this->assertEqualsCanonicalizing([
            [['id_usuarios'], 'usuarios', ['id_usuarios'], 'restrict'],
            [['id_pedidos', 'id_plazas'], 'pedidos', ['id_pedidos', 'id_plazas'], 'restrict'],
            [['id_plazas'], 'plazas', ['id_plazas'], 'restrict'],
        ], $this->llaves());

        // Una persona puede mandar varios reportes, también del mismo pedido.
        $this->assertSame([], $this->unicos());
    }

    #[TestDox('modelo-E06: la base rechaza un reporte con otra plaza que la de su pedido, y lo acepta con la suya o sin pedido')]
    public function test_modelo_e06_la_base_rechaza_un_reporte_con_otra_plaza_que_su_pedido(): void
    {
        $plazaA = $this->plazaDePrueba('Plaza de prueba A');
        $plazaB = $this->plazaDePrueba('Plaza de prueba B');
        $cuenta = $this->cuentaDeUsuario();
        $pedido = $this->pedidoDe($cuenta, $plazaA);

        $this->assertRechazada(
            fn () => $this->insertarReporte([
                'id_usuarios' => $cuenta->getKey(),
                'id_pedidos' => $pedido->getKey(),
                'id_plazas' => $plazaB->getKey(),
            ]),
            self::LLAVE_FORANEA_ROTA,
        );

        $this->insertarReporte([
            'id_usuarios' => $cuenta->getKey(),
            'id_pedidos' => $pedido->getKey(),
            'id_plazas' => $plazaA->getKey(),
        ]);
        // Sin pedido, la llave compuesta no se revisa; la de la plaza, sí.
        $this->insertarReporte(['id_usuarios' => $cuenta->getKey(), 'id_plazas' => $plazaB->getKey()]);

        $this->assertSame(2, DB::table('reportes_soporte')->count());
    }

    #[TestDox('modelo-E07: el estado nace recibido y la base rechaza cualquier otro mientras no se responda DD-108')]
    public function test_modelo_e07_el_estado_nace_recibido_y_la_base_rechaza_otro(): void
    {
        $reporte = $this->insertarReporte();

        $this->assertSame('recibido', DB::table('reportes_soporte')->where('id_reportes_soporte', $reporte)->value('estado'));
        $this->assertRechazada(fn () => $this->insertarReporte(['estado' => 'atendido']), self::CHECK_INCUMPLIDO);
        $this->assertRechazada(fn () => $this->insertarReporte(['estado' => '']), self::CHECK_INCUMPLIDO);

        $this->assertSame(1, DB::table('reportes_soporte')->count());
    }

    #[TestDox('modelo-E09: la base no borra la cuenta ni la plaza de un reporte; la tabla no lleva el trigger del historial')]
    public function test_modelo_e09_la_base_no_borra_la_cuenta_ni_la_plaza_de_un_reporte(): void
    {
        $plaza = $this->plazaDePrueba();
        $cuenta = $this->cuentaDeUsuario();
        $this->insertarReporte(['id_usuarios' => $cuenta->getKey(), 'id_plazas' => $plaza->getKey()]);

        $this->assertRechazada(
            fn () => DB::table('usuarios')->where('id_usuarios', $cuenta->getKey())->delete(),
            self::LLAVE_FORANEA_ROTA,
        );
        $this->assertRechazada(
            fn () => DB::table('plazas')->where('id_plazas', $plaza->getKey())->delete(),
            self::LLAVE_FORANEA_ROTA,
        );

        // impedir_cambio() es solo de pedidos, pedido_eventos, pagos y reembolsos (design.md, "Tablas y migración").
        $this->assertSame(0, DB::table('information_schema.triggers')
            ->where('event_object_schema', 'public')
            ->where('event_object_table', 'reportes_soporte')
            ->count());
    }

    #[TestDox('modelo-E16: ReporteSoporte declara su tabla y su llave igual que la base, y llega a su cuenta, su pedido y su plaza')]
    public function test_modelo_e16_reporte_soporte_declara_su_tabla_y_su_llave_y_llega_a_lo_suyo(): void
    {
        $modelo = new ReporteSoporte;
        $this->assertSame(['reportes_soporte', 'id_reportes_soporte'], [$modelo->getTable(), $modelo->getKeyName()]);
        $this->assertSame([], array_map('strval', (new RevisorDeModelos)->revisar([ReporteSoporte::class])));

        $plaza = $this->plazaDePrueba();
        $cuenta = $this->cuentaDeUsuario();
        $pedido = $this->pedidoDe($cuenta, $plaza);

        $reporte = ReporteSoporte::create([
            'id_usuarios' => $cuenta->getKey(),
            'id_pedidos' => $pedido->getKey(),
            'id_plazas' => $plaza->getKey(),
            'mensaje' => 'La prenda llegó con una mancha.',
        ])->fresh();

        $this->assertSame(ReporteSoporte::RECIBIDO, $reporte->estado);
        $this->assertNull($reporte->atendido_en);
        $this->assertNotNull($reporte->created_at);
        $this->assertTrue($cuenta->is($reporte->usuario));
        $this->assertTrue($pedido->is($reporte->pedido));
        $this->assertTrue($plaza->is($reporte->plaza));
    }

    /**
     * Un reporte de una cuenta nueva, sin pedido, en una plaza nueva, con las columnas que se pasan.
     *
     * @param  array<string, mixed>  $datos
     */
    private function insertarReporte(array $datos = []): int
    {
        return DB::table('reportes_soporte')->insertGetId([
            'id_usuarios' => $datos['id_usuarios'] ?? $this->cuentaDeUsuario()->getKey(),
            'id_plazas' => array_key_exists('id_plazas', $datos)
                ? $datos['id_plazas']
                : $this->plazaDePrueba('Plaza de prueba '.Str::ulid())->getKey(),
            'mensaje' => 'Descripción de prueba.',
            'created_at' => now(),
            'updated_at' => now(),
            ...$datos,
        ], 'id_reportes_soporte');
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
            ['reportes_soporte', $columna],
        );
    }

    private function admiteVacio(string $columna): bool
    {
        return array_column(Schema::getColumns('reportes_soporte'), 'nullable', 'name')[$columna];
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
            Schema::getForeignKeys('reportes_soporte'),
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
                Schema::getIndexes('reportes_soporte'),
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
