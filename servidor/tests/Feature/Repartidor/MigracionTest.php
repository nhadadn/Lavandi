<?php

namespace Tests\Feature\Repartidor;

use App\Esquema\RevisorDeEsquema;
use App\Esquema\RevisorDeModelos;
use App\Esquema\Violacion;
use App\Models\EstadoPedido;
use App\Models\Evidencia;
use App\Models\Lavanderia;
use App\Models\Pedido;
use App\Models\PedidoAsignacion;
use App\Models\Plaza;
use App\Models\Repartidor;
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
 * Las tablas repartidores, pedido_asignaciones y evidencias, las llaves de pedidos y calificaciones hacia repartidores,
 * y sus modelos de Eloquent, Repartidor, PedidoAsignacion y Evidencia (openspec/changes/repartidor/design.md, "Tablas y
 * migración"; vault/arquitectura/modelo-de-datos.md; ADR-0016).
 *
 * - repartidores: el socio repartidor, con su plaza, su cuenta, su tipo de vehículo y su estado. La plaza queda vacía
 *   solo mientras el registro está pendiente, con su CHECK (lista "plaza opcional" de config/modelo.php). Sin columnas
 *   de documentos: la verificación documental está excluida (NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3, Módulo C).
 * - pedido_asignaciones: cada oferta de un pedido a un repartidor, con su respuesta. Las dos llaves son compuestas con
 *   la plaza: la base rechaza ofrecer un pedido de una plaza a un repartidor de otra (modelo-E06).
 * - evidencias: la ruta de la foto de evidencia, ligada al pedido y a quien la tomó (ADR-0006).
 *
 * Las filas se insertan con el constructor de consultas, sin pasar por el modelo, para probar lo que hace la base.
 *
 * Escenarios: modelo-E01 a modelo-E17 sobre las tres tablas, y la base de repartidor-E01 a repartidor-E30 · Spec:
 * openspec/changes/repartidor/specs/repartidor/spec.md · Tarea 0.1
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

    private const TABLAS = ['repartidores', 'pedido_asignaciones', 'evidencias'];

    #[TestDox('modelo-E01 a modelo-E17: las tablas repartidores, pedido_asignaciones y evidencias existen y cumplen la prueba de esquema, y sus modelos también')]
    public function test_modelo_e01_a_e17_las_tres_tablas_existen_y_cumplen_la_prueba_de_esquema(): void
    {
        foreach (self::TABLAS as $tabla) {
            $this->assertTrue(Schema::hasTable($tabla), "Falta la tabla {$tabla}.");
        }

        $violaciones = array_filter(
            (new RevisorDeEsquema)->revisar(),
            fn (Violacion $violacion): bool => in_array($violacion->tabla, [...self::TABLAS, 'pedidos', 'calificaciones'], true),
        );

        $this->assertSame([], array_values(array_map('strval', $violaciones)));
        $this->assertSame([], array_map('strval', (new RevisorDeModelos)->revisar([
            Repartidor::class,
            PedidoAsignacion::class,
            Evidencia::class,
        ])));
        $this->assertSame(['repartidores', 'id_repartidores'], [(new Repartidor)->getTable(), (new Repartidor)->getKeyName()]);
        $this->assertSame(
            ['pedido_asignaciones', 'id_pedido_asignaciones'],
            [(new PedidoAsignacion)->getTable(), (new PedidoAsignacion)->getKeyName()],
        );
        $this->assertSame(['evidencias', 'id_evidencias'], [(new Evidencia)->getTable(), (new Evidencia)->getKeyName()]);
    }

    #[TestDox('modelo-E01 a modelo-E17: las columnas son las del modelo de datos, en su orden, con sus fechas de alta y de cambio; repartidores no tiene columnas de documentos')]
    public function test_modelo_e01_a_e17_las_columnas_son_las_del_modelo_de_datos(): void
    {
        $this->assertSame(
            [
                'id_repartidores', 'id_publico', 'id_plazas', 'id_usuarios', 'tipo_vehiculo', 'estado', 'aprobado_en',
                'created_at', 'updated_at', 'deleted_at',
            ],
            Schema::getColumnListing('repartidores'),
        );
        $this->assertSame(
            [
                'id_pedido_asignaciones', 'id_pedidos', 'id_plazas', 'id_repartidores', 'estado', 'motivo', 'respondido_en',
                'created_at', 'updated_at',
            ],
            Schema::getColumnListing('pedido_asignaciones'),
        );
        $this->assertSame(
            ['id_evidencias', 'id_pedidos', 'id_plazas', 'tipo', 'ruta', 'id_usuarios', 'tomada_en', 'created_at', 'updated_at'],
            Schema::getColumnListing('evidencias'),
        );
    }

    #[TestDox('modelo-E08 y modelo-E17: el texto va en text o varchar, y cada instante en timestamptz: aprobado_en, respondido_en, tomada_en y las fechas de alta, de cambio y de baja')]
    public function test_modelo_e08_y_e17_las_columnas_llevan_su_tipo(): void
    {
        $this->assertSame('bigint', $this->tipo('repartidores', 'id_repartidores'));
        $this->assertSame('character(26)', $this->tipo('repartidores', 'id_publico'));
        $this->assertSame('character varying(255)', $this->tipo('repartidores', 'tipo_vehiculo'));
        $this->assertSame('text', $this->tipo('pedido_asignaciones', 'motivo'));
        $this->assertSame('character varying(255)', $this->tipo('evidencias', 'ruta'));

        $instantes = [
            'repartidores' => ['aprobado_en', 'created_at', 'updated_at', 'deleted_at'],
            'pedido_asignaciones' => ['respondido_en', 'created_at', 'updated_at'],
            'evidencias' => ['tomada_en', 'created_at', 'updated_at'],
        ];

        foreach ($instantes as $tabla => $columnas) {
            foreach ($columnas as $columna) {
                $this->assertSame(self::INSTANTE, $this->tipo($tabla, $columna), "{$tabla}.{$columna}");
            }
        }
    }

    #[TestDox('plazas-E09 y repartidor-E05: la plaza del repartidor queda vacía solo mientras está pendiente; activo, suspendido o de baja, la base la exige. La tabla está en la lista de plaza opcional')]
    public function test_plazas_e09_y_repartidor_e05_la_plaza_vacia_solo_en_pendiente(): void
    {
        $this->assertContains('repartidores', config('modelo.plaza_opcional'));
        $this->assertTrue($this->admiteVacio('repartidores', 'id_plazas'));

        $plaza = $this->plazaDePrueba();

        // Pendiente, sin plaza ni alta: el registro de la app.
        $pendiente = $this->insertarRepartidor(['id_plazas' => null, 'estado' => Repartidor::PENDIENTE, 'aprobado_en' => null]);
        $this->assertNull(DB::table('repartidores')->where('id_repartidores', $pendiente)->value('id_plazas'));

        foreach ([Repartidor::ACTIVO, Repartidor::SUSPENDIDO, Repartidor::BAJA] as $estado) {
            $this->assertRechazada(fn () => $this->insertarRepartidor(['id_plazas' => null, 'estado' => $estado]), self::CHECK_INCUMPLIDO);
            $this->insertarRepartidor(['id_plazas' => $plaza->getKey(), 'estado' => $estado]);
        }

        // El alta le pone plaza a la fila pendiente; quitársela fuera de pendiente se rechaza.
        DB::table('repartidores')->where('id_repartidores', $pendiente)
            ->update(['id_plazas' => $plaza->getKey(), 'estado' => Repartidor::ACTIVO, 'aprobado_en' => now()]);
        $this->assertRechazada(
            fn () => DB::table('repartidores')->where('id_repartidores', $pendiente)->update(['id_plazas' => null]),
            self::CHECK_INCUMPLIDO,
        );
    }

    #[TestDox('repartidor-E01 a repartidor-E09: el estado del repartidor es uno de pendiente, activo, suspendido o baja, y nace pendiente; la cuenta, el tipo de vehículo y el estado son no nulos')]
    public function test_repartidor_e01_a_e09_el_estado_y_lo_obligatorio_de_repartidores(): void
    {
        $plaza = $this->plazaDePrueba();

        foreach (['aprobado', 'activa', 'documentos_pendientes', ''] as $estado) {
            $this->assertRechazada(fn () => $this->insertarRepartidor(['id_plazas' => $plaza->getKey(), 'estado' => $estado]), self::CHECK_INCUMPLIDO);
        }

        foreach (['id_usuarios', 'tipo_vehiculo', 'estado'] as $columna) {
            $this->assertFalse($this->admiteVacio('repartidores', $columna), "{$columna} es no nula.");
            $this->assertRechazada(fn () => $this->insertarRepartidor([$columna => null]), self::VALOR_VACIO);
        }

        $sinEstado = DB::table('repartidores')->insertGetId([
            'id_publico' => Str::lower((string) Str::ulid()),
            'id_usuarios' => $this->cuentaDeRepartidor()->getKey(),
            'tipo_vehiculo' => 'Bicicleta',
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id_repartidores');
        $this->assertSame(Repartidor::PENDIENTE, DB::table('repartidores')->where('id_repartidores', $sinEstado)->value('estado'));
    }

    #[TestDox('repartidor-E01: una cuenta es a lo más un repartidor, y el identificador público no se repite')]
    public function test_repartidor_e01_una_cuenta_es_a_lo_mas_un_repartidor(): void
    {
        $cuenta = $this->cuentaDeRepartidor();
        $this->insertarRepartidor(['id_usuarios' => $cuenta->getKey(), 'id_publico' => '01jd8x6m3v2c9q4w5e7r8t0y1z']);

        $this->assertRechazada(fn () => $this->insertarRepartidor(['id_usuarios' => $cuenta->getKey()]), self::UNICO_REPETIDO);
        $this->assertRechazada(fn () => $this->insertarRepartidor(['id_publico' => '01jd8x6m3v2c9q4w5e7r8t0y1z']), self::UNICO_REPETIDO);
    }

    #[TestDox('modelo-E09 y modelo-E10: repartidores lleva sus llaves a la plaza y a la cuenta, ON DELETE RESTRICT, y el único (id_repartidores, id_plazas) para las llaves compuestas de sus hijas')]
    public function test_modelo_e09_y_e10_las_llaves_de_repartidores(): void
    {
        $this->assertEqualsCanonicalizing([
            [['id_plazas'], 'plazas', ['id_plazas'], 'restrict'],
            [['id_usuarios'], 'usuarios', ['id_usuarios'], 'restrict'],
        ], $this->llaves('repartidores'));

        $this->assertContains(['id_repartidores', 'id_plazas'], $this->unicos('repartidores'));

        $plaza = $this->plazaDePrueba();
        $cuenta = $this->cuentaDeRepartidor();
        $this->insertarRepartidor(['id_plazas' => $plaza->getKey(), 'id_usuarios' => $cuenta->getKey()]);

        $this->assertRechazada(fn () => DB::table('usuarios')->where('id_usuarios', $cuenta->getKey())->delete(), self::LLAVE_FORANEA_ROTA);
        $this->assertRechazada(fn () => DB::table('plazas')->where('id_plazas', $plaza->getKey())->delete(), self::LLAVE_FORANEA_ROTA);
        $this->assertRechazada(fn () => $this->insertarRepartidor(['id_plazas' => 999_999]), self::LLAVE_FORANEA_ROTA);
    }

    #[TestDox('repartidor-E10 a repartidor-E18: la asignación es ofrecida, aceptada o rechazada; ofrecida no tiene respuesta, y aceptada o rechazada sí; el rechazo lleva motivo')]
    public function test_repartidor_e10_a_e18_el_estado_y_la_respuesta_de_la_asignacion(): void
    {
        [$pedido, $repartidor] = $this->pedidoYRepartidorEnLaMismaPlaza();
        $ahora = now();

        foreach (['asignado', 'entregado', ''] as $estado) {
            $this->assertRechazada(fn () => $this->insertarAsignacion($pedido, $repartidor, ['estado' => $estado]), self::CHECK_INCUMPLIDO);
        }

        // Ofrecida: sin respuesta. Aceptada o rechazada: con su hora.
        $this->assertRechazada(fn () => $this->insertarAsignacion($pedido, $repartidor, ['respondido_en' => $ahora]), self::CHECK_INCUMPLIDO);
        $this->assertRechazada(fn () => $this->insertarAsignacion($pedido, $repartidor, ['estado' => PedidoAsignacion::ACEPTADO]), self::CHECK_INCUMPLIDO);
        $this->assertRechazada(
            fn () => $this->insertarAsignacion($pedido, $repartidor, ['estado' => PedidoAsignacion::RECHAZADO, 'motivo' => 'Motivo de prueba.']),
            self::CHECK_INCUMPLIDO,
        );

        // El rechazo lleva un motivo que no está vacío (repartidor-E16 y E17).
        foreach ([null, '', '   '] as $motivo) {
            $this->assertRechazada(fn () => $this->insertarAsignacion($pedido, $repartidor, [
                'estado' => PedidoAsignacion::RECHAZADO,
                'motivo' => $motivo,
                'respondido_en' => $ahora,
            ]), self::CHECK_INCUMPLIDO);
        }

        $this->insertarAsignacion($pedido, $repartidor);
        $this->insertarAsignacion($pedido, $repartidor, ['estado' => PedidoAsignacion::ACEPTADO, 'respondido_en' => $ahora]);
        $this->insertarAsignacion($pedido, $repartidor, [
            'estado' => PedidoAsignacion::RECHAZADO,
            'motivo' => 'Motivo de prueba.',
            'respondido_en' => $ahora,
        ]);

        // Sin único: cada intento es una fila (design.md, "Tablas y migración").
        $this->assertSame(3, DB::table('pedido_asignaciones')->where('id_pedidos', $pedido->getKey())->count());

        foreach (['id_pedidos', 'id_plazas', 'id_repartidores', 'estado'] as $columna) {
            $this->assertFalse($this->admiteVacio('pedido_asignaciones', $columna), "{$columna} es no nula.");
        }
    }

    #[TestDox('modelo-E06 y modelo-E09: pedido_asignaciones lleva las llaves compuestas con la plaza hacia pedidos y hacia repartidores, ON DELETE RESTRICT; la base rechaza ofrecer un pedido de una plaza a un repartidor de otra, o a uno pendiente sin plaza')]
    public function test_modelo_e06_y_e09_las_llaves_de_pedido_asignaciones(): void
    {
        $this->assertEqualsCanonicalizing([
            [['id_plazas'], 'plazas', ['id_plazas'], 'restrict'],
            [['id_pedidos', 'id_plazas'], 'pedidos', ['id_pedidos', 'id_plazas'], 'restrict'],
            [['id_repartidores', 'id_plazas'], 'repartidores', ['id_repartidores', 'id_plazas'], 'restrict'],
        ], $this->llaves('pedido_asignaciones'));

        $plazaA = $this->plazaDePrueba('Plaza de prueba A');
        $plazaB = $this->plazaDePrueba('Plaza de prueba B');
        $pedidoDeA = $this->pedidoEn($plazaA);
        $repartidorDeB = Repartidor::factory()->en($plazaB)->create();
        $pendiente = Repartidor::factory()->pendiente()->create();

        $this->assertRechazada(fn () => $this->insertarAsignacion($pedidoDeA, $repartidorDeB), self::LLAVE_FORANEA_ROTA);
        $this->assertRechazada(
            fn () => $this->insertarAsignacion($pedidoDeA, $repartidorDeB, ['id_plazas' => $plazaB->getKey()]),
            self::LLAVE_FORANEA_ROTA,
        );
        $this->assertRechazada(fn () => $this->insertarAsignacion($pedidoDeA, $pendiente), self::LLAVE_FORANEA_ROTA);

        $repartidorDeA = Repartidor::factory()->en($plazaA)->create();
        $this->insertarAsignacion($pedidoDeA, $repartidorDeA);

        // La base no borra el repartidor de una asignación.
        $this->assertRechazada(
            fn () => DB::table('repartidores')->where('id_repartidores', $repartidorDeA->getKey())->delete(),
            self::LLAVE_FORANEA_ROTA,
        );
    }

    #[TestDox('repartidor-E23 y modelo-E06: la evidencia es de entrega, con su ruta, quien la tomó y cuándo; la de recolección se rechaza mientras DD-110 no la pida; lleva la llave compuesta con la plaza hacia el pedido y la de la cuenta, ON DELETE RESTRICT')]
    public function test_repartidor_e23_y_modelo_e06_la_tabla_de_evidencias(): void
    {
        $this->assertEqualsCanonicalizing([
            [['id_plazas'], 'plazas', ['id_plazas'], 'restrict'],
            [['id_pedidos', 'id_plazas'], 'pedidos', ['id_pedidos', 'id_plazas'], 'restrict'],
            [['id_usuarios'], 'usuarios', ['id_usuarios'], 'restrict'],
        ], $this->llaves('evidencias'));

        $plaza = $this->plazaDePrueba();
        $otra = $this->plazaDePrueba('Plaza de prueba 2');
        $pedido = $this->pedidoEn($plaza);
        $quien = $this->cuentaDeRepartidor();

        // La foto al recolectar es la propuesta de DD-110, sin respuesta (repartidor-E24): la base no la admite.
        foreach (['recoleccion', 'firma', 'otro', ''] as $tipo) {
            $this->assertRechazada(fn () => $this->insertarEvidencia($pedido, $quien, ['tipo' => $tipo]), self::CHECK_INCUMPLIDO);
        }

        foreach (['tipo', 'ruta', 'id_usuarios', 'tomada_en', 'id_plazas'] as $columna) {
            $this->assertFalse($this->admiteVacio('evidencias', $columna), "{$columna} es no nula.");
            $this->assertRechazada(fn () => $this->insertarEvidencia($pedido, $quien, [$columna => null]), self::VALOR_VACIO);
        }

        $this->assertRechazada(fn () => $this->insertarEvidencia($pedido, $quien, ['id_plazas' => $otra->getKey()]), self::LLAVE_FORANEA_ROTA);

        $this->insertarEvidencia($pedido, $quien);
        $this->assertSame([Evidencia::ENTREGA], DB::table('evidencias')->where('id_pedidos', $pedido->getKey())->pluck('tipo')->all());
        $this->assertRechazada(fn () => DB::table('usuarios')->where('id_usuarios', $quien->getKey())->delete(), self::LLAVE_FORANEA_ROTA);
    }

    #[TestDox('modelo-E06, modelo-E09 y modelo-E10: pedidos y calificaciones llevan la llave compuesta (id_repartidores, id_plazas) hacia repartidores, ON DELETE RESTRICT y con su índice; la base rechaza un repartidor de otra plaza que la del pedido')]
    public function test_modelo_e06_e09_y_e10_pedidos_y_calificaciones_apuntan_al_repartidor_de_su_plaza(): void
    {
        foreach (['pedidos', 'calificaciones'] as $tabla) {
            $this->assertContains(
                [['id_repartidores', 'id_plazas'], 'repartidores', ['id_repartidores', 'id_plazas'], 'restrict'],
                $this->llaves($tabla),
                $tabla,
            );
            $this->assertTrue($this->indiceQueEmpiezaCon($tabla, ['id_repartidores', 'id_plazas']), "{$tabla} sin índice de la llave.");
        }

        $plazaA = $this->plazaDePrueba('Plaza de prueba A');
        $plazaB = $this->plazaDePrueba('Plaza de prueba B');
        $pedido = $this->pedidoEn($plazaA);
        $deA = Repartidor::factory()->en($plazaA)->create();
        $deB = Repartidor::factory()->en($plazaB)->create();

        $this->assertRechazada(
            fn () => DB::table('pedidos')->where('id_pedidos', $pedido->getKey())->update(['id_repartidores' => $deB->getKey()]),
            self::LLAVE_FORANEA_ROTA,
        );
        DB::table('pedidos')->where('id_pedidos', $pedido->getKey())->update(['id_repartidores' => $deA->getKey()]);
        $this->assertSame($deA->getKey(), $pedido->fresh()->id_repartidores);

        $calificacion = [
            'id_plazas' => $plazaA->getKey(),
            'id_usuarios' => $pedido->id_usuarios,
            'estrellas' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        $this->assertRechazada(fn () => DB::table('calificaciones')->insert([...$calificacion, 'id_repartidores' => $deB->getKey()]), self::LLAVE_FORANEA_ROTA);
        DB::table('calificaciones')->insert([...$calificacion, 'id_repartidores' => $deA->getKey()]);

        // Una calificación de lavandería, sin repartidor, no cambia: con la columna vacía, la llave no se revisa.
        $lavanderia = Lavanderia::factory()->en($plazaA)->create();
        DB::table('calificaciones')->insert([...$calificacion, 'id_lavanderias' => $lavanderia->getKey()]);
        $this->assertSame(2, DB::table('calificaciones')->count());

        $this->assertRechazada(fn () => DB::table('repartidores')->where('id_repartidores', $deA->getKey())->delete(), self::LLAVE_FORANEA_ROTA);
    }

    #[TestDox('modelo-E11 y modelo-E12: ninguna de las tres tablas lleva el trigger impedir_cambio(), que es solo del historial del pedido y del dinero')]
    public function test_modelo_e11_y_e12_ninguna_lleva_el_trigger_del_historial(): void
    {
        $this->assertSame(0, DB::table('information_schema.triggers')
            ->where('event_object_schema', 'public')
            ->whereIn('event_object_table', self::TABLAS)
            ->count());
    }

    #[TestDox('modelo-E15 y modelo-E16: Repartidor recibe su ULID, llega a su plaza y a su cuenta y se borra en suave; PedidoAsignacion y Evidencia toman la plaza de su pedido; el pedido llega a su repartidor, a sus asignaciones y a sus evidencias')]
    public function test_modelo_e15_y_e16_los_modelos_llegan_a_lo_suyo(): void
    {
        $plaza = $this->plazaDePrueba();
        $cuenta = $this->cuentaDeRepartidor();

        $repartidor = Repartidor::create([
            'id_plazas' => $plaza->getKey(),
            'id_usuarios' => $cuenta->getKey(),
            'tipo_vehiculo' => 'Motocicleta',
            'estado' => Repartidor::ACTIVO,
            'aprobado_en' => '2026-12-01 18:00:00+00',
        ])->fresh();

        $this->assertTrue(Str::isUlid($repartidor->id_publico));
        $this->assertSame('id_publico', $repartidor->getRouteKeyName());
        $this->assertTrue($plaza->is($repartidor->plaza));
        $this->assertTrue($cuenta->is($repartidor->cuenta));
        $this->assertSame('2026-12-01T18:00:00Z', $repartidor->aprobado_en->toIso8601ZuluString());

        $pedido = $this->pedidoEn($plaza);
        $asignacion = PedidoAsignacion::create([
            'id_pedidos' => $pedido->getKey(),
            'id_repartidores' => $repartidor->getKey(),
            'estado' => PedidoAsignacion::OFRECIDO,
        ])->fresh();
        $this->assertSame($plaza->getKey(), $asignacion->id_plazas);
        $this->assertTrue($pedido->is($asignacion->pedido));
        $this->assertTrue($repartidor->is($asignacion->repartidor));
        $this->assertNull($asignacion->respondido_en);

        $evidencia = Evidencia::create([
            'id_pedidos' => $pedido->getKey(),
            'tipo' => Evidencia::ENTREGA,
            'ruta' => 'evidencias/prueba.jpg',
            'id_usuarios' => $cuenta->getKey(),
            'tomada_en' => '2026-12-03 18:30:00+00',
        ])->fresh();
        $this->assertSame($plaza->getKey(), $evidencia->id_plazas);
        $this->assertTrue($cuenta->is($evidencia->usuario));
        $this->assertSame('2026-12-03T18:30:00Z', $evidencia->tomada_en->toIso8601ZuluString());
        // La ruta no sale en JSON: la foto se ve solo con su URL firmada (ADR-0006).
        $this->assertArrayNotHasKey('ruta', $evidencia->toArray());

        $pedido->forceFill(['id_repartidores' => $repartidor->getKey()])->save();
        $pedido = $pedido->fresh();
        $this->assertTrue($repartidor->is($pedido->repartidor));
        $this->assertSame([$asignacion->getKey()], $pedido->asignaciones->modelKeys());
        $this->assertSame([$evidencia->getKey()], $pedido->evidencias->modelKeys());
        $this->assertSame([$asignacion->getKey()], $repartidor->asignaciones->modelKeys());

        // Borrado en suave: la fila se queda y el pedido lo sigue encontrando, como historial (repartidor-E07).
        $repartidor->delete();
        $this->assertSame(1, DB::table('repartidores')->where('id_repartidores', $repartidor->getKey())->count());
        $this->assertNull(Repartidor::find($repartidor->getKey()));
        $this->assertTrue($repartidor->is($pedido->fresh()->repartidor));
    }

    private function cuentaDeRepartidor(): Usuario
    {
        return Usuario::factory()->conRol(Rol::REPARTIDOR)->create();
    }

    /**
     * Un pedido de una lavandería nueva de la plaza, de una cuenta de usuario nueva, en el estado previo al pago.
     */
    private function pedidoEn(Plaza $plaza): Pedido
    {
        return Pedido::create([
            'id_plazas' => $plaza->getKey(),
            'id_usuarios' => Usuario::factory()->conRol(Rol::USUARIO)->create()->getKey(),
            'id_lavanderias' => Lavanderia::factory()->en($plaza)->create()->getKey(),
            'id_estados_pedido' => EstadoPedido::query()->where('codigo', EstadoPedido::POR_PAGAR)->value('id_estados_pedido'),
            'ventana_inicio' => '2026-12-03 17:00:00+00',
            'ventana_fin' => '2026-12-03 18:00:00+00',
        ]);
    }

    /**
     * @return array{Pedido, Repartidor}
     */
    private function pedidoYRepartidorEnLaMismaPlaza(): array
    {
        $plaza = $this->plazaDePrueba();

        return [$this->pedidoEn($plaza), Repartidor::factory()->en($plaza)->create()];
    }

    /**
     * Un repartidor activo de una plaza nueva, de una cuenta nueva, con las columnas que se pasan.
     *
     * @param  array<string, mixed>  $datos
     */
    private function insertarRepartidor(array $datos = []): int
    {
        return DB::table('repartidores')->insertGetId([
            'id_publico' => Str::lower((string) Str::ulid()),
            'id_plazas' => array_key_exists('id_plazas', $datos) ? $datos['id_plazas'] : $this->plazaDePrueba('Plaza '.Str::random(6))->getKey(),
            'id_usuarios' => array_key_exists('id_usuarios', $datos) ? $datos['id_usuarios'] : $this->cuentaDeRepartidor()->getKey(),
            'tipo_vehiculo' => 'Motocicleta',
            'estado' => Repartidor::ACTIVO,
            'aprobado_en' => now(),
            'created_at' => now(),
            'updated_at' => now(),
            ...$datos,
        ], 'id_repartidores');
    }

    /**
     * Una asignación ofrecida del pedido al repartidor, en la plaza del pedido, con las columnas que se pasan.
     *
     * @param  array<string, mixed>  $datos
     */
    private function insertarAsignacion(Pedido $pedido, Repartidor $repartidor, array $datos = []): int
    {
        return DB::table('pedido_asignaciones')->insertGetId([
            'id_pedidos' => $pedido->getKey(),
            'id_plazas' => $pedido->id_plazas,
            'id_repartidores' => $repartidor->getKey(),
            'estado' => PedidoAsignacion::OFRECIDO,
            'motivo' => null,
            'respondido_en' => null,
            'created_at' => now(),
            'updated_at' => now(),
            ...$datos,
        ], 'id_pedido_asignaciones');
    }

    /**
     * Una evidencia de entrega del pedido, tomada por esa cuenta, con las columnas que se pasan.
     *
     * @param  array<string, mixed>  $datos
     */
    private function insertarEvidencia(Pedido $pedido, Usuario $quien, array $datos = []): int
    {
        return DB::table('evidencias')->insertGetId([
            'id_pedidos' => $pedido->getKey(),
            'id_plazas' => $pedido->id_plazas,
            'tipo' => Evidencia::ENTREGA,
            'ruta' => 'evidencias/'.Str::random(8).'.jpg',
            'id_usuarios' => $quien->getKey(),
            'tomada_en' => now(),
            'created_at' => now(),
            'updated_at' => now(),
            ...$datos,
        ], 'id_evidencias');
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

    /**
     * @param  list<string>  $columnas
     */
    private function indiceQueEmpiezaCon(string $tabla, array $columnas): bool
    {
        foreach (Schema::getIndexes($tabla) as $indice) {
            if (array_slice($indice['columns'], 0, count($columnas)) === $columnas) {
                return true;
            }
        }

        return false;
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
