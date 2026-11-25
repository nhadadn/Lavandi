<?php

namespace App\Indicadores;

use Illuminate\Support\Facades\DB;

/**
 * El indicador de usuarios activos por día, semana y mes (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5, categoría
 * Usuarios; indicadores-E06): cuántas personas distintas usaron la aplicación en cada periodo, como lo define el
 * glosario del contrato (NSV-2026-001-v8.pdf:46:ANEXO D, Sección D.5). Una persona que la usó varios días cuenta una
 * sola vez en su semana y en su mes.
 *
 * Lee actividad_usuarios, una fila por persona y día, y cuenta todas sus filas: qué la escribe, y por eso quién cuenta
 * como activo, es DD-111 (indicadores-E07). La fecha de la fila ya es un día, sin zona horaria (design.md, pregunta 8).
 */
final class UsuariosActivos
{
    /**
     * Una fila por periodo del rango, con la clave de su primer día (Y-m-d), en orden: el periodo y sus personas.
     *
     * @return array<string, array{periodo: string, personas: int}>
     */
    public function porPeriodo(Rango $rango): array
    {
        $conteo = ConteoPorPeriodo::contar(
            DB::table('actividad_usuarios'),
            'actividad_usuarios.fecha',
            'count(distinct actividad_usuarios.id_usuarios)',
            $rango->granularidad,
            $rango->desde,
            $rango->hasta,
        );

        $filas = [];

        foreach ($rango->periodos() as $inicio) {
            $filas[$inicio->toDateString()] = [
                'periodo' => $rango->granularidad->etiqueta($inicio),
                'personas' => $conteo[$inicio->toDateString()] ?? 0,
            ];
        }

        return $filas;
    }
}
