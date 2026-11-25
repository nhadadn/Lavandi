<?php

namespace App\Exportacion\Exportadores;

use App\Exportacion\ExportadorDeIndicador;
use App\Finanzas\ConciliacionConLaPasarela;
use App\Indicadores\Rango;

/**
 * La descarga de la conciliación con la pasarela (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #6;
 * exportacion-E03; administracion-E20 y E21): la tabla de la página de conciliación (App\Filament\Pages\Conciliacion),
 * de la misma consulta, App\Finanzas\ConciliacionConLaPasarela, con el resultado por su texto.
 *
 * - Se pide desde la página, después de conciliar, y lleva el periodo conciliado: no tiene formulario propio. Así el
 *   archivo trae justo lo que muestra la tabla.
 * - No vuelve a leer la pasarela: escribe lo que ya se leyó al conciliar (segunda adenda del 2026-11-22 de ADR-0010,
 *   decisión 7: se lee cuando la administración concilia).
 * - Los montos van en pesos con dos decimales; el que no hay, vacío.
 */
class ConciliacionExportador extends ExportadorDeIndicador
{
    public const NOMBRE = 'conciliacion-con-la-pasarela';

    public static function titulo(): string
    {
        return 'Conciliación con la pasarela';
    }

    /**
     * Sin formulario: el periodo es el que se concilió en la página.
     *
     * @return array{}
     */
    public static function getOptionsFormComponents(): array
    {
        return [];
    }

    protected static function columnas(): array
    {
        return [
            'resultado' => 'Resultado',
            'orden' => 'Orden en la pasarela',
            'folio' => 'Folio',
            'plaza' => 'Plaza',
            'fecha' => 'Fecha del pago',
            'monto_plataforma' => 'Monto en la plataforma',
            'monto_pasarela' => 'Monto en la pasarela',
        ];
    }

    protected static function columnasNumericas(): array
    {
        return ['monto_plataforma', 'monto_pasarela'];
    }

    protected function filas(Rango $rango): iterable
    {
        return app(ConciliacionConLaPasarela::class)->reporte($rango);
    }
}
