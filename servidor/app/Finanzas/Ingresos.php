<?php

namespace App\Finanzas;

use App\Indicadores\Rango;
use App\Models\Pago;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Los ingresos del periodo: lo cobrado a los usuarios con pago confirmado por la pasarela (NSV-2026-001-v8.pdf:19:ANEXO
 * A, Sección A.3, Módulo D #6, "Ingresos por periodo"; administracion-E17; openspec/changes/administracion/design.md,
 * lectura 4).
 *
 * - Cuenta cada pago en pagado, el estado que solo pone el aviso auténtico de la pasarela (ADR-0010, decisión 1;
 *   App\Cobro\RecepcionDeAvisos). Una orden creada y sin confirmar, un intento rechazado o un cobro vencido no cuentan.
 * - El monto es el que cobró la pasarela, pagos.monto, no el estimado ni el final del pedido: qué monto se cobra lo fija
 *   el modelo de cobro (pago-E14, DD-012), y este cálculo no lo afirma. Los reembolsos no se descuentan todavía
 *   (administracion-E18, bloqueado por DD-012 y DD-016).
 * - La fecha de un ingreso es la de la confirmación del pago, en la hora de la plaza de su pedido (modelo-multi-plaza.md).
 * - El dinero viaja como texto con dos decimales, en pesos: nunca pasa por un flotante.
 *
 * Es la única fuente de los ingresos: el reporte financiero del panel, el indicador de ingresos brutos (indicadores-E16)
 * y sus descargas leen de aquí, para que nunca difieran. Cuenta los pagos de todas las plazas: la administración las ve
 * todas (Autorizacion::puedeVerLosReportesFinancieros()).
 */
final class Ingresos
{
    /** El día de un ingreso: el de la confirmación del pago, en la hora de su plaza. */
    public const FECHA_DEL_INGRESO = '(pagos.pagado_en at time zone plazas.zona_horaria)::date';

    /** La suma de los montos, con dos decimales, y "0.00" sin pagos. */
    private const SUMA = 'coalesce(sum(pagos.monto), 0)::numeric(12,2)';

    /**
     * Los pagos que confirmó la pasarela, con la plaza de su pedido: los que cuentan como ingreso.
     */
    public static function pagosConfirmados(): Builder
    {
        return DB::table('pagos')
            ->join('plazas', 'plazas.id_plazas', '=', 'pagos.id_plazas')
            ->where('pagos.estado', Pago::PAGADO);
    }

    /**
     * Los ingresos de todo el rango, con sus dos días: cuántos pagos confirmados y cuánto suman.
     *
     * @return array{pagos: int, ingresos: string}
     */
    public function delRango(Rango $rango): array
    {
        $fila = self::pagosConfirmados()
            ->whereRaw(self::FECHA_DEL_INGRESO.' between ? and ?', [$rango->desde->toDateString(), $rango->hasta->toDateString()])
            ->selectRaw('count(*) as pagos')
            ->selectRaw(self::SUMA.' as ingresos')
            ->first();

        return ['pagos' => (int) $fila->pagos, 'ingresos' => (string) $fila->ingresos];
    }

    /**
     * Una fila por periodo del rango, con la clave de su primer día (Y-m-d), en orden: el periodo, cuántos pagos
     * confirmados tuvo y cuánto suman. Un periodo sin pagos va con 0 y "0.00".
     *
     * @return array<string, array{periodo: string, pagos: int, ingresos: string}>
     */
    public function porPeriodo(Rango $rango): array
    {
        $periodo = sprintf("to_char(date_trunc('%s', %s), 'YYYY-MM-DD')", $rango->granularidad->unidadSql(), self::FECHA_DEL_INGRESO);

        $sumas = self::pagosConfirmados()
            ->whereRaw(self::FECHA_DEL_INGRESO.' between ? and ?', [$rango->desde->toDateString(), $rango->hasta->toDateString()])
            ->selectRaw("{$periodo} as periodo")
            ->selectRaw('count(*) as pagos')
            ->selectRaw(self::SUMA.' as ingresos')
            ->groupByRaw($periodo)
            ->get()
            ->keyBy('periodo');

        $filas = [];

        foreach ($rango->periodos() as $inicio) {
            $suma = $sumas->get($inicio->toDateString());

            $filas[$inicio->toDateString()] = [
                'periodo' => $rango->granularidad->etiqueta($inicio),
                'pagos' => $suma === null ? 0 : (int) $suma->pagos,
                'ingresos' => $suma === null ? '0.00' : (string) $suma->ingresos,
            ];
        }

        return $filas;
    }
}
