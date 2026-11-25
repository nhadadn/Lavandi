<?php

namespace App\Indicadores;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * El indicador de pedidos totales por día, semana y mes (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5, categoría
 * Operaciones; indicadores-E01 a E03): cuántos pedidos tuvo cada periodo del rango, junto a los del periodo anterior.
 *
 * Cuenta como pedido todo pedido que llegó a Confirmado, aunque después se rechace o se cancele; el que nunca se pagó no
 * tiene confirmado_en y no cuenta (openspec/changes/indicadores/design.md, lectura 1; C-188). Su fecha es la de su
 * confirmación en la hora de su plaza (indicadores-E03; modelo-multi-plaza.md).
 *
 * Es la consulta del indicador: la gráfica, la tabla y la descarga de exportacion leen de aquí, para que nunca
 * difieran (design.md, Goals). Cuenta los pedidos de todas las plazas: filtrar por plaza está fuera del contrato
 * (proposal.md).
 */
final class PedidosTotales
{
    /** El día del pedido: el de su confirmación, en la hora de su plaza. */
    public const FECHA_DEL_PEDIDO = '(pedidos.confirmado_en at time zone plazas.zona_horaria)::date';

    /**
     * Una fila por periodo del rango, con la clave de su primer día (Y-m-d), en orden: el periodo, sus pedidos, el
     * periodo anterior y los pedidos de ese anterior.
     *
     * @return array<string, array{periodo: string, pedidos: int, periodo_anterior: string, pedidos_anterior: int}>
     */
    public function porPeriodo(Rango $rango): array
    {
        $conteo = $this->contar($rango->granularidad, $rango->inicioDelAnterior(), $rango->hasta);
        $filas = [];

        foreach ($rango->periodos() as $inicio) {
            $anterior = $rango->granularidad->anterior($inicio);

            $filas[$inicio->toDateString()] = [
                'periodo' => $rango->granularidad->etiqueta($inicio),
                'pedidos' => $conteo[$inicio->toDateString()] ?? 0,
                'periodo_anterior' => $rango->granularidad->etiqueta($anterior),
                'pedidos_anterior' => $conteo[$anterior->toDateString()] ?? 0,
            ];
        }

        return $filas;
    }

    /**
     * Los pedidos de cada periodo entre esos dos días, incluidos, por el primer día del periodo (Y-m-d). Un periodo sin
     * pedidos no aparece.
     *
     * @return array<string, int>
     */
    private function contar(Granularidad $granularidad, CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        return ConteoPorPeriodo::contar(
            DB::table('pedidos')
                ->join('plazas', 'plazas.id_plazas', '=', 'pedidos.id_plazas')
                ->whereNotNull('pedidos.confirmado_en'),
            self::FECHA_DEL_PEDIDO,
            'count(*)',
            $granularidad,
            $desde,
            $hasta,
        );
    }
}
