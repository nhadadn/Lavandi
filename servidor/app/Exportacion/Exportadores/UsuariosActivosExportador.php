<?php

namespace App\Exportacion\Exportadores;

use App\Exportacion\ExportadorDeIndicador;
use App\Indicadores\Rango;
use App\Indicadores\UsuariosActivos;

/**
 * La descarga de los usuarios activos por día, semana o mes (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5, F-I03;
 * exportacion-E03): las personas distintas de cada periodo de la línea del panel (App\Filament\Widgets\Indicadores\
 * UsuariosActivosGrafica), de la misma consulta.
 */
class UsuariosActivosExportador extends ExportadorDeIndicador
{
    public const NOMBRE = 'usuarios-activos';

    public static function titulo(): string
    {
        return 'Usuarios activos';
    }

    protected static function columnas(): array
    {
        return ['periodo' => 'Periodo', 'personas' => 'Usuarios activos'];
    }

    protected static function columnasNumericas(): array
    {
        return ['personas'];
    }

    protected function filas(Rango $rango): iterable
    {
        return app(UsuariosActivos::class)->porPeriodo($rango);
    }
}
