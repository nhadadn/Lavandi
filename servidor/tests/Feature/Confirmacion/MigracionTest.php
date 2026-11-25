<?php

namespace Tests\Feature\Confirmacion;

use App\Esquema\RevisorDeEsquema;
use App\Esquema\RevisorDeModelos;
use App\Esquema\Violacion;
use App\Models\Direccion;
use App\Models\EstadoPedido;
use App\Models\Lavanderia;
use App\Models\Pedido;
use App\Models\PedidoDireccion;
use App\Models\PedidoEvento;
use App\Models\PedidoServicio;
use App\Models\TipoServicio;
use App\Models\UnidadMedida;
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
 * Las cinco tablas del pedido: el catálogo estados_pedido, pedidos, pedido_servicios, pedido_direcciones y
 * pedido_eventos, con sus modelos de Eloquent (openspec/changes/resumen-y-confirmacion/design.md, "Tablas y
 * migración"; vault/arquitectura/modelo-de-datos.md; ADR-0016).
 *
 * Las filas se insertan con el constructor de consultas, sin pasar por los modelos, cuando la prueba pide que la base
 * rechace la fila aunque el código no la haya validado.
 *
 * Escenarios: modelo-E01 a modelo-E17 sobre las tablas nuevas · Spec:
 * openspec/changes/modelo-de-datos/specs/modelo-de-datos/spec.md · Tarea 1.1 de
 * openspec/changes/resumen-y-confirmacion/tasks.md
 */
class MigracionTest extends TestCase
{
    use PlazasDePrueba;
    use RefreshDatabase;

    private const TABLAS = ['estados_pedido', 'pedidos', 'pedido_servicios', 'pedido_direcciones', 'pedido_eventos'];

    /** Las hijas de pedidos: llevan las dos llaves, a plazas y la compuesta a su pedido. */
    private const HIJAS = ['pedido_servicios', 'pedido_direcciones', 'pedido_eventos'];

    /** SQLSTATE restrict_violation: el que lanza impedir_cambio(). */
    private const CAMBIO_IMPEDIDO = '23001';

    /** SQLSTATE de PostgreSQL cuando una llave foránea no encuentra su fila padre, o su padre tiene hijas. */
    private const LLAVE_FORANEA_ROTA = '23503';

    /** SQLSTATE de PostgreSQL cuando se repite un valor único. */
    private const UNICO_REPETIDO = '23505';

    /** SQLSTATE de PostgreSQL cuando una fila no cumple un CHECK. */
    private const CHECK_INCUMPLIDO = '23514';

    /** SQLSTATE de PostgreSQL cuando se escribe en una columna generada. */
    private const COLUMNA_GENERADA = '428C9';

    #[TestDox('modelo-E01 a modelo-E17: las cinco tablas del pedido existen y cumplen la prueba de esquema')]
    public function test_modelo_e01_a_e17_las_cinco_tablas_del_pedido_existen_y_cumplen_la_prueba_de_esquema(): void
    {
        foreach (self::TABLAS as $tabla) {
            $this->assertTrue(Schema::hasTable($tabla), "Falta la tabla {$tabla}.");
        }

        $violaciones = array_filter(
            (new RevisorDeEsquema)->revisar(),
            fn (Violacion $violacion): bool => in_array($violacion->tabla, self::TABLAS, true),
        );

        $this->assertSame([], array_values(array_map('strval', $violaciones)));
        // estados_pedido es un catálogo: ya estaba en la lista de tablas sin plaza.
        $this->assertContains('estados_pedido', config('modelo.sin_plaza'));
    }

    #[TestDox('modelo-E01 a modelo-E17: las columnas son las del modelo de datos, en su orden')]
    public function test_modelo_e01_a_e17_las_columnas_son_las_del_modelo_de_datos_en_su_orden(): void
    {
        $this->assertSame(
            ['id_estados_pedido', 'codigo', 'nombre_usuario', 'orden', 'visible_usuario', 'created_at', 'updated_at'],
            Schema::getColumnListing('estados_pedido'),
        );
        $this->assertSame(
            [
                'id_pedidos', 'id_publico', 'id_plazas', 'folio', 'id_usuarios', 'id_lavanderias', 'id_repartidores',
                'id_estados_pedido', 'ventana_inicio', 'ventana_fin', 'monto_estimado', 'monto_final', 'envio',
                'comision_porcentaje', 'confirmado_en', 'cancelado_en', 'created_at', 'updated_at',
            ],
            Schema::getColumnListing('pedidos'),
        );
        $this->assertSame(
            [
                'id_pedido_servicios', 'id_pedidos', 'id_plazas', 'id_tipos_servicio', 'id_unidades_medida',
                'cantidad_estimada', 'cantidad_real', 'precio_unitario', 'monto_estimado', 'monto_real',
                'created_at', 'updated_at',
            ],
            Schema::getColumnListing('pedido_servicios'),
        );
        $this->assertSame(
            [
                'id_pedido_direcciones', 'id_pedidos', 'id_plazas', 'tipo', 'id_direcciones', 'alias', 'calle',
                'numero_exterior', 'numero_interior', 'colonia', 'codigo_postal', 'municipio', 'referencias',
                'ubicacion', 'created_at', 'updated_at',
            ],
            Schema::getColumnListing('pedido_direcciones'),
        );
        $this->assertSame(
            [
                'id_pedido_eventos', 'id_pedidos', 'id_plazas', 'id_estados_pedido', 'id_usuarios', 'motivo',
                'ocurrido_en', 'created_at', 'updated_at',
            ],
            Schema::getColumnListing('pedido_eventos'),
        );
    }

    #[TestDox('modelo-E07 y modelo-E08: el dinero, el porcentaje, las cantidades, los instantes y el punto llevan su tipo')]
    public function test_modelo_e07_y_e08_las_columnas_llevan_su_tipo(): void
    {
        foreach (['monto_estimado', 'monto_final', 'envio'] as $columna) {
            $this->assertSame('numeric(12,2)', $this->tipo('pedidos', $columna), $columna);
        }
        $this->assertSame('numeric(5,2)', $this->tipo('pedidos', 'comision_porcentaje'));
        foreach (['ventana_inicio', 'ventana_fin', 'confirmado_en', 'cancelado_en'] as $columna) {
            $this->assertSame('timestamp(0) with time zone', $this->tipo('pedidos', $columna), $columna);
        }
        $this->assertSame('character(26)', $this->tipo('pedidos', 'id_publico'));

        foreach (['precio_unitario', 'monto_estimado', 'monto_real'] as $columna) {
            $this->assertSame('numeric(12,2)', $this->tipo('pedido_servicios', $columna), $columna);
        }
        foreach (['cantidad_estimada', 'cantidad_real'] as $columna) {
            $this->assertSame('numeric(8,2)', $this->tipo('pedido_servicios', $columna), $columna);
        }

        $this->assertSame('geography(Point,4326)', $this->tipo('pedido_direcciones', 'ubicacion'));
        $this->assertSame('timestamp(0) with time zone', $this->tipo('pedido_eventos', 'ocurrido_en'));
        $this->assertSame('text', $this->tipo('pedido_eventos', 'motivo'));
        $this->assertSame('boolean', $this->tipo('estados_pedido', 'visible_usuario'));
    }

    #[TestDox('modelo-E07: los subtotales de pedido_servicios son columnas generadas, cantidad por precio unitario, que no se capturan')]
    public function test_modelo_e07_los_subtotales_son_columnas_generadas_que_no_se_capturan(): void
    {
        foreach (['monto_estimado', 'monto_real'] as $columna) {
            $this->assertSame('s', DB::scalar(
                "select attgenerated from pg_catalog.pg_attribute
                  where attrelid = to_regclass('pedido_servicios') and attname = ?",
                [$columna],
            ), $columna);
        }

        $pedido = $this->insertarPedido();

        // Sin cantidad, el subtotal queda vacío: la cantidad estimada espera DD-012.
        $sinCantidad = $this->insertarServicio($pedido);
        $this->assertNull(DB::table('pedido_servicios')->where('id_pedido_servicios', $sinCantidad)->value('monto_estimado'));

        $conCantidad = $this->insertarServicio($pedido, [
            'cantidad_estimada' => '3.50',
            'cantidad_real' => '4.25',
            'precio_unitario' => '18.00',
        ]);
        $fila = DB::table('pedido_servicios')->where('id_pedido_servicios', $conCantidad)->first();
        $this->assertSame(['63.00', '76.50'], [$fila->monto_estimado, $fila->monto_real]);

        // Nadie lo escribe.
        $this->assertRechazada(
            fn () => $this->insertarServicio($pedido, ['monto_estimado' => '1.00']),
            self::COLUMNA_GENERADA,
        );
    }

    #[TestDox('modelo-E07: la semilla de estados_pedido es por_pagar, que el usuario no ve, y los cinco estados del usuario, en su orden y visibles (ADR-0021)')]
    public function test_modelo_e07_la_semilla_de_estados_pedido_son_los_cinco_estados_del_usuario(): void
    {
        $this->assertSame(
            [
                ['por_pagar', 'Por pagar', 0, false],
                ['confirmado', 'Confirmado', 1, true],
                ['recolectado', 'Recolectado', 2, true],
                ['en_proceso', 'En proceso', 3, true],
                ['listo', 'Listo', 4, true],
                ['entregado', 'Entregado', 5, true],
            ],
            DB::table('estados_pedido')
                ->whereIn('codigo', ['por_pagar', 'confirmado', 'recolectado', 'en_proceso', 'listo', 'entregado'])
                ->orderBy('orden')
                ->get()
                ->map(fn (object $estado): array => [
                    $estado->codigo,
                    $estado->nombre_usuario,
                    $estado->orden,
                    $estado->visible_usuario,
                ])
                ->all(),
        );

        // Los demás internos los siembra el cambio que los usa (ADR-0021, decisión 3), y ninguno es un paso más del
        // seguimiento: los visibles siguen siendo exactamente los cinco.
        $this->assertSame(
            ['confirmado', 'recolectado', 'en_proceso', 'listo', 'entregado'],
            DB::table('estados_pedido')->where('visible_usuario', true)->orderBy('orden')->pluck('codigo')->all(),
        );
        $this->assertSame(1, EstadoPedido::where('codigo', EstadoPedido::CONFIRMADO)->count());
    }

    #[TestDox('modelo-E07: los únicos son el código del estado, el identificador público, el folio por plaza, el par con la plaza y el tipo de dirección por pedido')]
    public function test_modelo_e07_los_unicos_de_las_cinco_tablas(): void
    {
        $this->assertEqualsCanonicalizing([['codigo']], $this->unicos('estados_pedido'));
        $this->assertEqualsCanonicalizing(
            [['id_publico'], ['id_plazas', 'folio'], ['id_pedidos', 'id_plazas']],
            $this->unicos('pedidos'),
        );
        $this->assertSame([], $this->unicos('pedido_servicios'));
        $this->assertEqualsCanonicalizing([['id_pedidos', 'tipo']], $this->unicos('pedido_direcciones'));
        $this->assertSame([], $this->unicos('pedido_eventos'));
    }

    #[TestDox('modelo-E06 y modelo-E09: las llaves foráneas, compuestas con la plaza hacia la lavandería, el pedido y la dirección, todas ON DELETE RESTRICT')]
    public function test_modelo_e06_y_e09_las_llaves_foraneas_de_las_cinco_tablas(): void
    {
        $this->assertSame([], $this->llaves('estados_pedido'));

        $this->assertEqualsCanonicalizing([
            [['id_plazas'], 'plazas', ['id_plazas'], 'restrict'],
            [['id_usuarios'], 'usuarios', ['id_usuarios'], 'restrict'],
            [['id_lavanderias', 'id_plazas'], 'lavanderias', ['id_lavanderias', 'id_plazas'], 'restrict'],
            [['id_estados_pedido'], 'estados_pedido', ['id_estados_pedido'], 'restrict'],
            // La agregó el cambio repartidor, que crea repartidores (ADR-0016, regla 4).
            [['id_repartidores', 'id_plazas'], 'repartidores', ['id_repartidores', 'id_plazas'], 'restrict'],
        ], $this->llaves('pedidos'));

        $alPedido = [['id_pedidos', 'id_plazas'], 'pedidos', ['id_pedidos', 'id_plazas'], 'restrict'];
        $aPlazas = [['id_plazas'], 'plazas', ['id_plazas'], 'restrict'];

        $this->assertEqualsCanonicalizing([
            $alPedido,
            $aPlazas,
            [['id_tipos_servicio'], 'tipos_servicio', ['id_tipos_servicio'], 'restrict'],
            [['id_unidades_medida'], 'unidades_medida', ['id_unidades_medida'], 'restrict'],
        ], $this->llaves('pedido_servicios'));

        $this->assertEqualsCanonicalizing([
            $alPedido,
            $aPlazas,
            [['id_direcciones', 'id_plazas'], 'direcciones', ['id_direcciones', 'id_plazas'], 'restrict'],
        ], $this->llaves('pedido_direcciones'));

        $this->assertEqualsCanonicalizing([
            $alPedido,
            $aPlazas,
            [['id_estados_pedido'], 'estados_pedido', ['id_estados_pedido'], 'restrict'],
            [['id_usuarios'], 'usuarios', ['id_usuarios'], 'restrict'],
        ], $this->llaves('pedido_eventos'));
    }

    #[TestDox('modelo-E05: el repartidor nace opcional y sin llave, y el folio, el estimado, el envío, la comisión y las fechas de confirmación y cancelación nacen vacíos')]
    public function test_modelo_e05_el_repartidor_nace_sin_llave_y_lo_que_llena_otro_cambio_nace_opcional(): void
    {
        foreach (['id_repartidores', 'folio', 'monto_estimado', 'monto_final', 'envio', 'comision_porcentaje',
            'confirmado_en', 'cancelado_en'] as $columna) {
            $this->assertTrue($this->admiteVacio('pedidos', $columna), $columna);
        }

        foreach (['id_plazas', 'id_usuarios', 'id_lavanderias', 'id_estados_pedido', 'ventana_inicio', 'ventana_fin'] as $columna) {
            $this->assertFalse($this->admiteVacio('pedidos', $columna), $columna);
        }

        // La llave compuesta hacia repartidores la agrega el cambio repartidor.
        $this->assertNotContains(['id_repartidores'], array_column(Schema::getForeignKeys('pedidos'), 'columns'));

        // El evento que hace el sistema, como la confirmación por el pago, no lleva cuenta; el motivo es opcional.
        $this->assertTrue($this->admiteVacio('pedido_eventos', 'id_usuarios'));
        $this->assertTrue($this->admiteVacio('pedido_eventos', 'motivo'));
        $this->assertFalse($this->admiteVacio('pedido_eventos', 'ocurrido_en'));
    }

    #[TestDox('modelo-E06: la base rechaza un pedido con una lavandería de otra plaza, y una hija con otra plaza que su pedido')]
    public function test_modelo_e06_la_base_rechaza_un_pedido_y_sus_hijas_de_otra_plaza(): void
    {
        $plazaA = $this->insertarPlaza('Plaza de prueba A');
        $plazaB = $this->insertarPlaza('Plaza de prueba B');
        $lavanderiaA = $this->insertarLavanderia($plazaA);

        $this->assertRechazada(
            fn () => $this->insertarPedido(['id_plazas' => $plazaB, 'id_lavanderias' => $lavanderiaA]),
            self::LLAVE_FORANEA_ROTA,
        );

        $pedido = $this->insertarPedido(['id_plazas' => $plazaA, 'id_lavanderias' => $lavanderiaA]);

        $this->assertRechazada(fn () => $this->insertarServicio($pedido, ['id_plazas' => $plazaB]), self::LLAVE_FORANEA_ROTA);
        $this->assertRechazada(fn () => $this->insertarEvento($pedido, ['id_plazas' => $plazaB]), self::LLAVE_FORANEA_ROTA);
        $this->assertRechazada(
            fn () => $this->insertarDireccionDelPedido($pedido, [
                'id_plazas' => $plazaB,
                'id_direcciones' => $this->insertarDireccion($plazaB),
            ]),
            self::LLAVE_FORANEA_ROTA,
        );

        // Con la plaza de su pedido, entran.
        $this->insertarServicio($pedido);
        $this->insertarEvento($pedido);
        $this->insertarDireccionDelPedido($pedido);

        $this->assertSame(1, DB::table('pedidos')->count());
        foreach (self::HIJAS as $hija) {
            $this->assertSame(1, DB::table($hija)->count(), $hija);
        }
    }

    #[TestDox('modelo-E06: la base rechaza copiar al pedido una dirección de otra plaza o sin plaza (direcciones-E11 y E12)')]
    public function test_modelo_e06_la_base_rechaza_copiar_una_direccion_de_otra_plaza_o_sin_plaza(): void
    {
        $plazaA = $this->insertarPlaza('Plaza de prueba A');
        $plazaB = $this->insertarPlaza('Plaza de prueba B');
        $pedido = $this->insertarPedido(['id_plazas' => $plazaA, 'id_lavanderias' => $this->insertarLavanderia($plazaA)]);

        $this->assertRechazada(
            fn () => $this->insertarDireccionDelPedido($pedido, ['id_direcciones' => $this->insertarDireccion($plazaB)]),
            self::LLAVE_FORANEA_ROTA,
        );
        $this->assertRechazada(
            fn () => $this->insertarDireccionDelPedido($pedido, ['id_direcciones' => $this->insertarDireccion(null)]),
            self::LLAVE_FORANEA_ROTA,
        );

        $this->assertSame(0, DB::table('pedido_direcciones')->count());
    }

    #[TestDox('modelo-E07: la base rechaza una ventana que no termina después de empezar, montos negativos y una comisión fuera de 0 a 100')]
    public function test_modelo_e07_la_base_rechaza_ventana_imposible_montos_negativos_y_comision_fuera_de_rango(): void
    {
        $this->assertRechazada(
            fn () => $this->insertarPedido(['ventana_inicio' => '2026-12-03 16:00:00+00', 'ventana_fin' => '2026-12-03 16:00:00+00']),
            self::CHECK_INCUMPLIDO,
        );
        $this->assertRechazada(
            fn () => $this->insertarPedido(['ventana_inicio' => '2026-12-03 16:00:00+00', 'ventana_fin' => '2026-12-03 15:00:00+00']),
            self::CHECK_INCUMPLIDO,
        );

        foreach (['monto_estimado', 'monto_final', 'envio'] as $columna) {
            $this->assertRechazada(fn () => $this->insertarPedido([$columna => '-0.01']), self::CHECK_INCUMPLIDO);
            $this->insertarPedido([$columna => '0.00']);
        }

        $this->assertRechazada(fn () => $this->insertarPedido(['comision_porcentaje' => '-0.01']), self::CHECK_INCUMPLIDO);
        $this->assertRechazada(fn () => $this->insertarPedido(['comision_porcentaje' => '100.01']), self::CHECK_INCUMPLIDO);
        $this->insertarPedido(['comision_porcentaje' => '0.00']);
        $this->insertarPedido(['comision_porcentaje' => '100.00']);

        $this->assertSame(5, DB::table('pedidos')->count());
    }

    #[TestDox('modelo-E07: la base rechaza un precio unitario negativo, una cantidad que no es mayor que cero y un tipo de dirección que no es recoleccion ni entrega')]
    public function test_modelo_e07_la_base_rechaza_precio_negativo_cantidad_no_positiva_y_tipo_de_direccion_desconocido(): void
    {
        $pedido = $this->insertarPedido();

        $this->assertRechazada(fn () => $this->insertarServicio($pedido, ['precio_unitario' => '-0.01']), self::CHECK_INCUMPLIDO);
        foreach (['cantidad_estimada', 'cantidad_real'] as $columna) {
            $this->assertRechazada(fn () => $this->insertarServicio($pedido, [$columna => '0.00']), self::CHECK_INCUMPLIDO);
            $this->assertRechazada(fn () => $this->insertarServicio($pedido, [$columna => '-1.00']), self::CHECK_INCUMPLIDO);
        }
        $this->insertarServicio($pedido, ['precio_unitario' => '0.00', 'cantidad_estimada' => '0.01']);

        $this->assertRechazada(fn () => $this->insertarDireccionDelPedido($pedido, ['tipo' => 'otra']), self::CHECK_INCUMPLIDO);
        $this->insertarDireccionDelPedido($pedido, ['tipo' => 'recoleccion']);
        $this->insertarDireccionDelPedido($pedido, ['tipo' => 'entrega']);

        // Una sola de cada tipo por pedido.
        $this->assertRechazada(
            fn () => $this->insertarDireccionDelPedido($pedido, ['tipo' => 'entrega']),
            self::UNICO_REPETIDO,
        );

        $this->assertSame(1, DB::table('pedido_servicios')->count());
        $this->assertSame(2, DB::table('pedido_direcciones')->count());
    }

    #[TestDox('modelo-E07: el folio vacío se repite en la plaza; los códigos de estado y los identificadores públicos no')]
    public function test_modelo_e07_el_folio_vacio_se_repite_y_los_codigos_e_identificadores_no(): void
    {
        $plaza = $this->insertarPlaza('Plaza de prueba 1');
        $lavanderia = $this->insertarLavanderia($plaza);

        // PostgreSQL admite varios vacíos en un único: los pedidos que esperan su pago no tienen folio.
        $this->insertarPedido(['id_plazas' => $plaza, 'id_lavanderias' => $lavanderia]);
        $this->insertarPedido(['id_plazas' => $plaza, 'id_lavanderias' => $lavanderia]);

        $ulid = Str::lower((string) Str::ulid());
        $this->insertarPedido(['id_publico' => $ulid]);
        $this->assertRechazada(fn () => $this->insertarPedido(['id_publico' => $ulid]), self::UNICO_REPETIDO);

        $this->assertRechazada(fn () => $this->insertarEstado('confirmado'), self::UNICO_REPETIDO);

        $this->assertSame(3, DB::table('pedidos')->count());
    }

    #[TestDox('modelo-E11: la base rechaza borrar un pedido, y sí deja cambiar su estado actual')]
    public function test_modelo_e11_la_base_rechaza_borrar_un_pedido_y_deja_cambiar_su_estado_actual(): void
    {
        $pedido = $this->insertarPedido();

        $this->assertCambioImpedido(
            fn () => DB::table('pedidos')->where('id_pedidos', $pedido)->delete(),
            'DELETE',
            'pedidos',
        );

        DB::table('pedidos')->where('id_pedidos', $pedido)->update([
            'id_estados_pedido' => $this->idDelEstado('recolectado'),
        ]);

        $this->assertSame($this->idDelEstado('recolectado'), DB::table('pedidos')->where('id_pedidos', $pedido)->value('id_estados_pedido'));
    }

    #[TestDox('modelo-E12: la base rechaza editar o borrar un evento del pedido; el siguiente se agrega como fila nueva')]
    public function test_modelo_e12_la_base_rechaza_editar_o_borrar_un_evento_del_pedido(): void
    {
        $pedido = $this->insertarPedido();
        $evento = $this->insertarEvento($pedido);

        $this->assertCambioImpedido(
            fn () => DB::table('pedido_eventos')->where('id_pedido_eventos', $evento)->update([
                'id_estados_pedido' => $this->idDelEstado('recolectado'),
            ]),
            'UPDATE',
            'pedido_eventos',
        );
        $this->assertCambioImpedido(
            fn () => DB::table('pedido_eventos')->where('id_pedido_eventos', $evento)->delete(),
            'DELETE',
            'pedido_eventos',
        );

        $this->insertarEvento($pedido, ['id_estados_pedido' => $this->idDelEstado('recolectado')]);

        $this->assertSame(
            [$this->idDelEstado('confirmado'), $this->idDelEstado('recolectado')],
            DB::table('pedido_eventos')->orderBy('id_pedido_eventos')->pluck('id_estados_pedido')->all(),
        );
    }

    #[TestDox('modelo-E11 y modelo-E12: los triggers del historial están solo en pedidos y pedido_eventos')]
    public function test_modelo_e11_y_e12_los_triggers_del_historial_estan_solo_en_pedidos_y_pedido_eventos(): void
    {
        $this->assertSame(['DELETE'], $this->triggers('pedidos'));
        $this->assertEqualsCanonicalizing(['DELETE', 'UPDATE'], $this->triggers('pedido_eventos'));

        foreach (['estados_pedido', 'pedido_servicios', 'pedido_direcciones'] as $tabla) {
            $this->assertSame([], $this->triggers($tabla), $tabla);
        }
    }

    #[TestDox('modelo-E09: la base no borra la lavandería, la cuenta, la dirección ni el estado de un pedido')]
    public function test_modelo_e09_la_base_no_borra_lo_que_usa_un_pedido(): void
    {
        $plaza = $this->insertarPlaza('Plaza de prueba 1');
        $lavanderia = $this->insertarLavanderia($plaza);
        $usuario = $this->insertarUsuario();
        $pedido = $this->insertarPedido(['id_plazas' => $plaza, 'id_lavanderias' => $lavanderia, 'id_usuarios' => $usuario]);
        $direccion = $this->insertarDireccion($plaza);
        $this->insertarDireccionDelPedido($pedido, ['id_direcciones' => $direccion]);

        $this->assertRechazada(fn () => DB::table('lavanderias')->where('id_lavanderias', $lavanderia)->delete(), self::LLAVE_FORANEA_ROTA);
        $this->assertRechazada(fn () => DB::table('usuarios')->where('id_usuarios', $usuario)->delete(), self::LLAVE_FORANEA_ROTA);
        $this->assertRechazada(fn () => DB::table('direcciones')->where('id_direcciones', $direccion)->delete(), self::LLAVE_FORANEA_ROTA);
        $this->assertRechazada(
            fn () => DB::table('estados_pedido')->where('codigo', 'confirmado')->delete(),
            self::LLAVE_FORANEA_ROTA,
        );

        $this->assertSame(1, DB::table('pedidos')->count());
    }

    #[TestDox('modelo-E16: los cinco modelos declaran su tabla y su llave igual que la base')]
    public function test_modelo_e16_los_cinco_modelos_declaran_su_tabla_y_su_llave_igual_que_la_base(): void
    {
        $modelos = [
            EstadoPedido::class => ['estados_pedido', 'id_estados_pedido'],
            Pedido::class => ['pedidos', 'id_pedidos'],
            PedidoServicio::class => ['pedido_servicios', 'id_pedido_servicios'],
            PedidoDireccion::class => ['pedido_direcciones', 'id_pedido_direcciones'],
            PedidoEvento::class => ['pedido_eventos', 'id_pedido_eventos'],
        ];

        foreach ($modelos as $clase => $esperado) {
            $this->assertSame($esperado, [(new $clase)->getTable(), (new $clase)->getKeyName()], $clase);
        }

        $this->assertSame([], array_map('strval', (new RevisorDeModelos)->revisar(array_keys($modelos))));
    }

    #[TestDox('modelo-E15 y modelo-E16: el pedido recibe su ULID y llega por sus llaves a su plaza, su cuenta, su lavandería, su estado y sus hijas, que toman su plaza')]
    public function test_modelo_e15_y_e16_el_pedido_recibe_su_ulid_y_llega_a_lo_suyo_por_sus_llaves(): void
    {
        $plaza = $this->plazaDePrueba();
        $lavanderia = Lavanderia::factory()->en($plaza)->create();
        $usuario = Usuario::factory()->create();
        $confirmado = EstadoPedido::where('codigo', EstadoPedido::CONFIRMADO)->sole();

        $pedido = Pedido::create([
            'id_plazas' => $plaza->getKey(),
            'id_usuarios' => $usuario->getKey(),
            'id_lavanderias' => $lavanderia->getKey(),
            'id_estados_pedido' => $confirmado->getKey(),
            'ventana_inicio' => '2026-12-03 16:00:00',
            'ventana_fin' => '2026-12-03 17:00:00',
        ]);

        $this->assertTrue(Str::isUlid($pedido->id_publico));
        $this->assertSame('id_publico', $pedido->getRouteKeyName());

        $this->assertTrue($plaza->is($pedido->plaza));
        $this->assertTrue($usuario->is($pedido->usuario));
        $this->assertTrue($lavanderia->is($pedido->lavanderia));
        $this->assertTrue($confirmado->is($pedido->estado));

        $direccion = Direccion::create([
            'id_usuarios' => $usuario->getKey(),
            'id_plazas' => $plaza->getKey(),
            'colonia' => 'Colonia de prueba',
            'ubicacion' => ['latitud' => 10.5, 'longitud' => -119.5],
        ]);

        // Las hijas toman la plaza de su pedido al crearse.
        $servicio = $pedido->servicios()->create([
            'id_tipos_servicio' => TipoServicio::where('codigo', TipoServicio::ESTANDAR)->sole()->getKey(),
            'id_unidades_medida' => UnidadMedida::where('codigo', UnidadMedida::KILO)->sole()->getKey(),
            'precio_unitario' => '18.00',
        ]);
        $copia = $pedido->direcciones()->create([
            'tipo' => PedidoDireccion::RECOLECCION,
            'id_direcciones' => $direccion->getKey(),
            'colonia' => 'Colonia de prueba',
            'ubicacion' => ['latitud' => 10.5, 'longitud' => -119.5],
        ]);
        $evento = $pedido->eventos()->create([
            'id_estados_pedido' => $confirmado->getKey(),
            'ocurrido_en' => '2026-11-21 18:00:00',
        ]);

        foreach ([$servicio, $copia, $evento] as $hija) {
            $this->assertSame($plaza->getKey(), $hija->fresh()->id_plazas, $hija::class);
            $this->assertTrue($pedido->is($hija->pedido), $hija::class);
        }

        $this->assertSame('18.00', $servicio->fresh()->precio_unitario);
        $this->assertTrue($direccion->is($copia->direccion));
        $this->assertTrue($confirmado->is($evento->estado));
        $this->assertSame(1, $pedido->servicios()->count());
        $this->assertSame(1, $pedido->direcciones()->count());
        $this->assertSame(1, $pedido->eventos()->count());

        // El punto de la copia no sale en JSON: es el EWKB de PostGIS.
        $this->assertArrayNotHasKey('ubicacion', $copia->toArray());
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
            'id_publico' => Str::lower((string) Str::ulid()),
            'correo' => Str::lower((string) Str::ulid()).'@ejemplo.com',
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id_usuarios');
    }

    private function insertarLavanderia(int $plaza): int
    {
        return DB::table('lavanderias')->insertGetId([
            'id_publico' => Str::lower((string) Str::ulid()),
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
        ], 'id_lavanderias');
    }

    private function insertarDireccion(?int $plaza): int
    {
        return DB::table('direcciones')->insertGetId([
            'id_publico' => Str::lower((string) Str::ulid()),
            'id_usuarios' => $this->insertarUsuario(),
            'id_plazas' => $plaza,
            'colonia' => 'Colonia de prueba',
            'ubicacion' => DB::raw("ST_GeogFromText('SRID=4326;POINT(-119.5 10.5)')"),
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id_direcciones');
    }

    private function insertarEstado(string $codigo): int
    {
        return DB::table('estados_pedido')->insertGetId([
            'codigo' => $codigo,
            'nombre_usuario' => 'Estado de prueba',
            'orden' => 0,
            'visible_usuario' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id_estados_pedido');
    }

    private function idDelEstado(string $codigo): int
    {
        return DB::table('estados_pedido')->where('codigo', $codigo)->value('id_estados_pedido');
    }

    /**
     * Un pedido de una lavandería y una cuenta nuevas, en la plaza de la lavandería, con las columnas que se pasan.
     *
     * @param  array<string, mixed>  $datos
     */
    private function insertarPedido(array $datos = []): int
    {
        if (! isset($datos['id_plazas'])) {
            $datos['id_plazas'] = $this->insertarPlaza('Plaza de prueba '.Str::ulid());
        }

        return DB::table('pedidos')->insertGetId([
            'id_publico' => Str::lower((string) Str::ulid()),
            'id_usuarios' => $this->insertarUsuario(),
            'id_lavanderias' => $datos['id_lavanderias'] ?? $this->insertarLavanderia($datos['id_plazas']),
            'id_estados_pedido' => $this->idDelEstado('confirmado'),
            'ventana_inicio' => '2026-12-03 16:00:00+00',
            'ventana_fin' => '2026-12-03 17:00:00+00',
            'created_at' => now(),
            'updated_at' => now(),
            ...$datos,
        ], 'id_pedidos');
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function insertarServicio(int $pedido, array $datos = []): int
    {
        return DB::table('pedido_servicios')->insertGetId([
            'id_pedidos' => $pedido,
            'id_plazas' => $this->plazaDelPedido($pedido),
            'id_tipos_servicio' => DB::table('tipos_servicio')->where('codigo', 'estandar')->value('id_tipos_servicio'),
            'id_unidades_medida' => DB::table('unidades_medida')->where('codigo', 'kg')->value('id_unidades_medida'),
            'precio_unitario' => '18.00',
            'created_at' => now(),
            'updated_at' => now(),
            ...$datos,
        ], 'id_pedido_servicios');
    }

    /**
     * La copia de una dirección de la plaza del pedido, como recolección, con las columnas que se pasan.
     *
     * @param  array<string, mixed>  $datos
     */
    private function insertarDireccionDelPedido(int $pedido, array $datos = []): int
    {
        $plaza = $this->plazaDelPedido($pedido);

        return DB::table('pedido_direcciones')->insertGetId([
            'id_pedidos' => $pedido,
            'id_plazas' => $plaza,
            'tipo' => 'recoleccion',
            'id_direcciones' => $datos['id_direcciones'] ?? $this->insertarDireccion($plaza),
            'colonia' => 'Colonia de prueba',
            'ubicacion' => DB::raw("ST_GeogFromText('SRID=4326;POINT(-119.5 10.5)')"),
            'created_at' => now(),
            'updated_at' => now(),
            ...$datos,
        ], 'id_pedido_direcciones');
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function insertarEvento(int $pedido, array $datos = []): int
    {
        return DB::table('pedido_eventos')->insertGetId([
            'id_pedidos' => $pedido,
            'id_plazas' => $this->plazaDelPedido($pedido),
            'id_estados_pedido' => $this->idDelEstado('confirmado'),
            'ocurrido_en' => '2026-11-21 18:00:00+00',
            'created_at' => now(),
            'updated_at' => now(),
            ...$datos,
        ], 'id_pedido_eventos');
    }

    private function plazaDelPedido(int $pedido): int
    {
        return DB::table('pedidos')->where('id_pedidos', $pedido)->value('id_plazas');
    }

    /**
     * El tipo de la columna como lo escribe la base: numeric(12,2), geography(Point,4326), character(26).
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
     * Las columnas de cada índice único de la tabla, sin la llave primaria.
     *
     * @return list<list<string>>
     */
    private function unicos(string $tabla): array
    {
        return array_values(array_map(
            fn (array $indice): array => $indice['columns'],
            array_filter(
                Schema::getIndexes($tabla),
                fn (array $indice): bool => $indice['unique'] && ! $indice['primary'],
            ),
        ));
    }

    /**
     * Las operaciones que vigila un trigger de la tabla que no es interno: DELETE, UPDATE.
     *
     * @return list<string>
     */
    private function triggers(string $tabla): array
    {
        return array_map(
            fn (object $fila): string => $fila->operacion,
            DB::select(
                "select event_manipulation as operacion
                   from information_schema.triggers
                  where event_object_schema = 'public' and event_object_table = ?
                  order by event_manipulation",
                [$tabla],
            ),
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

    /**
     * La operación falla con el error de impedir_cambio(), que nombra la operación y la tabla.
     */
    private function assertCambioImpedido(callable $operacion, string $sentencia, string $tabla): void
    {
        try {
            DB::transaction($operacion);
            $this->fail("La base debió rechazar {$sentencia} sobre {$tabla}.");
        } catch (QueryException $error) {
            $this->assertSame(self::CAMBIO_IMPEDIDO, $error->getCode(), $error->getMessage());
            $this->assertStringContainsString("impedir_cambio: {$sentencia} sobre {$tabla}", $error->getMessage());
        }
    }
}
