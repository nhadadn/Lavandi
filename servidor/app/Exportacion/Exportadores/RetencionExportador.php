<?php

namespace App\Exportacion\Exportadores;

use App\Exportacion\ExportadorDeIndicador;
use App\Indicadores\Rango;
use App\Indicadores\RetencionPorMesDeRegistro;

/**
 * La descarga de los usuarios que vuelven a pedir, por mes de registro (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5,
 * F-I04; exportacion-E03): la tabla de cohortes del panel (App\Filament\Widgets\Indicadores\RetencionTabla), de la
 * misma consulta. El porcentaje va como número, con su decimal; el de un mes sin cuentas que hayan pedido, vacío.
 */
class RetencionExportador extends ExportadorDeIndicador
{
    public const NOMBRE = 'usuarios-que-vuelven-a-pedir';

    public static function titulo(): string
    {
        return 'Usuarios que vuelven a pedir';
    }

    protected static function columnas(): array
    {
        return [
            'mes' => 'Mes de registro',
            'con_primer_pedido' => 'Con primer pedido',
            'porcentaje' => 'Volvieron a pedir (%)',
        ];
    }

    protected static function columnasNumericas(): array
    {
        return ['con_primer_pedido', 'porcentaje'];
    }

    protected function filas(Rango $rango): iterable
    {
        return app(RetencionPorMesDeRegistro::class)->porMes($rango);
    }
}
