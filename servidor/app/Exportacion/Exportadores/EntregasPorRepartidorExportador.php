<?php

namespace App\Exportacion\Exportadores;

use App\Exportacion\ExportadorDeIndicador;
use App\Indicadores\EntregasPorRepartidor;
use App\Indicadores\Rango;

/**
 * La descarga de las entregas completadas por repartidor en el rango (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5,
 * F-I08; exportacion-E03): las columnas y las cifras de la tabla del panel
 * (App\Filament\Widgets\Indicadores\EntregasPorRepartidorTabla), de la misma consulta. Un repartidor sin plaza deja esa
 * celda vacía.
 */
class EntregasPorRepartidorExportador extends ExportadorDeIndicador
{
    public const NOMBRE = 'entregas-por-repartidor';

    public static function titulo(): string
    {
        return 'Entregas completadas por repartidor';
    }

    protected static function columnas(): array
    {
        return ['repartidor' => 'Repartidor', 'plaza' => 'Plaza', 'entregas' => 'Entregas completadas'];
    }

    protected static function columnasNumericas(): array
    {
        return ['entregas'];
    }

    protected function filas(Rango $rango): iterable
    {
        return app(EntregasPorRepartidor::class)->delRango($rango);
    }
}
