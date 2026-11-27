<?php

namespace App\Exportacion\Exportadores;

use App\Exportacion\ExportadorDeIndicador;
use App\Indicadores\PedidosPorZona;
use App\Indicadores\Rango;

/**
 * La descarga del mapa de calor por zona de servicio (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5, F-I11;
 * exportacion-E03): la tabla del widget del mapa cuando se ve por zona (App\Filament\Widgets\Indicadores\MapaDeCalor),
 * de la misma consulta. Lleva la zona, su plaza y sus pedidos; el polígono y la intensidad son para dibujar el mapa y no
 * van en el archivo, como no van en la tabla.
 */
class PedidosPorZonaExportador extends ExportadorDeIndicador
{
    public const NOMBRE = 'pedidos-por-zona';

    public static function titulo(): string
    {
        return 'Pedidos por zona de servicio';
    }

    protected static function columnas(): array
    {
        return ['zona' => 'Zona de servicio', 'plaza' => 'Plaza', 'pedidos' => 'Pedidos'];
    }

    protected static function columnasNumericas(): array
    {
        return ['pedidos'];
    }

    protected function filas(Rango $rango): iterable
    {
        return app(PedidosPorZona::class)->delRango($rango);
    }
}
