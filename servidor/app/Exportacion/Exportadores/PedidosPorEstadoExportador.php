<?php

namespace App\Exportacion\Exportadores;

use App\Exportacion\ExportadorDeIndicador;
use App\Indicadores\PedidosPorEstado;
use App\Indicadores\Rango;

/**
 * La descarga de los pedidos del rango por estado, activos y completados (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5,
 * F-I02; exportacion-E03): las dos cifras de la dona del panel (App\Filament\Widgets\Indicadores\
 * PedidosPorEstadoGrafica), de la misma consulta. Los cancelados esperan DD-016 (indicadores-E05).
 */
class PedidosPorEstadoExportador extends ExportadorDeIndicador
{
    public const NOMBRE = 'pedidos-por-estado';

    public static function titulo(): string
    {
        return 'Pedidos por estado';
    }

    protected static function columnas(): array
    {
        return ['estado' => 'Estado', 'pedidos' => 'Pedidos'];
    }

    protected static function columnasNumericas(): array
    {
        return ['pedidos'];
    }

    protected function filas(Rango $rango): iterable
    {
        $cifras = app(PedidosPorEstado::class)->delRango($rango);

        return [
            ['estado' => 'Activos', 'pedidos' => $cifras['activos']],
            ['estado' => 'Completados', 'pedidos' => $cifras['completados']],
        ];
    }
}
