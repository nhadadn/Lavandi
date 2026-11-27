<?php

namespace Tests\Feature\Historial;

use App\Esquema\RevisorDeEsquema;
use App\Esquema\RevisorDeModelos;
use App\Esquema\Violacion;
use App\Models\Calificacion;
use App\Models\Lavanderia;
use App\Models\Pedido;
use App\Models\Usuario;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Estados\Soporte\EstadosDePrueba;
use Tests\Feature\Historial\Soporte\HistorialDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * La tabla calificaciones, completa: nació en perfil-de-lavanderia con el CHECK de exactamente un calificado, los únicos
 * por pedido y calificado e id_lavanderias opcional; repartidor le agregó la llave hacia repartidores, y este cambio, la
 * llave compuesta (id_pedidos, id_plazas) hacia pedidos (openspec/changes/historial-y-calificacion/design.md, "Tablas y
 * migración"; vault/arquitectura/modelo-de-datos.md; ADR-0016). El modelo Calificacion llega a su pedido.
 *
 * Las filas se insertan con el constructor de consultas, sin pasar por el modelo, porque historial-E13 pide que la base
 * rechace la fila "aunque el código no lo haya validado". El pedido es real: nace al aceptar el resumen, lo confirma el
 * cobro y llega a Entregado con su lavandería y su repartidor (HistorialDePrueba).
 *
 * Escenarios: historial-E13, y modelo-E01 a modelo-E17 sobre calificaciones · Spec:
 * openspec/changes/historial-y-calificacion/specs/historial/spec.md · Tarea 0.1
 */
class MigracionTest extends TestCase
{
    use AgendaDePrueba;
    use EstadosDePrueba;
    use HistorialDePrueba;
    use LavanderiasDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    /** SQLSTATE de PostgreSQL cuando una llave foránea no encuentra su fila padre. */
    private const LLAVE_FORANEA_ROTA = '23503';

    /** SQLSTATE de PostgreSQL cuando una fila repite un valor único. */
    private const UNICO_REPETIDO = '23505';

    /** SQLSTATE de PostgreSQL cuando una fila no cumple un CHECK. */
    private const CHECK_INCUMPLIDO = '23514';

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();
    }

    #[TestDox('modelo-E01 a modelo-E17: calificaciones cumple la prueba de esquema, con sus columnas del modelo de datos, y su modelo también')]
    public function test_modelo_e01_a_e17_calificaciones_cumple_la_prueba_de_esquema(): void
    {
        $violaciones = array_filter(
            (new RevisorDeEsquema)->revisar(),
            fn (Violacion $violacion): bool => $violacion->tabla === 'calificaciones',
        );

        $this->assertSame([], array_values(array_map('strval', $violaciones)));
        $this->assertSame([], array_map('strval', (new RevisorDeModelos)->revisar([Calificacion::class])));
        $this->assertSame(
            [
                'id_calificaciones', 'id_pedidos', 'id_plazas', 'id_usuarios', 'id_lavanderias', 'id_repartidores',
                'estrellas', 'comentario', 'created_at', 'updated_at',
            ],
            Schema::getColumnListing('calificaciones'),
        );
    }

    #[TestDox('modelo-E06 y modelo-E09: la calificación lleva la llave compuesta (id_pedidos, id_plazas) hacia pedidos, con su índice y ON DELETE RESTRICT')]
    public function test_modelo_e06_y_e09_la_calificacion_lleva_la_llave_compuesta_hacia_su_pedido(): void
    {
        $this->assertContains(
            [['id_pedidos', 'id_plazas'], 'pedidos', ['id_pedidos', 'id_plazas'], 'restrict'],
            $this->llaves('calificaciones'),
        );
        $this->assertContains(['id_pedidos', 'id_plazas'], $this->indices('calificaciones'));
    }

    #[TestDox('modelo-E06: la base rechaza una calificación de un pedido que no existe, o con otra plaza que la de su pedido')]
    public function test_modelo_e06_la_base_rechaza_una_calificacion_de_un_pedido_que_no_existe_o_de_otra_plaza(): void
    {
        $pedido = $this->pedidoEntregado();

        $this->assertRechazada(fn () => $this->insertarCalificacion($pedido, [
            'id_pedidos' => $pedido->getKey() + 1000,
        ]), self::LLAVE_FORANEA_ROTA);

        // Una lavandería de otra plaza, calificada con el pedido de la plaza de prueba.
        $otraPlaza = $this->plazaDePrueba('Plaza de prueba 2');
        $deOtraPlaza = Lavanderia::factory()->en($otraPlaza)->create();
        $this->assertRechazada(fn () => $this->insertarCalificacion($pedido, [
            'id_plazas' => $otraPlaza->getKey(),
            'id_lavanderias' => $deOtraPlaza->getKey(),
        ]), self::LLAVE_FORANEA_ROTA);

        $this->assertSame(0, DB::table('calificaciones')->count());
    }

    #[TestDox('historial-E13: la base rechaza una calificación del pedido entregado ligada a su lavandería y a su repartidor a la vez')]
    public function test_historial_e13_la_base_rechaza_una_calificacion_con_los_dos_calificados(): void
    {
        $pedido = $this->pedidoEntregado();
        $this->assertNotNull($pedido->id_repartidores);

        $this->assertRechazada(fn () => $this->insertarCalificacion($pedido, [
            'id_repartidores' => $pedido->id_repartidores,
        ]), self::CHECK_INCUMPLIDO);

        $this->assertSame(0, DB::table('calificaciones')->count());
    }

    #[TestDox('historial-E13: la base rechaza una calificación del pedido entregado sin ningún calificado')]
    public function test_historial_e13_la_base_rechaza_una_calificacion_sin_calificado(): void
    {
        $pedido = $this->pedidoEntregado();

        $this->assertRechazada(fn () => $this->insertarCalificacion($pedido, [
            'id_lavanderias' => null,
        ]), self::CHECK_INCUMPLIDO);

        $this->assertSame(0, DB::table('calificaciones')->count());
    }

    #[TestDox('historial-E13: la base rechaza una segunda calificación de la misma lavandería para el mismo pedido')]
    public function test_historial_e13_la_base_rechaza_una_segunda_calificacion_de_la_misma_lavanderia(): void
    {
        $pedido = $this->pedidoEntregado();

        $this->insertarCalificacion($pedido);
        $this->assertRechazada(fn () => $this->insertarCalificacion($pedido, ['estrellas' => 2]), self::UNICO_REPETIDO);

        $this->assertSame([4], DB::table('calificaciones')->pluck('estrellas')->all());
    }

    #[TestDox('historial-E13: con exactamente un calificado, el pedido entregado guarda una calificación de su lavandería y otra de su repartidor, cada una en su fila')]
    public function test_historial_e13_con_exactamente_un_calificado_la_fila_entra(): void
    {
        $pedido = $this->pedidoEntregado();

        $this->insertarCalificacion($pedido);
        $this->insertarCalificacion($pedido, ['id_lavanderias' => null, 'id_repartidores' => $pedido->id_repartidores]);

        $this->assertSame(2, DB::table('calificaciones')->where('id_pedidos', $pedido->getKey())->count());
    }

    #[TestDox('modelo-E16: la calificación llega a su pedido por id_pedidos, y el pedido a sus calificaciones')]
    public function test_modelo_e16_la_calificacion_llega_a_su_pedido_por_su_llave(): void
    {
        $pedido = $this->pedidoEntregado();
        $this->insertarCalificacion($pedido);

        $calificacion = Calificacion::query()->sole();

        $this->assertSame('id_pedidos', $calificacion->pedido()->getForeignKeyName());
        $this->assertTrue($calificacion->pedido->is($pedido));
        $this->assertSame('id_pedidos', $pedido->calificaciones()->getForeignKeyName());
        $this->assertSame([$calificacion->getKey()], $pedido->calificaciones()->pluck('id_calificaciones')->all());
    }

    #[TestDox('historial-E13: una calificación sin pedido, como las de prueba del perfil de la lavandería, sigue entrando')]
    public function test_historial_e13_una_calificacion_sin_pedido_sigue_entrando(): void
    {
        $this->insertarCalificacion(null);

        $this->assertSame(1, DB::table('calificaciones')->whereNull('id_pedidos')->count());
    }

    /**
     * Un pedido de una cuenta de usuario, confirmado y llevado hasta Entregado por su lavandería y su repartidor.
     */
    private function pedidoEntregado(): Pedido
    {
        return $this->entregar($this->pedidoConfirmadoEl($this->cuentaDeUsuario(), self::JUEVES_DE_PRUEBA));
    }

    /**
     * Una calificación de 4 estrellas del dueño del pedido, de la lavandería del pedido y en su plaza, con las columnas
     * que se pasan encima. Sin pedido, de la lavandería de prueba y de una cuenta nueva.
     *
     * @param  array<string, mixed>  $datos
     */
    private function insertarCalificacion(?Pedido $pedido, array $datos = []): void
    {
        DB::table('calificaciones')->insert([
            'id_pedidos' => $pedido?->getKey(),
            'id_plazas' => $pedido->id_plazas ?? $this->lavanderia->id_plazas,
            'id_usuarios' => $pedido->id_usuarios ?? Usuario::factory()->create()->getKey(),
            'id_lavanderias' => $pedido->id_lavanderias ?? $this->lavanderia->getKey(),
            'estrellas' => 4,
            'created_at' => now(),
            'updated_at' => now(),
            ...$datos,
        ]);
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
     * Las columnas de cada índice de la tabla.
     *
     * @return list<list<string>>
     */
    private function indices(string $tabla): array
    {
        return array_map(fn (array $indice): array => $indice['columns'], Schema::getIndexes($tabla));
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
