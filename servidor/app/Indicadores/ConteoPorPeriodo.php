<?php

namespace App\Indicadores;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;

/**
 * El conteo que comparten los indicadores por día, semana y mes: agrupa las filas de una consulta por el primer día de
 * su periodo y las cuenta.
 *
 * La consulta trae sus tablas y sus condiciones; aquí se le agregan el rango de días, el periodo y el conteo. El día de
 * cada fila es una expresión de SQL que ya está en la hora que corresponde: la de la plaza del pedido, o la fecha de
 * actividad_usuarios.
 */
final class ConteoPorPeriodo
{
    /**
     * Lo que cuenta cada periodo con filas entre esos dos días, incluidos, por el primer día del periodo (Y-m-d). Un
     * periodo sin filas no aparece.
     *
     * @param  string  $dia  la expresión de SQL del día de cada fila, de tipo date
     * @param  string  $conteo  la expresión de SQL que cuenta: count(*), count(distinct …)
     * @return array<string, int>
     */
    public static function contar(
        Builder $consulta,
        string $dia,
        string $conteo,
        Granularidad $granularidad,
        CarbonImmutable $desde,
        CarbonImmutable $hasta,
    ): array {
        $periodo = sprintf("to_char(date_trunc('%s', %s), 'YYYY-MM-DD')", $granularidad->unidadSql(), $dia);

        return $consulta
            ->whereRaw("{$dia} between ? and ?", [$desde->toDateString(), $hasta->toDateString()])
            ->selectRaw("{$periodo} as periodo")
            ->selectRaw("{$conteo} as conteo")
            ->groupByRaw($periodo)
            ->pluck('conteo', 'periodo')
            ->map(fn (mixed $conteo): int => (int) $conteo)
            ->all();
    }
}
