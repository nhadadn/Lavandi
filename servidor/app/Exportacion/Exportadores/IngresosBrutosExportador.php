<?php

namespace App\Exportacion\Exportadores;

use App\Exportacion\ExportadorDeIndicador;
use App\Finanzas\Ingresos;
use App\Indicadores\Rango;

/**
 * La descarga de los ingresos brutos por periodo (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5, F-I09; exportacion-E03):
 * las barras del panel (App\Filament\Widgets\Indicadores\IngresosBrutosGrafica), de la misma consulta, App\Finanzas\
 * Ingresos. Cada periodo con lo cobrado, en pesos con dos decimales. Por qué se agrupan las barras espera DD-115
 * (indicadores-E17): cuando se decida, cada grupo será otra columna.
 */
class IngresosBrutosExportador extends ExportadorDeIndicador
{
    public const NOMBRE = 'ingresos-brutos';

    public static function titulo(): string
    {
        return 'Ingresos brutos';
    }

    protected static function columnas(): array
    {
        return ['periodo' => 'Periodo', 'ingresos' => 'Ingresos brutos'];
    }

    protected static function columnasNumericas(): array
    {
        return ['ingresos'];
    }

    protected function filas(Rango $rango): iterable
    {
        return app(Ingresos::class)->porPeriodo($rango);
    }
}
