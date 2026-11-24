<?php

namespace App\Finanzas;

use App\Cobro\PasarelaNoDisponible;
use App\Cobro\Pasarelas;
use App\Indicadores\Rango;
use App\Models\MovimientoPasarela;
use App\Models\Pago;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/**
 * La conciliación de un periodo: los pagos que registró la plataforma contra los movimientos de la pasarela
 * (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #6, "conciliación con Conekta"; administracion-E20 y E21;
 * segunda adenda del 2026-11-22 de ADR-0010, decisiones 6 y 7).
 *
 * - Conciliar lee de la pasarela configurada, por la interfaz neutral del cobro, los pagos que acreditó en el periodo, y
 *   los guarda en movimientos_pasarela, uno por orden: leer dos veces el mismo periodo no repite ninguno. Se lee cuando
 *   la administración concilia, no con una tarea programada.
 * - El reporte cruza los dos lados por la orden de la pasarela, (pasarela, id_pasarela_orden), con lo ya guardado:
 *   - cada pago que la plataforma tiene como pagado en el periodo, los mismos que cuentan como ingreso
 *     (App\Finanzas\Ingresos), queda conciliado si la pasarela tiene su movimiento con el mismo monto; con otro monto,
 *     el monto no coincide; sin movimiento, es un pedido pagado sin movimiento;
 *   - cada movimiento del periodo que no cuadra con ningún pago pagado de la plataforma es un movimiento sin pedido.
 *     También el de una orden cuyo cobro la plataforma no tiene como pagado, porque su aviso no llegó: no hay pedido
 *     pagado con el que cuadre.
 * - La plaza de un movimiento es la del pedido con el que cuadra; uno sin pedido no tiene plaza.
 * - El día de un pago es el de su confirmación en la hora de su plaza, como en los ingresos. El de un movimiento sin
 *   pedido, que no tiene plaza, es el de su fecha en la pasarela en la zona horaria de la aplicación, como el "hoy" de
 *   los indicadores (openspec/changes/indicadores/design.md, pregunta 8). Un pago y su movimiento cuadran aunque la
 *   pasarela lo feche un poco antes de que llegue su aviso: cuentan en el periodo del pago.
 * - Los montos se comparan como los guarda la base, con dos decimales. La comisión de la pasarela se guarda, pero
 *   mostrarla y descontarla espera a saber quién la absorbe (administracion-E22, DD-013).
 *
 * Es la única fuente de la conciliación: la página del panel y su descarga leen de aquí.
 */
final class ConciliacionConLaPasarela
{
    public function __construct(private readonly Pasarelas $pasarelas) {}

    /**
     * Lee de la pasarela configurada sus pagos del periodo, los guarda y devuelve el reporte del periodo.
     *
     * Se le pide a la pasarela un día más a cada lado del periodo, en UTC: así entran los pagos de cualquier plaza, sea
     * cual sea su zona horaria, y el reporte decide después el día de cada uno. Lo que la pasarela devuelva fuera de ese
     * lapso no se guarda: el corte por fecha lo hace el servidor.
     *
     * @return array<string, array{resultado: ResultadoDeConciliacion, orden: string, folio: string|null, plaza: string|null, fecha: string, monto_plataforma: string|null, monto_pasarela: string|null}>
     *
     * @throws PasarelaNoDisponible si no hay pasarela en este entorno, le falta su llave o no responde
     */
    public function conciliar(Rango $rango): array
    {
        $pasarela = $this->pasarelas->configurada();
        $desde = CarbonImmutable::parse($rango->desde->toDateString(), 'UTC')->subDay();
        $hasta = CarbonImmutable::parse($rango->hasta->toDateString(), 'UTC')->addDays(2)->subMicrosecond();
        $filas = [];

        foreach ($pasarela->pagosDelPeriodo($desde, $hasta) as $movimiento) {
            if (! $movimiento->pagadoEn->betweenIncluded($desde, $hasta)) {
                continue;
            }

            $filas[] = [
                'pasarela' => $pasarela->codigo(),
                'id_pasarela_orden' => $movimiento->idOrden,
                'monto' => $movimiento->monto,
                'comision' => $movimiento->comision,
                // Con su zona, para que la base no lo lea en la de su sesión.
                'pagado_en' => $movimiento->pagadoEn->utc()->format('Y-m-d H:i:s.uP'),
            ];
        }

        // Una fila por orden: si ya estaba, toma lo que dice la pasarela ahora.
        if ($filas !== []) {
            MovimientoPasarela::query()->upsert($filas, ['pasarela', 'id_pasarela_orden'], ['monto', 'comision', 'pagado_en']);
        }

        return $this->reporte($rango);
    }

    /**
     * El reporte del periodo con lo que ya se leyó de la pasarela, sin volver a pedirle nada: una fila por pago y por
     * movimiento, con la clave "pasarela|orden", del más antiguo al más nuevo. Cada una con su resultado, la orden, el
     * folio y la plaza del pedido si lo hay, la fecha del pago (d/m/Y H:i, en la hora de la plaza; la de la aplicación
     * si no tiene pedido) y los dos montos, el de la plataforma y el de la pasarela, el que haya.
     *
     * @return array<string, array{resultado: ResultadoDeConciliacion, orden: string, folio: string|null, plaza: string|null, fecha: string, monto_plataforma: string|null, monto_pasarela: string|null}>
     */
    public function reporte(Rango $rango): array
    {
        $dias = [$rango->desde->toDateString(), $rango->hasta->toDateString()];
        $filas = [];

        foreach ($this->pagosDelPeriodo($dias) as $pago) {
            $filas[] = [
                'instante' => CarbonImmutable::parse($pago->pagado_en),
                'clave' => "{$pago->pasarela}|{$pago->id_pasarela_orden}",
                'resultado' => match (true) {
                    $pago->monto_pasarela === null => ResultadoDeConciliacion::PedidoSinMovimiento,
                    $pago->monto_pasarela !== $pago->monto_plataforma => ResultadoDeConciliacion::MontoDistinto,
                    default => ResultadoDeConciliacion::Conciliado,
                },
                'orden' => (string) $pago->id_pasarela_orden,
                'folio' => $pago->folio === null ? null : (string) $pago->folio,
                'plaza' => (string) $pago->plaza,
                'fecha' => CarbonImmutable::parse($pago->pagado_en)->setTimezone($pago->zona_horaria)->format('d/m/Y H:i'),
                'monto_plataforma' => (string) $pago->monto_plataforma,
                'monto_pasarela' => $pago->monto_pasarela === null ? null : (string) $pago->monto_pasarela,
            ];
        }

        foreach ($this->movimientosSinPedido($dias) as $movimiento) {
            $filas[] = [
                'instante' => CarbonImmutable::parse($movimiento->pagado_en),
                'clave' => "{$movimiento->pasarela}|{$movimiento->id_pasarela_orden}",
                'resultado' => ResultadoDeConciliacion::MovimientoSinPedido,
                'orden' => (string) $movimiento->id_pasarela_orden,
                'folio' => null,
                'plaza' => null,
                'fecha' => CarbonImmutable::parse($movimiento->pagado_en)->setTimezone(self::zonaSinPlaza())->format('d/m/Y H:i'),
                'monto_plataforma' => null,
                'monto_pasarela' => (string) $movimiento->monto,
            ];
        }

        usort($filas, fn (array $una, array $otra): int => [$una['instante'], $una['orden']] <=> [$otra['instante'], $otra['orden']]);

        $reporte = [];

        foreach ($filas as $fila) {
            $clave = $fila['clave'];
            unset($fila['instante'], $fila['clave']);
            $reporte[$clave] = $fila;
        }

        return $reporte;
    }

    /**
     * Cuántas filas del reporte hay de cada resultado, en el orden del enum, con los que no tienen ninguna.
     *
     * @param  array<string, array{resultado: ResultadoDeConciliacion}>  $reporte
     * @return array<string, int>
     */
    public static function resumen(array $reporte): array
    {
        $resumen = array_fill_keys(array_map(fn (ResultadoDeConciliacion $resultado): string => $resultado->value, ResultadoDeConciliacion::cases()), 0);

        foreach ($reporte as $fila) {
            $resumen[$fila['resultado']->value]++;
        }

        return $resumen;
    }

    /**
     * Los pagos que la plataforma tiene como pagados en esos días, con su pedido, su plaza y el movimiento con el que
     * cuadran, si lo hay.
     *
     * @param  array{0: string, 1: string}  $dias
     * @return iterable<object>
     */
    private function pagosDelPeriodo(array $dias): iterable
    {
        return Ingresos::pagosConfirmados()
            ->join('pedidos', 'pedidos.id_pedidos', '=', 'pagos.id_pedidos')
            ->leftJoin('movimientos_pasarela', fn (JoinClause $cuadra) => $cuadra
                ->on('movimientos_pasarela.pasarela', '=', 'pagos.pasarela')
                ->on('movimientos_pasarela.id_pasarela_orden', '=', 'pagos.id_pasarela_orden'))
            ->whereRaw(Ingresos::FECHA_DEL_INGRESO.' between ? and ?', $dias)
            ->select('pagos.pasarela', 'pagos.id_pasarela_orden', 'pagos.pagado_en', 'pedidos.folio')
            ->addSelect('plazas.nombre as plaza', 'plazas.zona_horaria')
            ->addSelect('pagos.monto as monto_plataforma', 'movimientos_pasarela.monto as monto_pasarela')
            ->get();
    }

    /**
     * Los movimientos guardados con fecha en esos días que no cuadran con ningún pago pagado de la plataforma.
     *
     * @param  array{0: string, 1: string}  $dias
     * @return iterable<object>
     */
    private function movimientosSinPedido(array $dias): iterable
    {
        return DB::table('movimientos_pasarela')
            ->whereRaw('(movimientos_pasarela.pagado_en at time zone ?)::date between ? and ?', [self::zonaSinPlaza(), ...$dias])
            ->whereNotExists(fn ($pagos) => $pagos->selectRaw('1')
                ->from('pagos')
                ->whereColumn('pagos.pasarela', 'movimientos_pasarela.pasarela')
                ->whereColumn('pagos.id_pasarela_orden', 'movimientos_pasarela.id_pasarela_orden')
                ->where('pagos.estado', Pago::PAGADO))
            ->select('pasarela', 'id_pasarela_orden', 'monto', 'pagado_en')
            ->get();
    }

    /**
     * La zona horaria de un movimiento sin pedido: la de la aplicación, porque no tiene plaza.
     */
    private static function zonaSinPlaza(): string
    {
        return (string) config('app.timezone');
    }
}
