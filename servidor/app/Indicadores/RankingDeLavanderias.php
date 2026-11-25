<?php

namespace App\Indicadores;

use Illuminate\Support\Facades\DB;

/**
 * El indicador de calificación promedio por lavandería, en ranking (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5,
 * categoría Socios; indicadores-E12).
 *
 * - El promedio es el de todas las calificaciones de la lavandería: las filas de calificaciones con su id_lavanderias
 *   (design.md, "Tablas y migración"), con un decimal, como lo ve su perfil. No depende del rango de la página.
 * - Van de mayor a menor promedio; con el mismo promedio, por nombre. Las que no tienen calificaciones van al final,
 *   sin lugar ni promedio.
 * - Entran todas las lavanderías que no se borraron, de todas las plazas: qué lavandería cuenta como activa es DD-083
 *   (indicadores-E11).
 * - Cada promedio lleva el color del semáforo, con los dos cortes de la configuración (indicadores-E13).
 */
final class RankingDeLavanderias
{
    /**
     * Una fila por lavandería, con la clave de su id_publico, en el orden del ranking: su lugar, su nombre, su promedio y
     * el color de su semáforo. El lugar y el promedio son null si no tiene calificaciones; el semáforo, también si los
     * cortes de la configuración no sirven (CortesDelSemaforo).
     *
     * @return array<string, array{lugar: int|null, lavanderia: string, promedio: float|null, semaforo: Semaforo|null}>
     */
    public function calcular(): array
    {
        $cortes = CortesDelSemaforo::deLaConfiguracion();

        $calificaciones = DB::table('calificaciones')
            ->select('id_lavanderias')
            ->selectRaw('avg(estrellas) as promedio')
            ->whereNotNull('id_lavanderias')
            ->groupBy('id_lavanderias');

        $filas = DB::table('lavanderias')
            ->leftJoinSub($calificaciones, 'resumen', 'resumen.id_lavanderias', '=', 'lavanderias.id_lavanderias')
            ->whereNull('lavanderias.deleted_at')
            ->select('lavanderias.id_publico', 'lavanderias.nombre')
            ->selectRaw('round(resumen.promedio, 1) as promedio')
            ->orderByRaw('resumen.promedio desc nulls last')
            ->orderBy('lavanderias.nombre')
            ->orderBy('lavanderias.id_lavanderias')
            ->get();

        $ranking = [];
        $lugar = 0;

        foreach ($filas as $fila) {
            $promedio = $fila->promedio === null ? null : (float) $fila->promedio;

            $ranking[$fila->id_publico] = [
                'lugar' => $promedio === null ? null : ++$lugar,
                'lavanderia' => $fila->nombre,
                'promedio' => $promedio,
                // El color se toma del promedio con un decimal: el mismo número que ve la administración.
                'semaforo' => $promedio === null ? null : $cortes?->color($promedio),
            ];
        }

        return $ranking;
    }
}
