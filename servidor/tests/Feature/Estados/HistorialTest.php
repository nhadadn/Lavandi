<?php

namespace Tests\Feature\Estados;

use App\Models\EstadoPedido;
use App\Models\Pedido;
use App\Models\PedidoEvento;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Estados\Soporte\EstadosDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * El historial de un pedido solo crece: cada cambio de estado es un evento nuevo en pedido_eventos, y la base rechaza
 * editar o borrar cualquiera, aunque el código lo intente por el modelo de Eloquent o por una consulta
 * (NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3, "historial completo"; ADR-0016, regla 6; modelo-E12).
 *
 * La protección es de resumen-y-confirmacion: los triggers BEFORE UPDATE y BEFORE DELETE de pedido_eventos, con la
 * función impedir_cambio(). Esta prueba la comprueba sobre el historial de un pedido que la lavandería movió, con el
 * modelo PedidoEvento, que solo crea. La lavandería acepta el pedido y lo mueve con sus botones, la operación real de la
 * tarea 2.1 (EstadosDePrueba::aceptarComoLaLavanderia() y marcarComoLaLavanderia()).
 *
 * El estado anterior de un cambio no se guarda: es el del evento previo del mismo pedido, en el orden de ocurrido_en y
 * de id_pedido_eventos (design.md, "Tablas y migración").
 *
 * Escenario: estados-E14 · Spec: openspec/changes/estados-del-pedido/specs/estados/spec.md · Tarea 0.1
 */
class HistorialTest extends TestCase
{
    use AgendaDePrueba;
    use EstadosDePrueba;
    use LavanderiasDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    /** SQLSTATE restrict_violation: el que lanza impedir_cambio(). */
    private const CAMBIO_IMPEDIDO = '23001';

    private Pedido $pedido;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararPedido();

        // El pedido se confirma a las 09:30; la lavandería lo acepta a las 09:45, lo pasa a Recolectado a las 10:00 y a
        // En proceso a las 10:30.
        $this->pedido = $this->pedidoConfirmadoDe($this->cuentaDeUsuario());
        $this->elJuevesALas('09:45:00');
        $this->aceptarComoLaLavanderia($this->pedido);
        $this->elJuevesALas('10:00:00');
        $this->marcarComoLaLavanderia($this->pedido, EstadoPedido::RECOLECTADO);
        $this->elJuevesALas('10:30:00');
        $this->marcarComoLaLavanderia($this->pedido, EstadoPedido::EN_PROCESO);
    }

    #[TestDox('estados-E14: hay un evento por cada cambio de la lavandería, en orden y después de los anteriores del pedido, con su estado, su hora y su cuenta; su estado anterior es el del evento previo')]
    public function test_estados_e14_cada_cambio_queda_en_el_historial_en_orden(): void
    {
        $historial = $this->historial();

        // Los dos de antes, del pedido al nacer y al confirmarse, y después los tres de la lavandería: su aceptación y
        // sus dos botones.
        $this->assertSame(
            [
                EstadoPedido::POR_PAGAR,
                EstadoPedido::CONFIRMADO,
                EstadoPedido::ACEPTADO,
                EstadoPedido::RECOLECTADO,
                EstadoPedido::EN_PROCESO,
            ],
            $historial->map(fn (PedidoEvento $evento): string => $evento->estado->codigo)->all(),
        );

        [, $confirmacion, $aceptacion, $recolectado, $enProceso] = $historial->all();
        $lavanderia = $this->cuentaDeLaLavanderia($this->pedido);

        foreach ([
            [$aceptacion, '09:45:00', $confirmacion],
            [$recolectado, '10:00:00', $aceptacion],
            [$enProceso, '10:30:00', $recolectado],
        ] as [$evento, $hora, $previo]) {
            $this->assertSame($this->pedido->id_plazas, $evento->id_plazas);
            $this->assertSame($lavanderia->getKey(), $evento->id_usuarios);
            $this->assertTrue(
                $evento->ocurrido_en->eq(CarbonImmutable::parse(self::JUEVES_DE_PRUEBA.' '.$hora, self::ZONA_HORARIA)),
                "El evento {$evento->estado->codigo} debió ocurrir a las {$hora}.",
            );
            // El estado anterior es el del evento previo del mismo pedido.
            $this->assertSame($previo->id_estados_pedido, $this->estadoAnterior($evento)?->id_estados_pedido);
        }

        // El estado actual del pedido es el del último evento.
        $this->assertSame($enProceso->id_estados_pedido, $this->pedido->fresh()->id_estados_pedido);
    }

    #[TestDox('estados-E14: el historial solo crece: los cambios de la lavandería no tocan los eventos anteriores del pedido')]
    public function test_estados_e14_los_cambios_no_tocan_los_eventos_anteriores(): void
    {
        $antes = $this->filasDelHistorial();

        $this->elJuevesALas('11:00:00');
        $this->marcarComoLaLavanderia($this->pedido, EstadoPedido::LISTO);

        $despues = $this->filasDelHistorial();
        $this->assertCount(count($antes) + 1, $despues);
        $this->assertSame($antes, array_slice($despues, 0, count($antes)));
    }

    #[TestDox('estados-E14: la base rechaza editar cualquiera de los eventos, por el modelo PedidoEvento o por una consulta, y el historial queda como estaba (modelo-E12)')]
    public function test_estados_e14_la_base_rechaza_editar_cualquiera_de_los_eventos(): void
    {
        $antes = $this->filasDelHistorial();
        $listo = EstadoPedido::query()->where('codigo', EstadoPedido::LISTO)->sole();
        // Con el reloj después del último evento, cada intento cambia algo y manda el UPDATE: con el reloj a la hora del
        // evento, touch() no tendría nada que escribir y Eloquent no tocaría la base.
        $this->elJuevesALas('11:00:00');

        foreach ($this->historial() as $evento) {
            $this->assertCambioImpedido(fn () => $evento->update(['id_estados_pedido' => $listo->getKey()]), 'UPDATE');
            $this->assertCambioImpedido(
                fn () => $evento->forceFill(['ocurrido_en' => CarbonImmutable::now()->utc()])->save(),
                'UPDATE',
            );
            $this->assertCambioImpedido(fn () => $evento->fresh()->touch(), 'UPDATE');
        }

        $this->assertCambioImpedido(
            fn () => PedidoEvento::query()->where('id_pedidos', $this->pedido->getKey())->update(['motivo' => 'Editado']),
            'UPDATE',
        );

        $this->assertSame($antes, $this->filasDelHistorial());
    }

    #[TestDox('estados-E14: la base rechaza borrar cualquiera de los eventos, por el modelo PedidoEvento o por una consulta, y el historial queda como estaba (modelo-E12)')]
    public function test_estados_e14_la_base_rechaza_borrar_cualquiera_de_los_eventos(): void
    {
        $antes = $this->filasDelHistorial();

        foreach ($this->historial() as $evento) {
            $this->assertCambioImpedido(fn () => $evento->delete(), 'DELETE');
            $this->assertCambioImpedido(fn () => PedidoEvento::destroy($evento->getKey()), 'DELETE');
        }

        $this->assertCambioImpedido(
            fn () => PedidoEvento::query()->where('id_pedidos', $this->pedido->getKey())->delete(),
            'DELETE',
        );

        $this->assertSame($antes, $this->filasDelHistorial());
    }

    /**
     * El historial del pedido como lo lee la API: en el orden de ocurrido_en y de id_pedido_eventos.
     *
     * @return Collection<int, PedidoEvento>
     */
    private function historial(): Collection
    {
        return PedidoEvento::query()
            ->where('id_pedidos', $this->pedido->getKey())
            ->with('estado')
            ->orderBy('ocurrido_en')
            ->orderBy('id_pedido_eventos')
            ->get();
    }

    /**
     * El evento previo del mismo pedido, en el orden del historial; null si es el primero.
     */
    private function estadoAnterior(PedidoEvento $evento): ?PedidoEvento
    {
        $historial = $this->historial()->values();
        $posicion = $historial->search(fn (PedidoEvento $otro): bool => $otro->is($evento));

        return $posicion > 0 ? $historial[$posicion - 1] : null;
    }

    /**
     * Las filas del historial tal como están en la base, todas sus columnas, para comparar antes y después.
     *
     * @return list<array<string, mixed>>
     */
    private function filasDelHistorial(): array
    {
        return DB::table('pedido_eventos')
            ->where('id_pedidos', $this->pedido->getKey())
            ->orderBy('id_pedido_eventos')
            ->get()
            ->map(fn (object $fila): array => (array) $fila)
            ->all();
    }

    /**
     * La operación falla con el error de impedir_cambio() sobre pedido_eventos. Corre en una transacción anidada, un
     * savepoint, para que el error no aborte la transacción de la prueba.
     */
    private function assertCambioImpedido(callable $operacion, string $sentencia): void
    {
        try {
            DB::transaction($operacion);
            $this->fail("La base debió rechazar {$sentencia} sobre pedido_eventos.");
        } catch (QueryException $error) {
            $this->assertSame(self::CAMBIO_IMPEDIDO, $error->getCode(), $error->getMessage());
            $this->assertStringContainsString("impedir_cambio: {$sentencia} sobre pedido_eventos", $error->getMessage());
        }
    }
}
