<?php

namespace App\Exportacion\Exportadores;

use App\Exportacion\ExportadorDeIndicador;
use App\Indicadores\MontoPromedio;
use App\Indicadores\Rango;

/**
 * La descarga del monto promedio por pedido con su tendencia (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5, F-I05;
 * exportacion-E03): una fila con lo que dice el indicador destacado del panel (App\Filament\Widgets\Indicadores\
 * MontoPromedioIndicador), de la misma consulta: el periodo y su promedio, el periodo anterior y el suyo, la diferencia
 * con su signo y la tendencia. Los montos van en pesos, con dos decimales; sin pedidos con monto, vacíos.
 *
 * Qué monto cuenta espera DD-012 (indicadores-E10).
 */
class MontoPromedioExportador extends ExportadorDeIndicador
{
    public const NOMBRE = 'monto-promedio-por-pedido';

    public static function titulo(): string
    {
        return 'Monto promedio por pedido';
    }

    protected static function columnas(): array
    {
        return [
            'periodo' => 'Periodo',
            'promedio' => 'Monto promedio por pedido',
            'periodo_anterior' => 'Periodo anterior',
            'promedio_anterior' => 'Monto promedio del periodo anterior',
            'diferencia' => 'Diferencia',
            'tendencia' => 'Tendencia',
        ];
    }

    protected static function columnasNumericas(): array
    {
        return ['promedio', 'promedio_anterior', 'diferencia'];
    }

    protected function filas(Rango $rango): iterable
    {
        return [[
            'periodo' => $rango->dias(),
            'periodo_anterior' => $rango->anteriorDeLaMismaDuracion()->dias(),
            ...app(MontoPromedio::class)->delRango($rango),
        ]];
    }
}
