<?php

namespace App\Exportacion\Exportadores;

use App\Exportacion\ExportadorDeIndicador;
use App\Indicadores\PedidosPorColonia;
use App\Indicadores\Rango;

/**
 * La descarga del mapa de calor por colonia (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5, F-I11; exportacion-E07 y
 * E08): la tabla del widget del mapa (App\Filament\Widgets\Indicadores\MapaDeCalor), de la misma consulta. La colonia
 * es texto que capturó la persona en su dirección: lleva el escape de fórmulas de las celdas de texto.
 *
 * Es parte de la tarea 1.3 de exportacion, que se adelantó porque los escenarios E07 y E08 descargan este reporte.
 */
class PedidosPorColoniaExportador extends ExportadorDeIndicador
{
    public const NOMBRE = 'pedidos-por-colonia';

    public static function titulo(): string
    {
        return 'Pedidos por colonia';
    }

    protected static function columnas(): array
    {
        return ['colonia' => 'Colonia', 'municipio' => 'Municipio', 'plaza' => 'Plaza', 'pedidos' => 'Pedidos'];
    }

    protected static function columnasNumericas(): array
    {
        return ['pedidos'];
    }

    protected function filas(Rango $rango): iterable
    {
        return app(PedidosPorColonia::class)->delRango($rango);
    }
}
