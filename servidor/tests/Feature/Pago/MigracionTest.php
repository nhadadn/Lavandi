<?php

namespace Tests\Feature\Pago;

use App\Esquema\Regla;
use App\Esquema\RevisorDeEsquema;
use App\Esquema\RevisorDeModelos;
use App\Esquema\Violacion;
use App\Models\EstadoPedido;
use App\Models\EventoPasarela;
use App\Models\Lavanderia;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Plaza;
use App\Models\Reembolso;
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
 * Las tablas pagos, reembolsos y eventos_pasarela, y sus modelos de Eloquent, Pago, Reembolso y EventoPasarela
 * (openspec/changes/pago/design.md, "Tablas y migración"; vault/arquitectura/modelo-de-datos.md, "Dinero"; ADR-0016).
 *
 * - pagos: el cobro del pedido, con su plaza y la llave compuesta (id_pedidos, id_plazas) hacia pedidos. Ningún dato de
 *   tarjeta: lo único que lo liga con la pasarela es id_pasarela_orden, junto con la columna pasarela (pago-E05). No se
 *   borra nunca (impedir_cambio()).
 * - reembolsos: nace vacía (pago-E15 y pago-E16 están bloqueados), con la llave compuesta (id_pagos, id_plazas) hacia
 *   pagos. Tampoco se borra.
 * - eventos_pasarela: cada aviso de la pasarela, sin plaza, con el único (pasarela, id_pasarela_evento) que impide
 *   procesar dos veces el mismo (pago-E10).
 *
 * Ningún nombre de tabla o de columna nombra a un proveedor (C-192; modelo-E14). Las filas se insertan con el
 * constructor de consultas, sin pasar por el modelo, para probar lo que hace la base.
 *
 * Escenarios: pago-E05, y modelo-E01 a modelo-E17 sobre las tres tablas (sobre todo modelo-E13 y modelo-E14) · Spec:
 * openspec/changes/pago/specs/pago/spec.md · Tarea 1.1
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

    /** SQLSTATE restrict_violation: el que lanza impedir_cambio(). */
    private const CAMBIO_IMPEDIDO = '23001';

    private const INSTANTE = 'timestamp(0) with time zone';

    private const TABLAS = ['pagos', 'reembolsos', 'eventos_pasarela'];

    #[TestDox('modelo-E01 a modelo-E17: las tablas pagos, reembolsos y eventos_pasarela existen y cumplen la prueba de esquema, y sus modelos también')]
    public function test_modelo_e01_a_e17_las_tres_tablas_existen_y_cumplen_la_prueba_de_esquema(): void
    {
        foreach (self::TABLAS as $tabla) {
            $this->assertTrue(Schema::hasTable($tabla), "Falta la tabla {$tabla}.");
        }

        $violaciones = array_filter(
            (new RevisorDeEsquema)->revisar(),
            fn (Violacion $violacion): bool => in_array($violacion->tabla, self::TABLAS, true),
        );

        $this->assertSame([], array_values(array_map('strval', $violaciones)));
        $this->assertSame([], array_map('strval', (new RevisorDeModelos)->revisar([
            Pago::class,
            Reembolso::class,
            EventoPasarela::class,
        ])));
        $this->assertSame(['pagos', 'id_pagos'], [(new Pago)->getTable(), (new Pago)->getKeyName()]);
        $this->assertSame(['reembolsos', 'id_reembolsos'], [(new Reembolso)->getTable(), (new Reembolso)->getKeyName()]);
        $this->assertSame(
            ['eventos_pasarela', 'id_eventos_pasarela'],
            [(new EventoPasarela)->getTable(), (new EventoPasarela)->getKeyName()],
        );
    }

    #[TestDox('modelo-E01 a modelo-E17: las columnas son las del modelo de datos, en su orden, con sus fechas de alta y de cambio')]
    public function test_modelo_e01_a_e17_las_columnas_son_las_del_modelo_de_datos(): void
    {
        $this->assertSame(
            [
                'id_pagos', 'id_publico', 'id_pedidos', 'id_plazas', 'tipo', 'metodo', 'pasarela', 'monto', 'estado',
                'id_pasarela_orden', 'referencia_efectivo', 'vence_en', 'pagado_en', 'created_at', 'updated_at',
            ],
            Schema::getColumnListing('pagos'),
        );
        $this->assertSame(
            [
                'id_reembolsos', 'id_pagos', 'id_plazas', 'monto', 'motivo', 'id_pasarela_reembolso', 'estado',
                'reembolsado_en', 'created_at', 'updated_at',
            ],
            Schema::getColumnListing('reembolsos'),
        );
        $this->assertSame(
            [
                'id_eventos_pasarela', 'pasarela', 'id_pasarela_evento', 'tipo', 'contenido', 'recibido_en',
                'procesado_en', 'error', 'created_at', 'updated_at',
            ],
            Schema::getColumnListing('eventos_pasarela'),
        );
    }

    #[TestDox('modelo-E07, modelo-E08 y modelo-E17: el dinero va en numeric(12,2), el aviso en jsonb, el texto en text o varchar, y cada instante en timestamptz')]
    public function test_modelo_e07_e08_y_e17_las_columnas_llevan_su_tipo(): void
    {
        $this->assertSame('bigint', $this->tipo('pagos', 'id_pagos'));
        $this->assertSame('character(26)', $this->tipo('pagos', 'id_publico'));
        $this->assertSame('numeric(12,2)', $this->tipo('pagos', 'monto'));
        $this->assertSame('numeric(12,2)', $this->tipo('reembolsos', 'monto'));
        $this->assertSame('character varying(255)', $this->tipo('pagos', 'id_pasarela_orden'));
        $this->assertSame('character varying(255)', $this->tipo('pagos', 'referencia_efectivo'));
        $this->assertSame('text', $this->tipo('reembolsos', 'motivo'));
        $this->assertSame('jsonb', $this->tipo('eventos_pasarela', 'contenido'));
        $this->assertSame('text', $this->tipo('eventos_pasarela', 'error'));

        $instantes = [
            'pagos' => ['vence_en', 'pagado_en', 'created_at', 'updated_at'],
            'reembolsos' => ['reembolsado_en', 'created_at', 'updated_at'],
            'eventos_pasarela' => ['recibido_en', 'procesado_en', 'created_at', 'updated_at'],
        ];

        foreach ($instantes as $tabla => $columnas) {
            foreach ($columnas as $columna) {
                $this->assertSame(self::INSTANTE, $this->tipo($tabla, $columna), "{$tabla}.{$columna}");
            }
        }
    }

    #[TestDox('pago-E05: ninguna columna de la base está hecha para un número de tarjeta, una fecha de vencimiento o un código de seguridad, y lo único que liga un pago con la pasarela es su id_pasarela_orden, junto con la columna pasarela')]
    public function test_pago_e05_el_esquema_no_tiene_donde_guardar_una_tarjeta(): void
    {
        $deTarjeta = array_filter(
            (new RevisorDeEsquema)->revisar(),
            fn (Violacion $violacion): bool => $violacion->regla === Regla::DatosDeTarjeta,
        );
        $this->assertSame([], array_values(array_map('strval', $deTarjeta)));

        // Ni una columna de ninguna tabla nombra la tarjeta o su código.
        $columnas = DB::select(
            "select table_name as tabla, column_name as columna
               from information_schema.columns
              where table_schema = 'public'
                and (column_name ~ '(tarjeta|card|cvv|cvc|vencimiento_tarjeta|numero_tarjeta)')",
        );
        $this->assertSame([], $columnas);

        $deLaPasarela = array_values(array_filter(
            Schema::getColumnListing('pagos'),
            fn (string $columna): bool => str_contains($columna, 'pasarela'),
        ));
        $this->assertSame(['pasarela', 'id_pasarela_orden'], $deLaPasarela);
    }

    #[TestDox('modelo-E14: ninguna tabla ni columna del cobro nombra a un proveedor de pagos')]
    public function test_modelo_e14_ninguna_tabla_ni_columna_del_cobro_nombra_a_un_proveedor(): void
    {
        // Sin las tablas, la revisión pasaría en vacío.
        foreach (self::TABLAS as $tabla) {
            $this->assertTrue(Schema::hasTable($tabla), "Falta la tabla {$tabla}.");
        }

        $deProveedor = array_filter(
            (new RevisorDeEsquema)->revisar(),
            fn (Violacion $violacion): bool => $violacion->regla === Regla::ProveedorDePagos,
        );

        $this->assertSame([], array_values(array_map('strval', $deProveedor)));
    }

    #[TestDox('pago-E01 y pago-E14: el cobro es de tipo cobro o ajuste, con tarjeta o en efectivo, en uno de los cinco estados; nace pendiente, y su monto es mayor que cero')]
    public function test_pago_e01_y_e14_el_tipo_el_metodo_el_estado_y_el_monto_del_pago(): void
    {
        $pedido = $this->pedidoEn($this->plazaDePrueba());

        foreach (['tipo' => ['reembolso', 'propina', ''], 'metodo' => ['oxxo', 'spei', ''], 'estado' => ['aprobado', 'reembolsado', '']] as $columna => $valores) {
            foreach ($valores as $valor) {
                $this->assertRechazada(fn () => $this->insertarPago($pedido, [$columna => $valor]), self::CHECK_INCUMPLIDO);
            }
        }

        foreach (['0', '-1.00'] as $monto) {
            $this->assertRechazada(fn () => $this->insertarPago($pedido, ['monto' => $monto]), self::CHECK_INCUMPLIDO);
        }

        foreach (['id_pedidos', 'id_plazas', 'tipo', 'metodo', 'pasarela', 'monto', 'estado'] as $columna) {
            $this->assertFalse($this->admiteVacio('pagos', $columna), "{$columna} es no nula.");
            $this->assertRechazada(fn () => $this->insertarPago($pedido, [$columna => null]), self::VALOR_VACIO);
        }

        foreach (['id_pasarela_orden', 'referencia_efectivo', 'vence_en', 'pagado_en'] as $columna) {
            $this->assertTrue($this->admiteVacio('pagos', $columna), "{$columna} es opcional.");
        }

        $sinEstado = DB::table('pagos')->insertGetId([
            'id_publico' => Str::lower((string) Str::ulid()),
            'id_pedidos' => $pedido->getKey(),
            'id_plazas' => $pedido->id_plazas,
            'tipo' => Pago::COBRO,
            'metodo' => Pago::TARJETA,
            'pasarela' => Pago::PASARELA_SIMULADA,
            'monto' => '180.00',
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id_pagos');
        $this->assertSame(Pago::PENDIENTE, DB::table('pagos')->where('id_pagos', $sinEstado)->value('estado'));

        // Un pedido tiene un cobro y, si DD-012 lo pide, un ajuste.
        $this->insertarPago($pedido, ['tipo' => Pago::AJUSTE, 'monto' => '20.00']);
        $this->assertSame(2, DB::table('pagos')->where('id_pedidos', $pedido->getKey())->count());
    }

    #[TestDox('pago-E03: un pago pagado lleva su pagado_en; sin él, la base lo rechaza')]
    public function test_pago_e03_un_pago_pagado_lleva_su_pagado_en(): void
    {
        $pedido = $this->pedidoEn($this->plazaDePrueba());

        $this->assertRechazada(fn () => $this->insertarPago($pedido, ['estado' => Pago::PAGADO]), self::CHECK_INCUMPLIDO);

        $pagado = $this->insertarPago($pedido, ['estado' => Pago::PAGADO, 'pagado_en' => now()]);
        $this->assertRechazada(
            fn () => DB::table('pagos')->where('id_pagos', $pagado)->update(['pagado_en' => null]),
            self::CHECK_INCUMPLIDO,
        );
    }

    #[TestDox('pago-E01: un pedido tiene un solo cobro; la orden de la pasarela no se repite en la misma pasarela, y el identificador público tampoco')]
    public function test_pago_e01_un_solo_cobro_por_pedido_y_la_orden_no_se_repite(): void
    {
        $plaza = $this->plazaDePrueba();
        $pedido = $this->pedidoEn($plaza);
        $otro = $this->pedidoEn($plaza);

        $this->insertarPago($pedido, [
            'id_pasarela_orden' => 'orden-de-prueba-1',
            'id_publico' => '01jd8x6m3v2c9q4w5e7r8t0y1z',
        ]);

        // Otro cobro del mismo pedido.
        $this->assertRechazada(fn () => $this->insertarPago($pedido), self::UNICO_REPETIDO);

        // La misma orden en la misma pasarela, aunque sea de otro pedido.
        $this->assertRechazada(fn () => $this->insertarPago($otro, ['id_pasarela_orden' => 'orden-de-prueba-1']), self::UNICO_REPETIDO);
        $this->assertRechazada(fn () => $this->insertarPago($otro, ['id_publico' => '01jd8x6m3v2c9q4w5e7r8t0y1z']), self::UNICO_REPETIDO);

        // El mismo identificador en otra pasarela es otra orden; sin orden todavía, no se compara.
        $this->insertarPago($otro, ['id_pasarela_orden' => 'orden-de-prueba-1', 'pasarela' => 'conekta']);
        $this->insertarPago($this->pedidoEn($plaza));
        $this->insertarPago($this->pedidoEn($plaza));
        $this->assertSame(2, DB::table('pagos')->whereNull('id_pasarela_orden')->count());

        $this->assertContains(['pasarela', 'id_pasarela_orden'], $this->unicos('pagos'));
        $this->assertContains(['id_pagos', 'id_plazas'], $this->unicos('pagos'));
    }

    #[TestDox('pago-E01: pasarela es conekta, la del contrato, o la simulada de las pruebas; ningún otro valor entra mientras el cliente no firme el cambio (DD-079)')]
    public function test_pago_e01_los_valores_de_pasarela(): void
    {
        $plaza = $this->plazaDePrueba();

        foreach (['stripe', 'mercado_pago', 'Conekta', ''] as $pasarela) {
            $this->assertRechazada(fn () => $this->insertarPago($this->pedidoEn($plaza), ['pasarela' => $pasarela]), self::CHECK_INCUMPLIDO);
            $this->assertRechazada(fn () => $this->insertarEvento(['pasarela' => $pasarela]), self::CHECK_INCUMPLIDO);
        }

        foreach ([Pago::PASARELA_SIMULADA, 'conekta'] as $pasarela) {
            $this->insertarPago($this->pedidoEn($plaza), ['pasarela' => $pasarela]);
            $this->insertarEvento(['pasarela' => $pasarela]);
        }

        $this->assertSame(2, DB::table('pagos')->count());
        $this->assertSame(2, DB::table('eventos_pasarela')->count());
    }

    #[TestDox('modelo-E06, modelo-E09 y modelo-E10: pagos lleva la llave compuesta con la plaza hacia pedidos y su llave a plazas, ON DELETE RESTRICT; la base rechaza un pago de otra plaza que la del pedido')]
    public function test_modelo_e06_e09_y_e10_las_llaves_de_pagos(): void
    {
        $this->assertEqualsCanonicalizing([
            [['id_plazas'], 'plazas', ['id_plazas'], 'restrict'],
            [['id_pedidos', 'id_plazas'], 'pedidos', ['id_pedidos', 'id_plazas'], 'restrict'],
        ], $this->llaves('pagos'));

        $plaza = $this->plazaDePrueba('Plaza de prueba A');
        $otra = $this->plazaDePrueba('Plaza de prueba B');
        $pedido = $this->pedidoEn($plaza);

        $this->assertRechazada(fn () => $this->insertarPago($pedido, ['id_plazas' => $otra->getKey()]), self::LLAVE_FORANEA_ROTA);
        $this->assertRechazada(fn () => $this->insertarPago($pedido, ['id_pedidos' => 999_999]), self::LLAVE_FORANEA_ROTA);

        $this->insertarPago($pedido);
        $this->assertRechazada(fn () => DB::table('plazas')->where('id_plazas', $plaza->getKey())->delete(), self::LLAVE_FORANEA_ROTA);
    }

    #[TestDox('modelo-E06 y pago-E16: reembolsos lleva la llave compuesta (id_pagos, id_plazas) hacia pagos; su monto es mayor que cero y su estado es pendiente, reembolsado o fallido')]
    public function test_modelo_e06_y_pago_e16_la_tabla_de_reembolsos(): void
    {
        $this->assertEqualsCanonicalizing([
            [['id_plazas'], 'plazas', ['id_plazas'], 'restrict'],
            [['id_pagos', 'id_plazas'], 'pagos', ['id_pagos', 'id_plazas'], 'restrict'],
        ], $this->llaves('reembolsos'));

        $plaza = $this->plazaDePrueba('Plaza de prueba A');
        $otra = $this->plazaDePrueba('Plaza de prueba B');
        $pago = $this->insertarPago($this->pedidoEn($plaza), ['estado' => Pago::PAGADO, 'pagado_en' => now()]);

        $this->assertRechazada(fn () => $this->insertarReembolso($pago, $plaza, ['id_plazas' => $otra->getKey()]), self::LLAVE_FORANEA_ROTA);

        foreach (['0', '-5.00'] as $monto) {
            $this->assertRechazada(fn () => $this->insertarReembolso($pago, $plaza, ['monto' => $monto]), self::CHECK_INCUMPLIDO);
        }

        foreach (['aprobado', 'cancelado', ''] as $estado) {
            $this->assertRechazada(fn () => $this->insertarReembolso($pago, $plaza, ['estado' => $estado]), self::CHECK_INCUMPLIDO);
        }

        foreach (['id_pagos', 'id_plazas', 'monto', 'estado'] as $columna) {
            $this->assertFalse($this->admiteVacio('reembolsos', $columna), "{$columna} es no nula.");
        }

        foreach (['pendiente', 'reembolsado', 'fallido'] as $estado) {
            $this->insertarReembolso($pago, $plaza, ['estado' => $estado]);
        }

        $this->assertSame(3, DB::table('reembolsos')->count());
        $this->assertRechazada(fn () => DB::table('pagos')->where('id_pagos', $pago)->update(['id_plazas' => $otra->getKey()]), self::LLAVE_FORANEA_ROTA);
    }

    #[TestDox('pago-E10 y plazas-E09: eventos_pasarela no lleva plaza, está en la lista sin_plaza, y el único (pasarela, id_pasarela_evento) rechaza el mismo aviso dos veces')]
    public function test_pago_e10_el_mismo_aviso_no_entra_dos_veces(): void
    {
        $this->assertContains('eventos_pasarela', config('modelo.sin_plaza'));
        $this->assertNotContains('id_plazas', Schema::getColumnListing('eventos_pasarela'));
        $this->assertContains(['pasarela', 'id_pasarela_evento'], $this->unicos('eventos_pasarela'));

        $this->insertarEvento(['id_pasarela_evento' => 'evento-de-prueba-1']);
        $this->assertRechazada(fn () => $this->insertarEvento(['id_pasarela_evento' => 'evento-de-prueba-1']), self::UNICO_REPETIDO);

        // El mismo identificador en otra pasarela es otro aviso.
        $this->insertarEvento(['id_pasarela_evento' => 'evento-de-prueba-1', 'pasarela' => 'conekta']);

        foreach (['pagado', 'order.paid', ''] as $tipo) {
            $this->assertRechazada(fn () => $this->insertarEvento(['tipo' => $tipo]), self::CHECK_INCUMPLIDO);
        }

        foreach (['pasarela', 'id_pasarela_evento', 'tipo', 'contenido', 'recibido_en'] as $columna) {
            $this->assertFalse($this->admiteVacio('eventos_pasarela', $columna), "{$columna} es no nula.");
            $this->assertRechazada(fn () => $this->insertarEvento([$columna => null]), self::VALOR_VACIO);
        }

        foreach (['procesado_en', 'error'] as $columna) {
            $this->assertTrue($this->admiteVacio('eventos_pasarela', $columna), "{$columna} es opcional.");
        }
    }

    #[TestDox('modelo-E11: la base rechaza borrar un pago o un reembolso; editar el estado de un pago sí se puede, y eventos_pasarela no lleva el trigger')]
    public function test_modelo_e11_la_base_rechaza_borrar_un_pago_o_un_reembolso(): void
    {
        $plaza = $this->plazaDePrueba();
        $pago = $this->insertarPago($this->pedidoEn($plaza));
        $reembolso = $this->insertarReembolso(
            $this->insertarPago($this->pedidoEn($plaza), ['estado' => Pago::PAGADO, 'pagado_en' => now()]),
            $plaza,
        );

        $this->assertRechazada(fn () => DB::delete('delete from reembolsos where id_reembolsos = ?', [$reembolso]), self::CAMBIO_IMPEDIDO);
        $this->assertRechazada(fn () => DB::delete('delete from pagos where id_pagos = ?', [$pago]), self::CAMBIO_IMPEDIDO);

        DB::table('pagos')->where('id_pagos', $pago)->update(['estado' => Pago::PAGADO, 'pagado_en' => now()]);
        $this->assertSame(Pago::PAGADO, DB::table('pagos')->where('id_pagos', $pago)->value('estado'));

        $disparadores = DB::table('information_schema.triggers')
            ->where('event_object_schema', 'public')
            ->whereIn('event_object_table', self::TABLAS)
            ->get(['event_object_table as tabla', 'event_manipulation as operacion', 'action_timing as momento'])
            ->map(fn (object $fila): string => "{$fila->momento} {$fila->operacion} {$fila->tabla}")
            ->sort()
            ->values()
            ->all();
        $this->assertSame(['BEFORE DELETE pagos', 'BEFORE DELETE reembolsos'], $disparadores);
    }

    #[TestDox('modelo-E15 y modelo-E16: Pago recibe su ULID y toma la plaza de su pedido; el pedido llega a sus pagos, el pago a sus reembolsos, y el aviso guarda su contenido como arreglo')]
    public function test_modelo_e15_y_e16_los_modelos_llegan_a_lo_suyo(): void
    {
        $plaza = $this->plazaDePrueba();
        $pedido = $this->pedidoEn($plaza);

        $pago = Pago::create([
            'id_pedidos' => $pedido->getKey(),
            'tipo' => Pago::COBRO,
            'metodo' => Pago::TARJETA,
            'pasarela' => Pago::PASARELA_SIMULADA,
            'monto' => '180.00',
        ])->fresh();

        $this->assertTrue(Str::isUlid($pago->id_publico));
        $this->assertSame('id_publico', $pago->getRouteKeyName());
        $this->assertSame($plaza->getKey(), $pago->id_plazas);
        $this->assertSame(Pago::PENDIENTE, $pago->estado);
        $this->assertSame('180.00', $pago->monto);
        $this->assertTrue($pedido->is($pago->pedido));
        $this->assertSame([$pago->getKey()], $pedido->pagos->modelKeys());
        $this->assertTrue($pago->is($pedido->cobro));

        $pago->forceFill(['estado' => Pago::PAGADO, 'pagado_en' => '2026-12-03 15:30:00+00'])->save();
        $this->assertSame('2026-12-03T15:30:00Z', $pago->fresh()->pagado_en->toIso8601ZuluString());

        $reembolso = Reembolso::create([
            'id_pagos' => $pago->getKey(),
            'monto' => '50.00',
            'estado' => Reembolso::PENDIENTE,
        ])->fresh();
        $this->assertSame($plaza->getKey(), $reembolso->id_plazas);
        $this->assertTrue($pago->is($reembolso->pago));
        $this->assertSame([$reembolso->getKey()], $pago->reembolsos->modelKeys());

        $evento = EventoPasarela::create([
            'pasarela' => Pago::PASARELA_SIMULADA,
            'id_pasarela_evento' => 'evento-de-prueba-1',
            'tipo' => EventoPasarela::APROBADO,
            'contenido' => ['orden' => 'orden-de-prueba-1'],
            'recibido_en' => '2026-12-03 15:29:00+00',
        ])->fresh();
        $this->assertSame(['orden' => 'orden-de-prueba-1'], $evento->contenido);
        $this->assertSame('2026-12-03T15:29:00Z', $evento->recibido_en->toIso8601ZuluString());
        $this->assertNull($evento->procesado_en);
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
     * Un cobro pendiente con tarjeta del pedido, en su plaza y en la pasarela simulada, con las columnas que se pasan.
     *
     * @param  array<string, mixed>  $datos
     */
    private function insertarPago(Pedido $pedido, array $datos = []): int
    {
        return DB::table('pagos')->insertGetId([
            'id_publico' => Str::lower((string) Str::ulid()),
            'id_pedidos' => $pedido->getKey(),
            'id_plazas' => $pedido->id_plazas,
            'tipo' => Pago::COBRO,
            'metodo' => Pago::TARJETA,
            'pasarela' => Pago::PASARELA_SIMULADA,
            'monto' => '180.00',
            'estado' => Pago::PENDIENTE,
            'created_at' => now(),
            'updated_at' => now(),
            ...$datos,
        ], 'id_pagos');
    }

    /**
     * Un reembolso pendiente del pago, en su plaza, con las columnas que se pasan.
     *
     * @param  array<string, mixed>  $datos
     */
    private function insertarReembolso(int $pago, Plaza $plaza, array $datos = []): int
    {
        return DB::table('reembolsos')->insertGetId([
            'id_pagos' => $pago,
            'id_plazas' => $plaza->getKey(),
            'monto' => '20.00',
            'motivo' => 'Motivo de prueba.',
            'estado' => 'pendiente',
            'created_at' => now(),
            'updated_at' => now(),
            ...$datos,
        ], 'id_reembolsos');
    }

    /**
     * Un aviso de pago aprobado de la pasarela simulada, con las columnas que se pasan.
     *
     * @param  array<string, mixed>  $datos
     */
    private function insertarEvento(array $datos = []): int
    {
        return DB::table('eventos_pasarela')->insertGetId([
            'pasarela' => Pago::PASARELA_SIMULADA,
            'id_pasarela_evento' => 'evento-'.Str::random(10),
            'tipo' => 'aprobado',
            'contenido' => json_encode(['orden' => 'orden-de-prueba']),
            'recibido_en' => now(),
            'created_at' => now(),
            'updated_at' => now(),
            ...$datos,
        ], 'id_eventos_pasarela');
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
