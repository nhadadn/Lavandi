<?php

namespace App\Repartidores;

use App\Autorizacion\Autorizacion;
use App\Models\Repartidor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Las estadísticas básicas del repartidor: sus entregas completadas del día y de la semana (NSV-2026-001-v8.pdf:18:ANEXO
 * A, Sección A.3, Módulo C #5; openspec/changes/repartidor/design.md, lectura 5; repartidor-E28).
 *
 * - Cuenta las entregas de su historial (Autorizacion::entregasDelRepartidor()) por el instante de su paso a Entregado
 *   (Pedido::entregados()).
 * - "Del día" y "de la semana" se leen en la zona horaria de la plaza de cada pedido, que es la del repartidor: una
 *   entrega a las 19:30 del jueves en la plaza es del jueves, aunque en UTC ya sea el viernes. La semana va de lunes a
 *   domingo (date_trunc('week') de PostgreSQL empieza en lunes), como las de la lavandería
 *   (App\Pedidos\EstadisticasDeLaLavanderia).
 *
 * El ingreso total acumulado espera DD-019 y DD-013 (repartidor-E29).
 */
final class EstadisticasDelRepartidor
{
    public function __construct(private readonly Autorizacion $autorizacion) {}

    /**
     * Los conteos del repartidor en ese instante; sin instante, ahora.
     *
     * @return array{entregas_del_dia: int, entregas_de_la_semana: int}
     */
    public function de(Repartidor $repartidor, ?CarbonImmutable $ahora = null): array
    {
        // El instante va como parámetro y no como now() de la base: el reloj es el de la aplicación.
        $instante = ($ahora ?? CarbonImmutable::now())->utc()->toIso8601String();

        $entregas = $this->autorizacion->entregasDelRepartidor($repartidor)
            ->entregados()
            ->join('plazas', 'plazas.id_plazas', '=', 'pedidos.id_plazas')
            ->addSelect('plazas.zona_horaria');

        $conteos = DB::query()
            ->fromSub($entregas, 'entregas')
            ->selectRaw(
                'count(*) filter (where (entregado_en at time zone zona_horaria)::date
                    = (cast(? as timestamptz) at time zone zona_horaria)::date) as entregas_del_dia',
                [$instante],
            )
            ->selectRaw(
                "count(*) filter (where date_trunc('week', entregado_en at time zone zona_horaria)
                    = date_trunc('week', cast(? as timestamptz) at time zone zona_horaria)) as entregas_de_la_semana",
                [$instante],
            )
            ->sole();

        return [
            'entregas_del_dia' => (int) $conteos->entregas_del_dia,
            'entregas_de_la_semana' => (int) $conteos->entregas_de_la_semana,
        ];
    }
}
