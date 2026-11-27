<?php

namespace App\Indicadores;

use Illuminate\Support\Facades\DB;

/**
 * El indicador de usuarios que vuelven a pedir, por mes de registro (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5,
 * categoría Usuarios; NSV-2026-001-v8.pdf:46:ANEXO D, Sección D.5, retención; indicadores-E08).
 *
 * Por cada mes del rango: cuántas cuentas registradas ese mes hicieron su primer pedido, y qué porcentaje de ellas
 * volvió a pedir, hasta hoy (openspec/changes/indicadores/design.md, lectura 3). Es una cifra por mes, no una matriz
 * de meses.
 *
 * - El mes de registro es el de usuarios.created_at (pregunta 7), en la zona horaria de referencia de lo que no tiene
 *   plaza (app.zona_horaria_sin_plaza), porque la cuenta no tiene plaza (pregunta 8; indicadores-E24). También cuentan
 *   las cuentas borradas después: su registro y sus pedidos son historial.
 * - Pedido es el que llegó a Confirmado, aunque después se rechace (lectura 1). Los pedidos cuentan hasta hoy, no solo
 *   los del rango.
 * - Un mes sin cuentas que hayan pedido da 0 y no tiene porcentaje.
 */
final class RetencionPorMesDeRegistro
{
    /**
     * Una fila por mes del rango, con la clave de su primer día (Y-m-d), en orden: el mes, las cuentas con primer pedido
     * y el porcentaje de ellas que volvió a pedir, con un decimal, o null si ninguna pidió.
     *
     * @return array<string, array{mes: string, con_primer_pedido: int, porcentaje: float|null}>
     */
    public function porMes(Rango $rango): array
    {
        $meses = Rango::deLosFiltros([
            'desde' => $rango->desde->toDateString(),
            'hasta' => $rango->hasta->toDateString(),
            'granularidad' => Granularidad::Mes->value,
        ]);

        $conteo = $this->contar($meses);
        $filas = [];

        foreach ($meses->periodos() as $inicio) {
            $clave = $inicio->toDateString();
            $conPrimerPedido = $conteo[$clave]['con_primer_pedido'] ?? 0;
            $volvieron = $conteo[$clave]['volvieron'] ?? 0;

            $filas[$clave] = [
                'mes' => Granularidad::Mes->etiqueta($inicio),
                'con_primer_pedido' => $conPrimerPedido,
                'porcentaje' => $conPrimerPedido === 0 ? null : round($volvieron * 100 / $conPrimerPedido, 1),
            ];
        }

        return $filas;
    }

    /**
     * Por el primer día de cada mes con cuentas registradas: las que pidieron una vez o más, y las que pidieron dos o
     * más.
     *
     * @return array<string, array{con_primer_pedido: int, volvieron: int}>
     */
    private function contar(Rango $meses): array
    {
        $zona = (string) config('app.zona_horaria_sin_plaza');
        $registro = '(usuarios.created_at at time zone ?)';

        $pedidosPorCuenta = DB::table('pedidos')
            ->select('id_usuarios')
            ->selectRaw('count(*) as pedidos')
            ->whereNotNull('confirmado_en')
            ->groupBy('id_usuarios');

        return DB::table('usuarios')
            ->leftJoinSub($pedidosPorCuenta, 'por_cuenta', 'por_cuenta.id_usuarios', '=', 'usuarios.id_usuarios')
            ->selectRaw("to_char(date_trunc('month', {$registro}), 'YYYY-MM-DD') as mes", [$zona])
            ->selectRaw('count(*) filter (where por_cuenta.pedidos >= 1) as con_primer_pedido')
            ->selectRaw('count(*) filter (where por_cuenta.pedidos >= 2) as volvieron')
            ->whereRaw("{$registro}::date between ? and ?", [$zona, $meses->desde->toDateString(), $meses->hasta->toDateString()])
            ->groupBy('mes')
            ->get()
            ->mapWithKeys(fn (object $fila): array => [$fila->mes => [
                'con_primer_pedido' => (int) $fila->con_primer_pedido,
                'volvieron' => (int) $fila->volvieron,
            ]])
            ->all();
    }
}
