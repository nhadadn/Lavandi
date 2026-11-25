<?php

namespace App\Exportacion\Exportadores;

use App\Exportacion\ExportadorDeIndicador;
use App\Indicadores\PedidosTotales;
use App\Indicadores\Rango;

/**
 * La descarga de los pedidos totales por día, semana o mes, cada periodo junto al anterior (NSV-2026-001-v8.pdf:22:
 * ANEXO A, Sección A.5, F-I01; exportacion-E01 y E02): las columnas y las cifras de la tabla comparativa del panel
 * (App\Filament\Widgets\Indicadores\PedidosTotalesTabla), de la misma consulta.
 */
class PedidosTotalesExportador extends ExportadorDeIndicador
{
    public const NOMBRE = 'pedidos-totales';

    public static function titulo(): string
    {
        return 'Pedidos totales';
    }

    protected static function columnas(): array
    {
        return [
            'periodo' => 'Periodo',
            'pedidos' => 'Pedidos',
            'periodo_anterior' => 'Periodo anterior',
            'pedidos_anterior' => 'Pedidos del periodo anterior',
        ];
    }

    protected static function columnasNumericas(): array
    {
        return ['pedidos', 'pedidos_anterior'];
    }

    protected function filas(Rango $rango): iterable
    {
        return app(PedidosTotales::class)->porPeriodo($rango);
    }
}
