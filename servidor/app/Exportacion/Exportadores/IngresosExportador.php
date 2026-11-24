<?php

namespace App\Exportacion\Exportadores;

use App\Exportacion\ExportadorDeIndicador;
use App\Finanzas\Ingresos;
use App\Indicadores\Rango;

/**
 * La descarga del reporte financiero de ingresos por periodo (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #6;
 * exportacion-E03; administracion-E17): la tabla de la página de ingresos (App\Filament\Widgets\Finanzas\
 * IngresosPorPeriodoTabla), de la misma consulta, App\Finanzas\Ingresos, y al final el total del periodo, la cifra que
 * la página muestra arriba (App\Filament\Widgets\Finanzas\IngresosDelPeriodo). Los montos van en pesos con dos
 * decimales.
 */
class IngresosExportador extends ExportadorDeIndicador
{
    public const NOMBRE = 'ingresos-por-periodo';

    /** El texto del renglón del total. */
    public const TOTAL = 'Total del periodo';

    public static function titulo(): string
    {
        return 'Ingresos por periodo';
    }

    protected static function columnas(): array
    {
        return ['periodo' => 'Periodo', 'pagos' => 'Pagos confirmados', 'ingresos' => 'Ingresos'];
    }

    protected static function columnasNumericas(): array
    {
        return ['pagos', 'ingresos'];
    }

    protected function filas(Rango $rango): iterable
    {
        $ingresos = app(Ingresos::class);

        return [
            ...array_values($ingresos->porPeriodo($rango)),
            ['periodo' => self::TOTAL, ...$ingresos->delRango($rango)],
        ];
    }
}
