<?php

namespace App\Exportacion\Exportadores;

use App\Exportacion\ExportadorDeIndicador;
use App\Indicadores\Rango;
use App\Indicadores\RankingDeLavanderias;

/**
 * La descarga del ranking de calificación promedio por lavandería, con su semáforo (NSV-2026-001-v8.pdf:22:ANEXO A,
 * Sección A.5, F-I07; exportacion-E03): las columnas y las cifras de la tabla del panel
 * (App\Filament\Widgets\Indicadores\RankingDeLavanderiasTabla), de la misma consulta.
 *
 * - El promedio va con un decimal, como lo ve el panel; el color del semáforo, por su texto (Verde, Amarillo, Rojo).
 * - Una lavandería sin calificaciones va sin lugar, sin promedio y sin color; sin cortes en la configuración (DD-114),
 *   ninguna lleva color. El panel lo dice con texto; el archivo deja la celda vacía.
 * - Como en el panel, el ranking no depende del rango: promedia todas las calificaciones de cada lavandería. El rango
 *   solo nombra el archivo.
 */
class RankingDeLavanderiasExportador extends ExportadorDeIndicador
{
    public const NOMBRE = 'calificacion-por-lavanderia';

    public static function titulo(): string
    {
        return 'Calificación promedio por lavandería';
    }

    protected static function columnas(): array
    {
        return [
            'lugar' => 'Lugar',
            'lavanderia' => 'Lavandería',
            'promedio' => 'Calificación promedio',
            'semaforo' => 'Semáforo',
        ];
    }

    protected static function columnasNumericas(): array
    {
        return ['lugar', 'promedio'];
    }

    protected function filas(Rango $rango): iterable
    {
        return array_map(fn (array $fila): array => [
            ...$fila,
            'promedio' => $fila['promedio'] === null ? null : number_format($fila['promedio'], 1, '.', ''),
        ], app(RankingDeLavanderias::class)->calcular());
    }
}
