<?php

namespace Tests\Feature\PerfilLavanderia;

use App\Esquema\RevisorDeEsquema;
use App\Esquema\RevisorDeModelos;
use App\Esquema\Violacion;
use App\Models\Calificacion;
use App\Models\Lavanderia;
use App\Models\LavanderiaFoto;
use App\Models\LavanderiaHorario;
use App\Models\LavanderiaServicio;
use App\Models\Plaza;
use App\Models\TipoServicio;
use App\Models\UnidadMedida;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * Las siete tablas de la lavandería: los catálogos tipos_servicio y unidades_medida, lavanderias, lavanderia_servicios,
 * lavanderia_horarios, lavanderia_fotos y la base de calificaciones, con sus modelos de Eloquent
 * (openspec/changes/perfil-de-lavanderia/design.md, "Tablas y migración"; vault/arquitectura/modelo-de-datos.md;
 * ADR-0016).
 *
 * Las filas se insertan con el constructor de consultas, sin pasar por los modelos, cuando el escenario pide que la
 * base rechace la fila "aunque el código no lo haya validado".
 *
 * Escenarios: perfil-lavanderia-E14 a perfil-lavanderia-E17, y modelo-E01 a modelo-E17 sobre las tablas nuevas ·
 * Spec: openspec/changes/perfil-de-lavanderia/specs/perfil-lavanderia/spec.md
 */
class MigracionTest extends TestCase
{
    use PlazasDePrueba;
    use RefreshDatabase;

    private const CATALOGOS = ['tipos_servicio', 'unidades_medida'];

    private const DE_OPERACION = [
        'lavanderias',
        'lavanderia_servicios',
        'lavanderia_horarios',
        'lavanderia_fotos',
        'calificaciones',
    ];

    /** Las hijas de lavanderias: llevan las dos llaves, a plazas y la compuesta a su lavandería (DD-134, punto 4). */
    private const HIJAS = ['lavanderia_servicios', 'lavanderia_horarios', 'lavanderia_fotos', 'calificaciones'];

    /** SQLSTATE de PostgreSQL cuando una llave foránea no encuentra su fila padre, o su padre tiene hijas. */
    private const LLAVE_FORANEA_ROTA = '23503';

    /** SQLSTATE de PostgreSQL cuando se repite un valor único. */
    private const UNICO_REPETIDO = '23505';

    /** SQLSTATE de PostgreSQL cuando una fila no cumple un CHECK. */
    private const CHECK_INCUMPLIDO = '23514';

    #[TestDox('modelo-E01 a modelo-E17: las siete tablas de la lavandería existen y cumplen la prueba de esquema')]
    public function test_modelo_e01_a_e17_las_siete_tablas_de_la_lavanderia_existen_y_cumplen_la_prueba_de_esquema(): void
    {
        $tablas = [...self::CATALOGOS, ...self::DE_OPERACION];

        foreach ($tablas as $tabla) {
            $this->assertTrue(Schema::hasTable($tabla), "Falta la tabla {$tabla}.");
        }

        $violaciones = array_filter(
            (new RevisorDeEsquema)->revisar(),
            fn (Violacion $violacion): bool => in_array($violacion->tabla, $tablas, true),
        );

        $this->assertSame([], array_values(array_map('strval', $violaciones)));
    }

    #[TestDox('modelo-E01 a modelo-E17: las columnas son las del modelo de datos, en su orden')]
    public function test_modelo_e01_a_e17_las_columnas_son_las_del_modelo_de_datos_en_su_orden(): void
    {
        $this->assertSame(
            ['id_tipos_servicio', 'codigo', 'nombre', 'activo', 'created_at', 'updated_at'],
            Schema::getColumnListing('tipos_servicio'),
        );
        $this->assertSame(
            ['id_unidades_medida', 'codigo', 'nombre', 'created_at', 'updated_at'],
            Schema::getColumnListing('unidades_medida'),
        );
        $this->assertSame(
            [
                'id_lavanderias', 'id_publico', 'id_plazas', 'id_usuarios', 'nombre', 'descripcion', 'telefono',
                'calle', 'numero', 'colonia', 'codigo_postal', 'ubicacion', 'estado', 'aprobada_en',
                'created_at', 'updated_at', 'deleted_at',
                // La agrega alta-de-lavanderias, al final (tests/Feature/AltaLavanderias/MigracionTest.php).
                'id_municipios',
            ],
            Schema::getColumnListing('lavanderias'),
        );
        $this->assertSame(
            [
                'id_lavanderia_servicios', 'id_lavanderias', 'id_plazas', 'id_tipos_servicio', 'id_unidades_medida',
                'precio', 'activo', 'created_at', 'updated_at',
            ],
            Schema::getColumnListing('lavanderia_servicios'),
        );
        $this->assertSame(
            [
                'id_lavanderia_horarios', 'id_lavanderias', 'id_plazas', 'dia_semana', 'hora_inicio', 'hora_fin',
                'created_at', 'updated_at',
            ],
            Schema::getColumnListing('lavanderia_horarios'),
        );
        $this->assertSame(
            ['id_lavanderia_fotos', 'id_lavanderias', 'id_plazas', 'ruta', 'orden', 'created_at', 'updated_at'],
            Schema::getColumnListing('lavanderia_fotos'),
        );
        $this->assertSame(
            [
                'id_calificaciones', 'id_pedidos', 'id_plazas', 'id_usuarios', 'id_lavanderias', 'id_repartidores',
                'estrellas', 'comentario', 'created_at', 'updated_at',
            ],
            Schema::getColumnListing('calificaciones'),
        );
    }

    #[TestDox('modelo-E07 y modelo-E08: el punto, el precio, las horas del día y los instantes llevan su tipo')]
    public function test_modelo_e07_y_e08_el_punto_el_precio_las_horas_del_dia_y_los_instantes_llevan_su_tipo(): void
    {
        $this->assertSame('geography(Point,4326)', $this->tipo('lavanderias', 'ubicacion'));
        $this->assertSame('numeric(12,2)', $this->tipo('lavanderia_servicios', 'precio'));
        $this->assertSame('time(0) without time zone', $this->tipo('lavanderia_horarios', 'hora_inicio'));
        $this->assertSame('time(0) without time zone', $this->tipo('lavanderia_horarios', 'hora_fin'));
        $this->assertSame('smallint', $this->tipo('lavanderia_horarios', 'dia_semana'));
        $this->assertSame('smallint', $this->tipo('calificaciones', 'estrellas'));
        $this->assertSame('character(26)', $this->tipo('lavanderias', 'id_publico'));

        foreach (['aprobada_en', 'deleted_at'] as $columna) {
            $this->assertSame('timestamp(0) with time zone', $this->tipo('lavanderias', $columna), $columna);
        }

        foreach ([...self::CATALOGOS, ...self::DE_OPERACION] as $tabla) {
            foreach (['created_at', 'updated_at'] as $columna) {
                $this->assertSame('timestamp(0) with time zone', $this->tipo($tabla, $columna), "{$tabla}.{$columna}");
            }
        }
    }

    #[TestDox('modelo-E05 y plazas-E09: los catálogos no llevan plaza; las cinco de operación la llevan no nula, con llave a plazas')]
    public function test_modelo_e05_y_plazas_e09_los_catalogos_no_llevan_plaza_las_cinco_de_operacion_la_llevan(): void
    {
        foreach (self::CATALOGOS as $catalogo) {
            $this->assertFalse(Schema::hasColumn($catalogo, 'id_plazas'), "{$catalogo} no lleva id_plazas.");
            $this->assertContains($catalogo, config('modelo.sin_plaza'));
        }

        foreach (self::DE_OPERACION as $tabla) {
            $this->assertFalse($this->admiteVacio($tabla, 'id_plazas'), "{$tabla}.id_plazas es no nula.");
            $this->assertContains([['id_plazas'], 'plazas', ['id_plazas'], 'restrict'], $this->llaves($tabla), $tabla);

            // Ninguna está en las listas de excepción: la prueba de esquema las revisa enteras.
            foreach (['del_framework', 'sin_plaza', 'plaza_opcional', 'de_enlace'] as $lista) {
                $this->assertNotContains($tabla, config("modelo.{$lista}"), "{$tabla} en {$lista}");
            }
        }
    }

    #[TestDox('modelo-E06: cada hija lleva además la llave compuesta con la plaza hacia su lavandería')]
    public function test_modelo_e06_cada_hija_lleva_ademas_la_llave_compuesta_con_la_plaza_hacia_su_lavanderia(): void
    {
        $this->assertContains(['id_lavanderias', 'id_plazas'], $this->unicos('lavanderias'));

        foreach (self::HIJAS as $hija) {
            $this->assertContains(
                [['id_lavanderias', 'id_plazas'], 'lavanderias', ['id_lavanderias', 'id_plazas'], 'restrict'],
                $this->llaves($hija),
                $hija,
            );
        }

        $this->assertContains(
            [['id_tipos_servicio'], 'tipos_servicio', ['id_tipos_servicio'], 'restrict'],
            $this->llaves('lavanderia_servicios'),
        );
        $this->assertContains(
            [['id_unidades_medida'], 'unidades_medida', ['id_unidades_medida'], 'restrict'],
            $this->llaves('lavanderia_servicios'),
        );
        $this->assertContains([['id_usuarios'], 'usuarios', ['id_usuarios'], 'restrict'], $this->llaves('lavanderias'));
        $this->assertContains([['id_usuarios'], 'usuarios', ['id_usuarios'], 'restrict'], $this->llaves('calificaciones'));
    }

    #[TestDox('modelo-E06: la base rechaza un servicio, una foto o una calificación con otra plaza que su lavandería')]
    public function test_modelo_e06_la_base_rechaza_un_servicio_una_foto_o_una_calificacion_con_otra_plaza(): void
    {
        $plaza = $this->insertarPlaza('Plaza de prueba 1');
        $otra = $this->insertarPlaza('Plaza de prueba 2');
        $lavanderia = $this->insertarLavanderia($plaza);

        $this->assertRechazada(fn () => $this->insertarServicio($lavanderia, $otra), self::LLAVE_FORANEA_ROTA);
        $this->assertRechazada(fn () => $this->insertarFoto($lavanderia, $otra), self::LLAVE_FORANEA_ROTA);
        $this->assertRechazada(
            fn () => $this->insertarCalificacion(['id_lavanderias' => $lavanderia, 'id_plazas' => $otra]),
            self::LLAVE_FORANEA_ROTA,
        );

        // Con la plaza de su lavandería, las tres entran.
        $this->insertarServicio($lavanderia, $plaza);
        $this->insertarFoto($lavanderia, $plaza);
        $this->insertarCalificacion(['id_lavanderias' => $lavanderia, 'id_plazas' => $plaza]);

        $this->assertSame(1, DB::table('lavanderia_servicios')->count());
        $this->assertSame(1, DB::table('lavanderia_fotos')->count());
        $this->assertSame(1, DB::table('calificaciones')->count());
    }

    #[TestDox('modelo-E07: la ubicación de la lavandería tiene índice GiST')]
    public function test_modelo_e07_la_ubicacion_de_la_lavanderia_tiene_indice_gist(): void
    {
        $indices = array_filter(
            Schema::getIndexes('lavanderias'),
            fn (array $indice): bool => $indice['columns'] === ['ubicacion'],
        );

        $this->assertSame(['gist'], array_values(array_map(fn (array $indice): string => $indice['type'], $indices)));
    }

    #[TestDox('modelo-E07: la migración siembra los tres tipos de servicio del contrato y la unidad kilo')]
    public function test_modelo_e07_la_migracion_siembra_los_tres_tipos_de_servicio_del_contrato_y_la_unidad_kilo(): void
    {
        $this->assertSame(
            [
                ['codigo' => 'estandar', 'nombre' => 'Lavado estándar', 'activo' => true],
                ['codigo' => 'planchado', 'nombre' => 'Lavado con planchado', 'activo' => true],
                ['codigo' => 'seco', 'nombre' => 'Lavado en seco', 'activo' => true],
            ],
            DB::table('tipos_servicio')->orderBy('id_tipos_servicio')->get(['codigo', 'nombre', 'activo'])
                ->map(fn (object $fila): array => (array) $fila)->all(),
        );

        $this->assertSame(
            [['codigo' => 'kg', 'nombre' => 'kilo']],
            DB::table('unidades_medida')->get(['codigo', 'nombre'])->map(fn (object $fila): array => (array) $fila)->all(),
        );
    }

    #[TestDox('modelo-E07: el código es único en los dos catálogos (C-235)')]
    public function test_modelo_e07_el_codigo_es_unico_en_los_dos_catalogos(): void
    {
        $this->assertRechazada(fn () => DB::table('tipos_servicio')->insert([
            'codigo' => 'estandar',
            'nombre' => 'Otro lavado estándar',
            'activo' => true,
        ]), self::UNICO_REPETIDO);

        $this->assertRechazada(fn () => DB::table('unidades_medida')->insert([
            'codigo' => 'kg',
            'nombre' => 'Otro kilo',
        ]), self::UNICO_REPETIDO);

        $this->assertSame(3, DB::table('tipos_servicio')->count());
        $this->assertSame(1, DB::table('unidades_medida')->count());
    }

    #[TestDox('modelo-E07: la base solo acepta los cuatro estados de la lavandería')]
    public function test_modelo_e07_la_base_solo_acepta_los_cuatro_estados_de_la_lavanderia(): void
    {
        $plaza = $this->insertarPlaza('Plaza de prueba 1');

        foreach (['pendiente', 'activa', 'suspendida', 'baja'] as $estado) {
            $this->insertarLavanderia($plaza, ['estado' => $estado]);
        }

        $this->assertRechazada(fn () => $this->insertarLavanderia($plaza, ['estado' => 'cerrada']), self::CHECK_INCUMPLIDO);
        $this->assertSame(4, DB::table('lavanderias')->count());
    }

    #[TestDox('modelo-E07: un tipo de servicio va una vez por lavandería, y su precio no es negativo')]
    public function test_modelo_e07_un_tipo_de_servicio_va_una_vez_por_lavanderia_y_su_precio_no_es_negativo(): void
    {
        $plaza = $this->insertarPlaza('Plaza de prueba 1');
        $lavanderia = $this->insertarLavanderia($plaza);
        $otra = $this->insertarLavanderia($plaza);

        $this->insertarServicio($lavanderia, $plaza, precio: '0.00');
        $this->assertRechazada(fn () => $this->insertarServicio($lavanderia, $plaza), self::UNICO_REPETIDO);

        // El mismo tipo en otra lavandería sí entra; un precio negativo, nunca.
        $this->insertarServicio($otra, $plaza);
        $this->assertRechazada(
            fn () => $this->insertarServicio($otra, $plaza, tipo: 'planchado', precio: '-1.00'),
            self::CHECK_INCUMPLIDO,
        );

        $this->assertSame(2, DB::table('lavanderia_servicios')->count());
    }

    #[TestDox('modelo-E07: una calificación por pedido y calificado')]
    public function test_modelo_e07_una_calificacion_por_pedido_y_calificado(): void
    {
        $plaza = $this->insertarPlaza('Plaza de prueba 1');
        $lavanderia = $this->insertarLavanderia($plaza);
        // Un pedido que existe en la plaza: la llave de id_pedidos la agregó historial-y-calificacion.
        $pedido = $this->insertarPedido($lavanderia, $plaza);

        $this->insertarCalificacion(['id_pedidos' => $pedido, 'id_lavanderias' => $lavanderia, 'id_plazas' => $plaza]);
        $this->assertRechazada(
            fn () => $this->insertarCalificacion(['id_pedidos' => $pedido, 'id_lavanderias' => $lavanderia, 'id_plazas' => $plaza]),
            self::UNICO_REPETIDO,
        );

        $repartidor = $this->insertarRepartidor($plaza);
        $this->insertarCalificacion(['id_pedidos' => $pedido, 'id_repartidores' => $repartidor, 'id_plazas' => $plaza]);
        $this->assertRechazada(
            fn () => $this->insertarCalificacion(['id_pedidos' => $pedido, 'id_repartidores' => $repartidor, 'id_plazas' => $plaza]),
            self::UNICO_REPETIDO,
        );

        // Sin pedido todavía, como las de prueba de este hito: no chocan entre sí.
        $this->insertarCalificacion(['id_lavanderias' => $lavanderia, 'id_plazas' => $plaza]);
        $this->insertarCalificacion(['id_lavanderias' => $lavanderia, 'id_plazas' => $plaza]);

        $this->assertSame(4, DB::table('calificaciones')->count());
    }

    #[TestDox('modelo-E09: la base no borra una lavandería que tiene servicios, horarios, fotos o calificaciones')]
    public function test_modelo_e09_la_base_no_borra_una_lavanderia_que_tiene_hijas(): void
    {
        $plaza = $this->insertarPlaza('Plaza de prueba 1');

        foreach (['servicio', 'horario', 'foto', 'calificación'] as $hija) {
            $lavanderia = $this->insertarLavanderia($plaza);

            match ($hija) {
                'servicio' => $this->insertarServicio($lavanderia, $plaza),
                'horario' => $this->insertarHorario($lavanderia, $plaza),
                'foto' => $this->insertarFoto($lavanderia, $plaza),
                'calificación' => $this->insertarCalificacion(['id_lavanderias' => $lavanderia, 'id_plazas' => $plaza]),
            };

            $this->assertRechazada(
                fn () => DB::table('lavanderias')->where('id_lavanderias', $lavanderia)->delete(),
                self::LLAVE_FORANEA_ROTA,
            );
        }

        $this->assertSame(4, DB::table('lavanderias')->count());
    }

    #[TestDox('modelo-E11 y modelo-E12: ninguna de las siete tablas lleva el trigger del historial')]
    public function test_modelo_e11_y_e12_ninguna_de_las_siete_tablas_lleva_el_trigger_del_historial(): void
    {
        foreach ([...self::CATALOGOS, ...self::DE_OPERACION] as $tabla) {
            $this->assertTrue(Schema::hasTable($tabla), "Falta la tabla {$tabla}.");

            $triggers = DB::scalar(
                'select count(*) from pg_catalog.pg_trigger where tgrelid = to_regclass(?) and not tgisinternal',
                [$tabla],
            );

            $this->assertSame(0, $triggers, $tabla);
        }
    }

    #[TestDox('perfil-lavanderia-E14: el horario de prueba queda guardado por día, ligado a la lavandería y a su misma plaza')]
    public function test_perfil_lavanderia_e14_el_horario_de_prueba_queda_guardado_por_dia_con_la_plaza_de_su_lavanderia(): void
    {
        $plaza = $this->plazaDePrueba('Plaza de prueba 1');
        $otra = $this->plazaDePrueba('Plaza de prueba 2');
        $lavanderia = Lavanderia::factory()->en($plaza)->create();

        // El lunes, día 1 (ISO 8601), con dos ventanas.
        $manana = $lavanderia->horarios()->create(['dia_semana' => 1, 'hora_inicio' => '08:00', 'hora_fin' => '14:00']);
        $lavanderia->horarios()->create(['dia_semana' => 1, 'hora_inicio' => '16:00', 'hora_fin' => '20:00']);

        $fila = DB::table('lavanderia_horarios')->where('id_lavanderia_horarios', $manana->getKey())->first();
        $this->assertSame($lavanderia->getKey(), $fila->id_lavanderias);
        $this->assertSame($plaza->getKey(), $fila->id_plazas);
        $this->assertSame(1, $fila->dia_semana);
        $this->assertSame(['08:00:00', '14:00:00'], [$fila->hora_inicio, $fila->hora_fin]);
        $this->assertSame(2, $lavanderia->horarios()->where('dia_semana', 1)->count());

        // Un horario de esa lavandería con otra plaza se rechaza.
        $this->assertRechazada(fn () => $lavanderia->horarios()->create([
            'id_plazas' => $otra->getKey(),
            'dia_semana' => 2,
            'hora_inicio' => '08:00',
            'hora_fin' => '14:00',
        ]), self::LLAVE_FORANEA_ROTA);

        $this->assertSame(2, DB::table('lavanderia_horarios')->count());
    }

    #[TestDox('perfil-lavanderia-E15: la base rechaza un horario que termina antes de empezar o con un día fuera del 1 al 7')]
    public function test_perfil_lavanderia_e15_la_base_rechaza_un_horario_imposible(): void
    {
        $plaza = $this->insertarPlaza('Plaza de prueba 1');
        $lavanderia = $this->insertarLavanderia($plaza);

        // Termina a la misma hora en que empieza, o antes.
        $this->assertRechazada(
            fn () => $this->insertarHorario($lavanderia, $plaza, inicio: '09:00', fin: '09:00'),
            self::CHECK_INCUMPLIDO,
        );
        $this->assertRechazada(
            fn () => $this->insertarHorario($lavanderia, $plaza, inicio: '18:00', fin: '09:00'),
            self::CHECK_INCUMPLIDO,
        );

        // Un día fuera del 1 al 7.
        $this->assertRechazada(fn () => $this->insertarHorario($lavanderia, $plaza, dia: 0), self::CHECK_INCUMPLIDO);
        $this->assertRechazada(fn () => $this->insertarHorario($lavanderia, $plaza, dia: 8), self::CHECK_INCUMPLIDO);

        // Los extremos del rango sí entran: el lunes y el domingo.
        $this->insertarHorario($lavanderia, $plaza, dia: 1);
        $this->insertarHorario($lavanderia, $plaza, dia: 7);

        $this->assertSame([1, 7], DB::table('lavanderia_horarios')->orderBy('dia_semana')->pluck('dia_semana')->all());
    }

    #[TestDox('perfil-lavanderia-E16: sin calificado, o con la lavandería y el repartidor a la vez, la calificación no se guarda')]
    public function test_perfil_lavanderia_e16_sin_calificado_o_con_los_dos_la_calificacion_no_se_guarda(): void
    {
        $plaza = $this->insertarPlaza('Plaza de prueba 1');
        $lavanderia = $this->insertarLavanderia($plaza);

        $this->assertRechazada(fn () => $this->insertarCalificacion(['id_plazas' => $plaza]), self::CHECK_INCUMPLIDO);

        // Un repartidor que existe en la plaza: la llave de id_repartidores la agregó el cambio repartidor.
        $repartidor = $this->insertarRepartidor($plaza);
        $this->assertRechazada(fn () => $this->insertarCalificacion([
            'id_plazas' => $plaza,
            'id_lavanderias' => $lavanderia,
            'id_repartidores' => $repartidor,
        ]), self::CHECK_INCUMPLIDO);

        // Con exactamente uno, entra.
        $this->insertarCalificacion(['id_plazas' => $plaza, 'id_lavanderias' => $lavanderia]);
        $this->insertarCalificacion(['id_plazas' => $plaza, 'id_repartidores' => $repartidor]);

        $this->assertSame(2, DB::table('calificaciones')->count());
    }

    #[TestDox('perfil-lavanderia-E17: las estrellas van de 1 a 5')]
    public function test_perfil_lavanderia_e17_las_estrellas_van_de_1_a_5(): void
    {
        $plaza = $this->insertarPlaza('Plaza de prueba 1');
        $lavanderia = $this->insertarLavanderia($plaza);
        $calificada = ['id_plazas' => $plaza, 'id_lavanderias' => $lavanderia];

        $this->assertRechazada(fn () => $this->insertarCalificacion([...$calificada, 'estrellas' => 0]), self::CHECK_INCUMPLIDO);
        $this->assertRechazada(fn () => $this->insertarCalificacion([...$calificada, 'estrellas' => 6]), self::CHECK_INCUMPLIDO);

        $this->insertarCalificacion([...$calificada, 'estrellas' => 1]);
        $this->insertarCalificacion([...$calificada, 'estrellas' => 5]);

        $this->assertSame([1, 5], DB::table('calificaciones')->orderBy('estrellas')->pluck('estrellas')->all());
    }

    #[TestDox('modelo-E16: los siete modelos declaran su tabla y su llave igual que la base')]
    public function test_modelo_e16_los_siete_modelos_declaran_su_tabla_y_su_llave_igual_que_la_base(): void
    {
        $modelos = [
            TipoServicio::class => ['tipos_servicio', 'id_tipos_servicio'],
            UnidadMedida::class => ['unidades_medida', 'id_unidades_medida'],
            Lavanderia::class => ['lavanderias', 'id_lavanderias'],
            LavanderiaServicio::class => ['lavanderia_servicios', 'id_lavanderia_servicios'],
            LavanderiaHorario::class => ['lavanderia_horarios', 'id_lavanderia_horarios'],
            LavanderiaFoto::class => ['lavanderia_fotos', 'id_lavanderia_fotos'],
            Calificacion::class => ['calificaciones', 'id_calificaciones'],
        ];

        foreach ($modelos as $clase => $esperado) {
            $this->assertSame($esperado, [(new $clase)->getTable(), (new $clase)->getKeyName()], $clase);
        }

        $this->assertSame([], array_map('strval', (new RevisorDeModelos)->revisar(array_keys($modelos))));
    }

    #[TestDox('modelo-E15: la lavandería recibe su identificador público ULID y se borra en suave')]
    public function test_modelo_e15_la_lavanderia_recibe_su_identificador_publico_ulid_y_se_borra_en_suave(): void
    {
        $lavanderia = Lavanderia::factory()->en($this->plazaDePrueba())->create();

        $this->assertTrue(Str::isUlid($lavanderia->id_publico));
        $this->assertSame('id_publico', $lavanderia->getRouteKeyName());
        $this->assertContains(SoftDeletes::class, class_uses_recursive(Lavanderia::class));
        // La ubicación es de PostGIS y no sale en JSON.
        $this->assertArrayNotHasKey('ubicacion', $lavanderia->toArray());

        $lavanderia->delete();

        $this->assertNull(Lavanderia::find($lavanderia->getKey()));
        $this->assertNotNull(DB::table('lavanderias')->where('id_lavanderias', $lavanderia->getKey())->value('deleted_at'));
    }

    #[TestDox('modelo-E16: la lavandería guarda su punto y llega a su plaza, a quien la administra y a sus hijas por sus llaves')]
    public function test_modelo_e16_la_lavanderia_llega_a_su_plaza_a_quien_la_administra_y_a_sus_hijas_por_sus_llaves(): void
    {
        $plaza = $this->plazaDePrueba();
        $lavanderia = Lavanderia::factory()->en($plaza, latitud: 10.5, longitud: -119.5)->create();

        $this->assertSame(
            ['latitud' => 10.5, 'longitud' => -119.5],
            array_map('floatval', (array) DB::selectOne(
                'select ST_Y(ubicacion::geometry) as latitud, ST_X(ubicacion::geometry) as longitud
                   from lavanderias where id_lavanderias = ?',
                [$lavanderia->getKey()],
            )),
        );

        $estandar = TipoServicio::where('codigo', TipoServicio::ESTANDAR)->sole();
        $kilo = UnidadMedida::where('codigo', UnidadMedida::KILO)->sole();

        // Las hijas toman la plaza de su lavandería al crearse.
        $servicio = $lavanderia->servicios()->create([
            'id_tipos_servicio' => $estandar->getKey(),
            'id_unidades_medida' => $kilo->getKey(),
            'precio' => '10.00',
        ]);
        $foto = $lavanderia->fotos()->create(['ruta' => 'lavanderias/prueba/1.jpg', 'orden' => 1]);
        $calificacion = $lavanderia->calificaciones()->create([
            'id_usuarios' => Usuario::factory()->create()->getKey(),
            'estrellas' => 4,
        ]);

        foreach ([$servicio, $foto, $calificacion] as $hija) {
            $this->assertSame($plaza->getKey(), $hija->id_plazas, $hija::class);
            $this->assertTrue($lavanderia->is($hija->lavanderia), $hija::class);
        }

        $this->assertTrue($plaza->is($lavanderia->plaza));
        $this->assertTrue($lavanderia->administrador->is(Usuario::find($lavanderia->id_usuarios)));
        $this->assertTrue($estandar->is($servicio->tipo));
        $this->assertTrue($kilo->is($servicio->unidad));
        $this->assertSame('10.00', $servicio->fresh()->precio);
        $this->assertTrue($servicio->fresh()->activo);
        $this->assertSame([$servicio->getKey()], $lavanderia->servicios()->pluck('id_lavanderia_servicios')->all());
        $this->assertSame([$foto->getKey()], $lavanderia->fotos()->pluck('id_lavanderia_fotos')->all());
        $this->assertSame([$calificacion->getKey()], $lavanderia->calificaciones()->pluck('id_calificaciones')->all());

        foreach (['servicios' => 'id_lavanderias', 'horarios' => 'id_lavanderias', 'fotos' => 'id_lavanderias'] as $relacion => $llave) {
            $this->assertSame($llave, $lavanderia->{$relacion}()->getForeignKeyName(), $relacion);
        }
        $this->assertSame('id_plazas', $lavanderia->plaza()->getForeignKeyName());
        $this->assertSame('id_usuarios', $lavanderia->administrador()->getForeignKeyName());
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
     * Un repartidor activo de la plaza, con su cuenta. La llave de calificaciones.id_repartidores hacia repartidores la
     * agregó el cambio repartidor: la calificación del repartidor apunta a uno que existe en su plaza (modelo-E06).
     */
    private function insertarRepartidor(int $plaza): int
    {
        return DB::table('repartidores')->insertGetId([
            'id_publico' => Str::lower((string) Str::ulid()),
            'id_plazas' => $plaza,
            'id_usuarios' => $this->insertarUsuario(),
            'tipo_vehiculo' => 'Motocicleta',
            'estado' => 'activo',
            'aprobado_en' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id_repartidores');
    }

    /**
     * Un pedido de una cuenta nueva a la lavandería, en su plaza, en el estado previo al pago. La llave de
     * calificaciones.id_pedidos hacia pedidos la agregó historial-y-calificacion: la calificación de un pedido apunta a
     * uno que existe en su plaza (modelo-E06).
     */
    private function insertarPedido(int $lavanderia, int $plaza): int
    {
        return DB::table('pedidos')->insertGetId([
            'id_publico' => Str::lower((string) Str::ulid()),
            'id_plazas' => $plaza,
            'id_usuarios' => $this->insertarUsuario(),
            'id_lavanderias' => $lavanderia,
            'id_estados_pedido' => DB::table('estados_pedido')->where('codigo', 'por_pagar')->value('id_estados_pedido'),
            'ventana_inicio' => '2026-12-03 17:00:00+00',
            'ventana_fin' => '2026-12-03 18:00:00+00',
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id_pedidos');
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function insertarLavanderia(int $plaza, array $datos = []): int
    {
        return DB::table('lavanderias')->insertGetId([
            'id_publico' => (string) Str::ulid(),
            'id_plazas' => $plaza,
            'id_usuarios' => $this->insertarUsuario(),
            'nombre' => 'Lavandería de prueba',
            'calle' => 'Calle de prueba',
            'numero' => '1',
            'colonia' => 'Colonia de prueba',
            'codigo_postal' => '00000',
            'ubicacion' => DB::raw("ST_GeogFromText('SRID=4326;POINT(-119.5 10.5)')"),
            'estado' => 'activa',
            'created_at' => now(),
            'updated_at' => now(),
            ...$datos,
        ], 'id_lavanderias');
    }

    private function insertarServicio(int $lavanderia, int $plaza, string $tipo = 'estandar', string $precio = '10.00'): void
    {
        DB::table('lavanderia_servicios')->insert([
            'id_lavanderias' => $lavanderia,
            'id_plazas' => $plaza,
            'id_tipos_servicio' => DB::table('tipos_servicio')->where('codigo', $tipo)->value('id_tipos_servicio'),
            'id_unidades_medida' => DB::table('unidades_medida')->where('codigo', 'kg')->value('id_unidades_medida'),
            'precio' => $precio,
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertarHorario(int $lavanderia, int $plaza, int $dia = 1, string $inicio = '08:00', string $fin = '14:00'): void
    {
        DB::table('lavanderia_horarios')->insert([
            'id_lavanderias' => $lavanderia,
            'id_plazas' => $plaza,
            'dia_semana' => $dia,
            'hora_inicio' => $inicio,
            'hora_fin' => $fin,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertarFoto(int $lavanderia, int $plaza): void
    {
        DB::table('lavanderia_fotos')->insert([
            'id_lavanderias' => $lavanderia,
            'id_plazas' => $plaza,
            'ruta' => 'lavanderias/prueba/1.jpg',
            'orden' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Una calificación de 4 estrellas, de una cuenta nueva, con las columnas que se pasan.
     *
     * @param  array<string, mixed>  $datos
     */
    private function insertarCalificacion(array $datos): void
    {
        DB::table('calificaciones')->insert([
            'id_usuarios' => $this->insertarUsuario(),
            'estrellas' => 4,
            'created_at' => now(),
            'updated_at' => now(),
            ...$datos,
        ]);
    }

    /**
     * El tipo de la columna como lo escribe la base: geography(Point,4326), numeric(12,2), time(0) without time zone.
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
     * Las columnas de cada índice único de la tabla.
     *
     * @return list<list<string>>
     */
    private function unicos(string $tabla): array
    {
        return array_values(array_map(
            fn (array $indice): array => $indice['columns'],
            array_filter(Schema::getIndexes($tabla), fn (array $indice): bool => $indice['unique']),
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
