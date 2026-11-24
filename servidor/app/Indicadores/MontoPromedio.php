<?php

namespace App\Indicadores;

use Illuminate\Support\Facades\DB;

/**
 * El indicador de monto promedio por pedido, con su tendencia (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5, categoría
 * Usuarios; NSV-2026-001-v8.pdf:46:ANEXO D, Sección D.5, monto promedio por pedido; indicadores-E09).
 *
 * - El periodo es el rango de la página, con sus dos días; el anterior, los mismos días justo antes (design.md, lectura
 *   4). Pedido del periodo es el que llegó a Confirmado en él, en el día de su plaza, como en PedidosTotales (lectura 1).
 * - El monto de cada pedido es el final si ya se pesó y, si no, el estimado: la misma lectura de la ficha del usuario en
 *   el panel (openspec/changes/administracion/design.md, "El panel"). Qué monto cuenta lo fija el modelo de cobro
 *   (DD-012; indicadores-E10, bloqueado): esta lectura no lo afirma y cambia aquí cuando se responda. Un pedido sin
 *   ningún monto no entra al promedio.
 * - Los montos viajan como texto con dos decimales, en pesos: el dinero nunca se pasa por un flotante.
 *
 * Es la consulta del indicador: el widget y la descarga de exportacion leen de aquí.
 */
final class MontoPromedio
{
    /** El monto de un pedido, mientras DD-012 no diga cuál cuenta. */
    private const MONTO_DEL_PEDIDO = 'coalesce(pedidos.monto_final, pedidos.monto_estimado)';

    /**
     * El promedio del periodo y el del anterior, con dos decimales, su diferencia y la tendencia. Cada cifra es null si
     * su periodo no tiene pedidos con monto; la diferencia y la tendencia, si falta alguno de los dos.
     *
     * @return array{promedio: string|null, promedio_anterior: string|null, diferencia: string|null, tendencia: Tendencia|null}
     */
    public function delRango(Rango $rango): array
    {
        $anterior = $rango->anteriorDeLaMismaDuracion();
        $promedio = 'round(avg('.self::MONTO_DEL_PEDIDO.') filter (where '.PedidosTotales::FECHA_DEL_PEDIDO.' between ? and ?), 2)';

        $fila = DB::table('pedidos')
            ->join('plazas', 'plazas.id_plazas', '=', 'pedidos.id_plazas')
            ->whereNotNull('pedidos.confirmado_en')
            ->whereRaw(self::MONTO_DEL_PEDIDO.' is not null')
            ->selectRaw("{$promedio} as promedio", [$rango->desde->toDateString(), $rango->hasta->toDateString()])
            ->selectRaw("{$promedio} as promedio_anterior", [$anterior->desde->toDateString(), $anterior->hasta->toDateString()])
            ->first();

        $actual = $fila->promedio === null ? null : (string) $fila->promedio;
        $previo = $fila->promedio_anterior === null ? null : (string) $fila->promedio_anterior;

        if ($actual === null || $previo === null) {
            return ['promedio' => $actual, 'promedio_anterior' => $previo, 'diferencia' => null, 'tendencia' => null];
        }

        $centavos = self::centavos($actual) - self::centavos($previo);

        return [
            'promedio' => $actual,
            'promedio_anterior' => $previo,
            'diferencia' => self::pesos($centavos),
            'tendencia' => Tendencia::deLaDiferencia($centavos),
        ];
    }

    /**
     * Un monto con dos decimales, en centavos.
     */
    private static function centavos(string $monto): int
    {
        return (int) round(((float) $monto) * 100);
    }

    /**
     * Unos centavos como monto con dos decimales: "50.00", "-100.00".
     */
    private static function pesos(int $centavos): string
    {
        return ($centavos < 0 ? '-' : '').intdiv(abs($centavos), 100).'.'.str_pad((string) (abs($centavos) % 100), 2, '0', STR_PAD_LEFT);
    }
}
