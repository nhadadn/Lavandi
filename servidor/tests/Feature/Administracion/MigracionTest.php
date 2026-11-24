<?php

namespace Tests\Feature\Administracion;

use App\Esquema\RevisorDeEsquema;
use App\Esquema\RevisorDeModelos;
use App\Esquema\Violacion;
use App\Models\AvisoMasivo;
use App\Models\Comision;
use App\Models\Lavanderia;
use App\Models\MovimientoPasarela;
use App\Models\Pago;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * Las tablas comisiones, avisos_masivos y movimientos_pasarela y sus modelos de Eloquent, Comision, AvisoMasivo y
 * MovimientoPasarela (openspec/changes/administracion/design.md, "Tablas y migración"; vault/arquitectura/
 * modelo-de-datos.md; ADR-0016).
 *
 * - comisiones: el porcentaje global, uno solo para todo el sistema (sin plaza ni lavandería), y el propio de cada
 *   lavandería, con su plaza, cada uno con su vigencia. La llave hacia la lavandería es compuesta con la plaza, el
 *   porcentaje va de 0 a 100 y la migración no siembra ninguno: los captura EL CLIENTE (administracion-E11 a E13). La
 *   global era de cada plaza hasta la migración 2026_11_26_100000_hacer_global_la_comision_global, que convierte lo que
 *   hubiera (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #5, "por socio o global").
 * - avisos_masivos: el aviso al celular con su título, su texto, quién lo envió y a cuántos teléfonos salió. La plaza es
 *   opcional: vacía, el aviso es para todas (lista "plaza opcional" de config/modelo.php).
 * - movimientos_pasarela: los pagos que acreditó la pasarela, leídos de su API al conciliar un periodo, una fila por
 *   orden (segunda adenda del 2026-11-22 de ADR-0010, decisiones 6 y 7). Sin plaza propia: la plaza de un movimiento es
 *   la del pedido con el que cuadra, y uno sin pedido es justo una diferencia (lista "sin plaza" de config/modelo.php).
 *
 * Las filas se insertan con el constructor de consultas, sin pasar por el modelo, para probar lo que hace la base.
 *
 * Escenarios: modelo-E01 a modelo-E17 sobre las tres tablas, y la base de administracion-E11 a E16 y E19 a E24 ·
 * Spec: openspec/changes/administracion/specs/administracion/spec.md · Tareas 0.1 y 0.2
 */
class MigracionTest extends TestCase
{
    use PlazasDePrueba;
    use RefreshDatabase;

    /** SQLSTATE de PostgreSQL cuando una columna no nula queda vacía. */
    private const VALOR_VACIO = '23502';

    /** SQLSTATE de PostgreSQL cuando una llave foránea no encuentra su fila padre, o su padre tiene hijas. */
    private const LLAVE_FORANEA_ROTA = '23503';

    /** SQLSTATE de PostgreSQL cuando una fila repite un valor único. */
    private const UNICO_REPETIDO = '23505';

    /** SQLSTATE de PostgreSQL cuando una fila no cumple un CHECK. */
    private const CHECK_INCUMPLIDO = '23514';

    private const INSTANTE = 'timestamp(0) with time zone';

    #[TestDox('modelo-E01 a modelo-E17: las tablas comisiones y avisos_masivos existen y cumplen la prueba de esquema, y sus modelos también')]
    public function test_modelo_e01_a_e17_las_dos_tablas_existen_y_cumplen_la_prueba_de_esquema(): void
    {
        foreach (['comisiones', 'avisos_masivos'] as $tabla) {
            $this->assertTrue(Schema::hasTable($tabla), "Falta la tabla {$tabla}.");
        }

        $violaciones = array_filter(
            (new RevisorDeEsquema)->revisar(),
            fn (Violacion $violacion): bool => in_array($violacion->tabla, ['comisiones', 'avisos_masivos'], true),
        );

        $this->assertSame([], array_values(array_map('strval', $violaciones)));
        $this->assertSame([], array_map('strval', (new RevisorDeModelos)->revisar([Comision::class, AvisoMasivo::class])));
        $this->assertSame(['comisiones', 'id_comisiones'], [(new Comision)->getTable(), (new Comision)->getKeyName()]);
        $this->assertSame(['avisos_masivos', 'id_avisos_masivos'], [(new AvisoMasivo)->getTable(), (new AvisoMasivo)->getKeyName()]);
    }

    #[TestDox('modelo-E01 a modelo-E17: las columnas son las del modelo de datos, en su orden, con sus fechas de alta y de cambio')]
    public function test_modelo_e01_a_e17_las_columnas_son_las_del_modelo_de_datos(): void
    {
        $this->assertSame(
            ['id_comisiones', 'id_plazas', 'id_lavanderias', 'porcentaje', 'vigente_desde', 'vigente_hasta', 'created_at', 'updated_at'],
            Schema::getColumnListing('comisiones'),
        );
        $this->assertSame(
            [
                'id_avisos_masivos', 'id_plazas', 'segmento', 'titulo', 'mensaje', 'id_usuarios', 'enviado_en',
                'destinatarios', 'created_at', 'updated_at',
            ],
            Schema::getColumnListing('avisos_masivos'),
        );
    }

    #[TestDox('modelo-E07, modelo-E08 y modelo-E17: el porcentaje es numeric(5,2), el segmento jsonb, el texto text, los destinatarios un entero y los instantes timestamptz')]
    public function test_modelo_e07_e08_y_e17_las_columnas_llevan_su_tipo(): void
    {
        $this->assertSame('bigint', $this->tipo('comisiones', 'id_comisiones'));
        $this->assertSame('numeric(5,2)', $this->tipo('comisiones', 'porcentaje'));

        foreach (['vigente_desde', 'vigente_hasta', 'created_at', 'updated_at'] as $columna) {
            $this->assertSame(self::INSTANTE, $this->tipo('comisiones', $columna), $columna);
        }

        $this->assertSame('bigint', $this->tipo('avisos_masivos', 'id_avisos_masivos'));
        $this->assertSame('jsonb', $this->tipo('avisos_masivos', 'segmento'));
        $this->assertSame('character varying(255)', $this->tipo('avisos_masivos', 'titulo'));
        $this->assertSame('text', $this->tipo('avisos_masivos', 'mensaje'));
        $this->assertSame('integer', $this->tipo('avisos_masivos', 'destinatarios'));

        foreach (['enviado_en', 'created_at', 'updated_at'] as $columna) {
            $this->assertSame(self::INSTANTE, $this->tipo('avisos_masivos', $columna), $columna);
        }
    }

    #[TestDox('plazas-E09 y administracion-E11: en comisiones el porcentaje y el inicio de vigencia son no nulos; la global, una para todo el sistema, no lleva plaza ni lavandería, la propia lleva las dos, y la tabla está en la lista de plaza opcional')]
    public function test_plazas_e09_y_administracion_e11_lo_obligatorio_y_lo_opcional_de_comisiones(): void
    {
        $this->assertContains('comisiones', config('modelo.plaza_opcional'));

        foreach (['porcentaje', 'vigente_desde'] as $columna) {
            $this->assertFalse($this->admiteVacio('comisiones', $columna), "{$columna} es no nula.");
            $this->assertRechazada(fn () => $this->insertarComision([$columna => null]), self::VALOR_VACIO);
        }

        foreach (['id_plazas', 'id_lavanderias', 'vigente_hasta'] as $columna) {
            $this->assertTrue($this->admiteVacio('comisiones', $columna), "{$columna} admite vacío.");
        }

        $plaza = $this->plazaDePrueba();
        $lavanderia = Lavanderia::factory()->en($plaza)->create();

        // Una global con plaza, o una propia sin ella, que se saltaría la llave compuesta: ninguna de las dos.
        $this->assertRechazada(fn () => $this->insertarComision(['id_plazas' => $plaza->getKey()]), self::CHECK_INCUMPLIDO);
        $this->assertRechazada(fn () => $this->insertarComision(['id_lavanderias' => $lavanderia->getKey()]), self::CHECK_INCUMPLIDO);

        // La global: sin plaza, sin lavandería y sin fin de vigencia. La propia, con su lavandería y su plaza.
        $this->insertarComision();
        $this->insertarPropia($lavanderia);
        $this->assertSame(1, DB::table('comisiones')->whereNull('id_plazas')->whereNull('id_lavanderias')->whereNull('vigente_hasta')->count());
        $this->assertSame(1, DB::table('comisiones')->where('id_plazas', $plaza->getKey())->where('id_lavanderias', $lavanderia->getKey())->count());
    }

    #[TestDox('administracion-E11: la migración que hace global la comisión convierte las globales de cada plaza en la del sistema, con su historial: queda abierta la que se fijó al último, las demás se cierran cuando empezó esa, y las propias no cambian')]
    public function test_administracion_e11_la_migracion_convierte_las_globales_de_cada_plaza_en_la_del_sistema(): void
    {
        // La tabla como la dejó la migración que la creó: la plaza obligatoria y una global abierta por plaza.
        DB::statement('alter table comisiones drop constraint comisiones_global_o_propia_check');
        DB::statement('drop index comisiones_global_abierta_unique');
        DB::statement('alter table comisiones alter column id_plazas set not null');
        DB::statement('create unique index comisiones_global_abierta_unique on comisiones (id_plazas)
            where id_lavanderias is null and vigente_hasta is null');

        $plazaA = $this->plazaDePrueba('Plaza de prueba A');
        $plazaB = $this->plazaDePrueba('Plaza de prueba B');
        $plazaC = $this->plazaDePrueba('Plaza de prueba C');
        $plazaD = $this->plazaDePrueba('Plaza de prueba D');
        $deA = Lavanderia::factory()->en($plazaA)->create();

        $historialDeA = $this->insertarComision(['id_plazas' => $plazaA->getKey(), 'porcentaje' => '8', 'vigente_desde' => '2025-06-01 00:00:00+00', 'vigente_hasta' => '2026-01-01 00:00:00+00']);
        $abiertaDeA = $this->insertarComision(['id_plazas' => $plazaA->getKey(), 'porcentaje' => '10', 'vigente_desde' => '2026-01-01 00:00:00+00']);
        $abiertaDeD = $this->insertarComision(['id_plazas' => $plazaD->getKey(), 'porcentaje' => '9', 'vigente_desde' => '2025-12-01 00:00:00+00']);
        // C y B se fijaron en el mismo instante, el último: queda la de B, que se guardó después.
        $abiertaDeC = $this->insertarComision(['id_plazas' => $plazaC->getKey(), 'porcentaje' => '11', 'vigente_desde' => '2026-02-01 00:00:00+00']);
        $abiertaDeB = $this->insertarComision(['id_plazas' => $plazaB->getKey(), 'porcentaje' => '12', 'vigente_desde' => '2026-02-01 00:00:00+00']);
        $propia = $this->insertarPropia($deA, ['porcentaje' => '7', 'vigente_desde' => '2026-01-15 00:00:00+00']);

        $migracion = require database_path('migrations/2026_11_26_100000_hacer_global_la_comision_global.php');
        $migracion->up();

        $fila = fn (int $id): array => (array) DB::table('comisiones')
            ->where('id_comisiones', $id)
            ->selectRaw("id_plazas, id_lavanderias, porcentaje, to_char(vigente_desde at time zone 'UTC', 'YYYY-MM-DD') as desde, to_char(vigente_hasta at time zone 'UTC', 'YYYY-MM-DD') as hasta")
            ->first();

        $this->assertSame(['id_plazas' => null, 'id_lavanderias' => null, 'porcentaje' => '12.00', 'desde' => '2026-02-01', 'hasta' => null], $fila($abiertaDeB));
        $this->assertSame(['id_plazas' => null, 'id_lavanderias' => null, 'porcentaje' => '10.00', 'desde' => '2026-01-01', 'hasta' => '2026-02-01'], $fila($abiertaDeA));
        $this->assertSame(['id_plazas' => null, 'id_lavanderias' => null, 'porcentaje' => '9.00', 'desde' => '2025-12-01', 'hasta' => '2026-02-01'], $fila($abiertaDeD));
        $this->assertSame(['id_plazas' => null, 'id_lavanderias' => null, 'porcentaje' => '8.00', 'desde' => '2025-06-01', 'hasta' => '2026-01-01'], $fila($historialDeA));
        $this->assertSame(['id_plazas' => $plazaA->getKey(), 'id_lavanderias' => $deA->getKey(), 'porcentaje' => '7.00', 'desde' => '2026-01-15', 'hasta' => null], $fila($propia));
        // La de C empezó en el mismo instante que la que quedó: nunca rigió sola.
        $this->assertFalse(DB::table('comisiones')->where('id_comisiones', $abiertaDeC)->exists());

        // La tabla queda como la nueva: una global abierta en todo el sistema, y ninguna global con plaza.
        $this->assertRechazada(fn () => $this->insertarComision(['vigente_desde' => '2026-03-01 00:00:00+00']), self::UNICO_REPETIDO);
        $this->assertRechazada(
            fn () => $this->insertarComision(['id_plazas' => $plazaA->getKey(), 'vigente_hasta' => '2026-01-02 00:00:00+00']),
            self::CHECK_INCUMPLIDO,
        );
    }

    #[TestDox('administracion-E11: la migración no siembra ningún porcentaje: comisiones nace vacía')]
    public function test_administracion_e11_la_migracion_no_siembra_ningun_porcentaje(): void
    {
        $this->assertSame(0, DB::table('comisiones')->count());
    }

    #[TestDox('administracion-E13: la base rechaza un porcentaje menor que 0 o mayor que 100, y acepta 0 y 100')]
    public function test_administracion_e13_la_base_rechaza_un_porcentaje_fuera_de_rango(): void
    {
        foreach (['-0.01', '100.01', '-5', '150'] as $porcentaje) {
            $this->assertRechazada(fn () => $this->insertarComision(['porcentaje' => $porcentaje]), self::CHECK_INCUMPLIDO);
        }

        $this->insertarComision(['porcentaje' => '0', 'vigente_hasta' => '2026-02-01 00:00:00+00']);
        $this->insertarComision(['porcentaje' => '100', 'vigente_desde' => '2026-02-01 00:00:00+00']);

        $this->assertSame(['0.00', '100.00'], DB::table('comisiones')->orderBy('id_comisiones')->pluck('porcentaje')->all());
    }

    #[TestDox('modelo-E07: la vigencia de una comisión termina después de empezar, o no termina')]
    public function test_modelo_e07_la_vigencia_termina_despues_de_empezar(): void
    {
        foreach (['2026-01-01 00:00:00+00', '2025-12-31 23:59:59+00'] as $fin) {
            $this->assertRechazada(fn () => $this->insertarComision(['vigente_hasta' => $fin]), self::CHECK_INCUMPLIDO);
        }

        $this->insertarComision(['vigente_hasta' => '2026-01-01 00:00:01+00']);
        $this->assertSame(1, DB::table('comisiones')->count());
    }

    #[TestDox('administracion-E11 y administracion-E12: hay a lo más una comisión global abierta en todo el sistema, con lavanderías en dos plazas, y una lavandería a lo más una propia abierta; las cerradas quedan como historial')]
    public function test_administracion_e11_y_e12_a_lo_mas_una_global_abierta_y_una_propia_por_lavanderia(): void
    {
        $lavanderia = Lavanderia::factory()->en($this->plazaDePrueba())->create();
        $deOtraPlaza = Lavanderia::factory()->en($this->plazaDePrueba('Plaza de prueba 2'))->create();

        $this->insertarComision();
        $this->assertRechazada(fn () => $this->insertarComision(['vigente_desde' => '2026-03-01 00:00:00+00']), self::UNICO_REPETIDO);

        $this->insertarPropia($lavanderia);
        $this->assertRechazada(
            fn () => $this->insertarPropia($lavanderia, ['vigente_desde' => '2026-03-01 00:00:00+00']),
            self::UNICO_REPETIDO,
        );
        // La de otra lavandería, de otra plaza, es otra propia.
        $this->insertarPropia($deOtraPlaza);

        // Las cerradas no cuentan: un cambio de porcentaje es una fila nueva y la anterior queda como historial.
        $this->insertarComision(['vigente_desde' => '2025-01-01 00:00:00+00', 'vigente_hasta' => '2026-01-01 00:00:00+00']);
        $this->insertarPropia($lavanderia, [
            'vigente_desde' => '2025-01-01 00:00:00+00',
            'vigente_hasta' => '2026-01-01 00:00:00+00',
        ]);

        $this->assertSame(5, DB::table('comisiones')->count());
    }

    #[TestDox('modelo-E06 y modelo-E09: comisiones lleva la llave a la plaza y la compuesta con la plaza hacia la lavandería, ON DELETE RESTRICT; la base rechaza la comisión de una lavandería con otra plaza que la suya')]
    public function test_modelo_e06_y_e09_las_llaves_de_comisiones(): void
    {
        $this->assertEqualsCanonicalizing([
            [['id_plazas'], 'plazas', ['id_plazas'], 'restrict'],
            [['id_lavanderias', 'id_plazas'], 'lavanderias', ['id_lavanderias', 'id_plazas'], 'restrict'],
        ], $this->llaves('comisiones'));

        $plazaA = $this->plazaDePrueba('Plaza de prueba A');
        $plazaB = $this->plazaDePrueba('Plaza de prueba B');
        $deA = Lavanderia::factory()->en($plazaA)->create();

        $this->assertRechazada(fn () => $this->insertarPropia($deA, ['id_plazas' => $plazaB->getKey()]), self::LLAVE_FORANEA_ROTA);
        $this->assertRechazada(fn () => $this->insertarPropia($deA, ['id_plazas' => 999_999]), self::LLAVE_FORANEA_ROTA);

        $this->insertarPropia($deA);

        // La base no borra la plaza ni la lavandería de una comisión.
        $this->assertRechazada(fn () => DB::table('lavanderias')->where('id_lavanderias', $deA->getKey())->delete(), self::LLAVE_FORANEA_ROTA);
        $this->assertRechazada(fn () => DB::table('plazas')->where('id_plazas', $plazaA->getKey())->delete(), self::LLAVE_FORANEA_ROTA);
    }

    #[TestDox('plazas-E09 y administracion-E23: en avisos_masivos la plaza y el segmento son opcionales (vacíos, el aviso es para todas las plazas) y la tabla está en la lista de plaza opcional; el título, el texto, quién lo envió y cuándo son no nulos')]
    public function test_plazas_e09_y_administracion_e23_lo_obligatorio_y_lo_opcional_de_avisos_masivos(): void
    {
        $this->assertContains('avisos_masivos', config('modelo.plaza_opcional'));

        foreach (['id_plazas', 'segmento', 'destinatarios'] as $columna) {
            $this->assertTrue($this->admiteVacio('avisos_masivos', $columna), "{$columna} admite vacío.");
        }

        foreach (['titulo', 'mensaje', 'id_usuarios', 'enviado_en'] as $columna) {
            $this->assertFalse($this->admiteVacio('avisos_masivos', $columna), "{$columna} es no nula.");
            $this->assertRechazada(fn () => $this->insertarAviso([$columna => null]), self::VALOR_VACIO);
        }

        // Global: sin plaza ni segmento. Con plaza, solo una que existe.
        $this->insertarAviso();
        $this->insertarAviso(['id_plazas' => $this->plazaDePrueba()->getKey(), 'segmento' => json_encode(['de_prueba' => true])]);
        $this->assertRechazada(fn () => $this->insertarAviso(['id_plazas' => 999_999]), self::LLAVE_FORANEA_ROTA);

        $this->assertSame(2, DB::table('avisos_masivos')->count());
    }

    #[TestDox('modelo-E07 y modelo-E09: los destinatarios no son negativos; avisos_masivos lleva sus llaves a la plaza y a la cuenta que lo envió, ON DELETE RESTRICT')]
    public function test_modelo_e07_y_e09_destinatarios_y_llaves_de_avisos_masivos(): void
    {
        $this->assertRechazada(fn () => $this->insertarAviso(['destinatarios' => -1]), self::CHECK_INCUMPLIDO);
        $this->insertarAviso(['destinatarios' => 0]);
        $this->insertarAviso(['destinatarios' => 3]);

        $this->assertEqualsCanonicalizing([
            [['id_plazas'], 'plazas', ['id_plazas'], 'restrict'],
            [['id_usuarios'], 'usuarios', ['id_usuarios'], 'restrict'],
        ], $this->llaves('avisos_masivos'));

        $administracion = $this->administracion();
        $this->insertarAviso(['id_usuarios' => $administracion->getKey()]);
        $this->assertRechazada(fn () => DB::table('usuarios')->where('id_usuarios', $administracion->getKey())->delete(), self::LLAVE_FORANEA_ROTA);
    }

    #[TestDox('modelo-E11 y modelo-E12: ninguna de las dos tablas lleva el trigger impedir_cambio(), que es solo del historial del pedido y del dinero')]
    public function test_modelo_e11_y_e12_ninguna_lleva_el_trigger_del_historial(): void
    {
        $this->assertSame(0, DB::table('information_schema.triggers')
            ->where('event_object_schema', 'public')
            ->whereIn('event_object_table', ['comisiones', 'avisos_masivos'])
            ->count());
    }

    #[TestDox('modelo-E16: Comision llega a su plaza y a su lavandería, y toma la plaza de su lavandería; la global no tiene ninguna de las dos; AvisoMasivo llega a su plaza y a la cuenta que lo envió')]
    public function test_modelo_e16_los_modelos_llegan_a_lo_suyo(): void
    {
        $plaza = $this->plazaDePrueba();
        $lavanderia = Lavanderia::factory()->en($plaza)->create();

        $propia = Comision::create([
            'id_lavanderias' => $lavanderia->getKey(),
            'porcentaje' => '12.5',
            'vigente_desde' => '2026-01-01 00:00:00+00',
        ])->fresh();

        $this->assertSame($plaza->getKey(), $propia->id_plazas);
        $this->assertSame('12.50', $propia->porcentaje);
        $this->assertSame('2026-01-01T00:00:00Z', $propia->vigente_desde->toIso8601ZuluString());
        $this->assertNull($propia->vigente_hasta);
        $this->assertTrue($plaza->is($propia->plaza));
        $this->assertTrue($lavanderia->is($propia->lavanderia));

        $global = Comision::create(['porcentaje' => '10', 'vigente_desde' => '2026-01-01 00:00:00+00'])->fresh();
        $this->assertSame([null, null, '10.00'], [$global->id_plazas, $global->id_lavanderias, $global->porcentaje]);
        $this->assertNull($global->plaza);
        $this->assertNull($global->lavanderia);

        $administracion = $this->administracion();
        $aviso = AvisoMasivo::create([
            'titulo' => 'Mantenimiento',
            'mensaje' => 'Texto de prueba.',
            'id_usuarios' => $administracion->getKey(),
            'enviado_en' => '2026-12-03 12:00:00+00',
            'segmento' => ['de_prueba' => true],
        ])->fresh();

        $this->assertNull($aviso->id_plazas);
        $this->assertNull($aviso->plaza);
        $this->assertSame(['de_prueba' => true], $aviso->segmento);
        $this->assertTrue($administracion->is($aviso->usuario));
        $this->assertSame('2026-12-03T12:00:00Z', $aviso->enviado_en->toIso8601ZuluString());
    }

    #[TestDox('modelo-E01 a modelo-E17: la tabla movimientos_pasarela existe y cumple la prueba de esquema, y su modelo también')]
    public function test_modelo_e01_a_e17_movimientos_pasarela_existe_y_cumple_la_prueba_de_esquema(): void
    {
        $this->assertTrue(Schema::hasTable('movimientos_pasarela'));

        $violaciones = array_filter(
            (new RevisorDeEsquema)->revisar(),
            fn (Violacion $violacion): bool => $violacion->tabla === 'movimientos_pasarela',
        );

        $this->assertSame([], array_values(array_map('strval', $violaciones)));
        $this->assertSame([], array_map('strval', (new RevisorDeModelos)->revisar([MovimientoPasarela::class])));
        $this->assertSame(
            ['movimientos_pasarela', 'id_movimientos_pasarela'],
            [(new MovimientoPasarela)->getTable(), (new MovimientoPasarela)->getKeyName()],
        );
    }

    #[TestDox('administracion-E20: movimientos_pasarela trae de cada pago de la pasarela el id de la orden, el monto, la comisión y la fecha del pago, en su orden, con sus fechas de alta y de cambio; el dinero en numeric(12,2) y cada instante en timestamptz')]
    public function test_administracion_e20_las_columnas_de_movimientos_pasarela(): void
    {
        $this->assertSame(
            ['id_movimientos_pasarela', 'pasarela', 'id_pasarela_orden', 'monto', 'comision', 'pagado_en', 'created_at', 'updated_at'],
            Schema::getColumnListing('movimientos_pasarela'),
        );

        $this->assertSame('bigint', $this->tipo('movimientos_pasarela', 'id_movimientos_pasarela'));
        $this->assertSame('numeric(12,2)', $this->tipo('movimientos_pasarela', 'monto'));
        $this->assertSame('numeric(12,2)', $this->tipo('movimientos_pasarela', 'comision'));

        foreach (['pagado_en', 'created_at', 'updated_at'] as $columna) {
            $this->assertSame(self::INSTANTE, $this->tipo('movimientos_pasarela', $columna), $columna);
        }
    }

    #[TestDox('administracion-E20 y plazas-E09: movimientos_pasarela no lleva plaza propia, está en la lista sin plaza y no apunta a ningún pago: un movimiento sin pedido también se guarda')]
    public function test_administracion_e20_movimientos_pasarela_no_lleva_plaza(): void
    {
        $this->assertContains('movimientos_pasarela', config('modelo.sin_plaza'));
        $this->assertNotContains('id_plazas', Schema::getColumnListing('movimientos_pasarela'));
        $this->assertSame([], $this->llaves('movimientos_pasarela'));

        // Una orden que no es de ningún cobro de la plataforma.
        $this->insertarMovimiento(['id_pasarela_orden' => 'orden-sin-pedido']);
        $this->assertSame(1, DB::table('movimientos_pasarela')->count());
    }

    #[TestDox('administracion-E20: una orden de la pasarela es una sola fila: la misma orden en la misma pasarela se rechaza, y en otra pasarela es otro movimiento')]
    public function test_administracion_e20_una_fila_por_orden(): void
    {
        $this->assertContains(['pasarela', 'id_pasarela_orden'], $this->unicos('movimientos_pasarela'));

        $this->insertarMovimiento(['id_pasarela_orden' => 'orden-de-prueba-1']);
        $this->assertRechazada(fn () => $this->insertarMovimiento(['id_pasarela_orden' => 'orden-de-prueba-1']), self::UNICO_REPETIDO);
        $this->insertarMovimiento(['id_pasarela_orden' => 'orden-de-prueba-1', 'pasarela' => 'conekta']);

        $this->assertSame(2, DB::table('movimientos_pasarela')->count());
    }

    #[TestDox('administracion-E20: la pasarela, la orden, el monto y la fecha del pago son no nulos y la comisión es opcional; la pasarela es conekta o la simulada, el monto es mayor que cero y la comisión no es negativa')]
    public function test_administracion_e20_lo_obligatorio_y_los_check_de_movimientos_pasarela(): void
    {
        foreach (['pasarela', 'id_pasarela_orden', 'monto', 'pagado_en'] as $columna) {
            $this->assertFalse($this->admiteVacio('movimientos_pasarela', $columna), "{$columna} es no nula.");
            $this->assertRechazada(fn () => $this->insertarMovimiento([$columna => null]), self::VALOR_VACIO);
        }

        $this->assertTrue($this->admiteVacio('movimientos_pasarela', 'comision'));

        $this->assertRechazada(fn () => $this->insertarMovimiento(['pasarela' => 'otra']), self::CHECK_INCUMPLIDO);
        $this->assertRechazada(fn () => $this->insertarMovimiento(['monto' => '0.00']), self::CHECK_INCUMPLIDO);
        $this->assertRechazada(fn () => $this->insertarMovimiento(['monto' => '-1.00']), self::CHECK_INCUMPLIDO);
        $this->assertRechazada(fn () => $this->insertarMovimiento(['comision' => '-0.01']), self::CHECK_INCUMPLIDO);

        $this->insertarMovimiento(['comision' => null]);
        $this->insertarMovimiento(['comision' => '0.00']);
        $this->insertarMovimiento(['comision' => '4.35', 'pasarela' => 'conekta']);
        $this->assertSame(3, DB::table('movimientos_pasarela')->count());
    }

    #[TestDox('modelo-E11: movimientos_pasarela no lleva el trigger impedir_cambio(): leer otra vez la orden actualiza su fila')]
    public function test_modelo_e11_movimientos_pasarela_no_lleva_el_trigger(): void
    {
        $this->assertSame(0, DB::table('information_schema.triggers')
            ->where('event_object_schema', 'public')
            ->where('event_object_table', 'movimientos_pasarela')
            ->count());
    }

    #[TestDox('modelo-E16: MovimientoPasarela guarda el monto y la comisión con dos decimales y la fecha del pago como instante')]
    public function test_modelo_e16_movimiento_pasarela_lleva_sus_tipos(): void
    {
        $movimiento = MovimientoPasarela::create([
            'pasarela' => Pago::PASARELA_SIMULADA,
            'id_pasarela_orden' => 'orden-de-prueba',
            'monto' => '180',
            'comision' => '6.3',
            'pagado_en' => '2027-03-05 16:00:00+00',
        ])->fresh();

        $this->assertSame('180.00', $movimiento->monto);
        $this->assertSame('6.30', $movimiento->comision);
        $this->assertSame('2027-03-05T16:00:00Z', $movimiento->pagado_en->toIso8601ZuluString());
    }

    /**
     * La comisión global, sin plaza ni lavandería, vigente desde el 1 de enero de 2026, con las columnas que se pasan.
     *
     * @param  array<string, mixed>  $datos
     */
    private function insertarComision(array $datos = []): int
    {
        return DB::table('comisiones')->insertGetId([
            'id_plazas' => null,
            'id_lavanderias' => null,
            'porcentaje' => '10.00',
            'vigente_desde' => '2026-01-01 00:00:00+00',
            'vigente_hasta' => null,
            'created_at' => now(),
            'updated_at' => now(),
            ...$datos,
        ], 'id_comisiones');
    }

    /**
     * La comisión propia de la lavandería, con su plaza, vigente desde el 1 de enero de 2026, con las columnas que se
     * pasan.
     *
     * @param  array<string, mixed>  $datos
     */
    private function insertarPropia(Lavanderia $lavanderia, array $datos = []): int
    {
        return $this->insertarComision([
            'id_plazas' => $lavanderia->id_plazas,
            'id_lavanderias' => $lavanderia->getKey(),
            ...$datos,
        ]);
    }

    /**
     * Un aviso global enviado por una cuenta de administración nueva, con las columnas que se pasan.
     *
     * @param  array<string, mixed>  $datos
     */
    private function insertarAviso(array $datos = []): int
    {
        return DB::table('avisos_masivos')->insertGetId([
            'id_plazas' => null,
            'segmento' => null,
            'titulo' => 'Aviso de prueba',
            'mensaje' => 'Texto de prueba.',
            'id_usuarios' => array_key_exists('id_usuarios', $datos) ? $datos['id_usuarios'] : $this->administracion()->getKey(),
            'enviado_en' => now(),
            'destinatarios' => null,
            'created_at' => now(),
            'updated_at' => now(),
            ...$datos,
        ], 'id_avisos_masivos');
    }

    /**
     * Un pago de la pasarela simulada, de 180 pesos, con las columnas que se pasan.
     *
     * @param  array<string, mixed>  $datos
     */
    private function insertarMovimiento(array $datos = []): int
    {
        return DB::table('movimientos_pasarela')->insertGetId([
            'pasarela' => Pago::PASARELA_SIMULADA,
            'id_pasarela_orden' => 'orden-'.Str::lower((string) Str::ulid()),
            'monto' => '180.00',
            'comision' => null,
            'pagado_en' => now(),
            'created_at' => now(),
            'updated_at' => now(),
            ...$datos,
        ], 'id_movimientos_pasarela');
    }

    /**
     * Las columnas de cada índice único de la tabla.
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

    private function administracion(): Usuario
    {
        return Usuario::factory()->conRol(Rol::ADMINISTRACION)->create(['correo' => 'admin-'.Str::lower((string) Str::ulid()).'@ejemplo.com']);
    }

    /**
     * El tipo de la columna como lo escribe la base: text, timestamp(0) with time zone.
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
        return array_column(Schema::getColumns($tabla), 'nullable', 'name')[$columna];
    }

    /**
     * Las llaves foráneas: columnas, tabla padre, columnas padre y qué hacen al borrar.
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
