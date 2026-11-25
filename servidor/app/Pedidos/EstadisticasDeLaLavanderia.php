<?php

namespace App\Pedidos;

use App\Autorizacion\Autorizacion;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Las estadísticas básicas de la lavandería: sus pedidos completados del día y de la semana (NSV-2026-001-v8.pdf:17:ANEXO
 * A, Sección A.3, Módulo B #5; openspec/changes/historial-de-la-lavanderia/design.md, decisiones 1 a 3).
 *
 * - Cuenta los pedidos en Entregado por el instante de su paso a Entregado (Pedido::entregados()).
 * - "Del día" y "de la semana" se leen en la zona horaria de la plaza de cada pedido, la de su lavandería: una entrega a
 *   las 19:30 del jueves en la plaza es del jueves, aunque en UTC ya sea el viernes. La semana va de lunes a domingo
 *   (date_trunc('week') de PostgreSQL empieza en lunes).
 * - Qué pedidos son de la cuenta lo dice el componente de autorización (Autorizacion::pedidosDeLaLavanderia()).
 *
 * El ingreso total y la calificación promedio esperan DD-013, DD-020, DD-012 y DD-052 (historial-lavanderia-E07 y E08).
 */
final class EstadisticasDeLaLavanderia
{
    public function __construct(private readonly Autorizacion $autorizacion) {}

    /**
     * Los conteos de la cuenta en ese instante; sin instante, ahora.
     *
     * @return array{pedidos_del_dia: int, pedidos_de_la_semana: int}
     */
    public function de(Usuario $cuenta, ?CarbonImmutable $ahora = null): array
    {
        // El instante va como parámetro y no como now() de la base: el reloj es el de la aplicación.
        $instante = ($ahora ?? CarbonImmutable::now())->utc()->toIso8601String();

        $entregas = $this->autorizacion->pedidosDeLaLavanderia($cuenta)
            ->entregados()
            ->join('plazas', 'plazas.id_plazas', '=', 'pedidos.id_plazas')
            ->addSelect('plazas.zona_horaria');

        $conteos = DB::query()
            ->fromSub($entregas, 'entregas')
            ->selectRaw(
                'count(*) filter (where (entregado_en at time zone zona_horaria)::date
                    = (cast(? as timestamptz) at time zone zona_horaria)::date) as pedidos_del_dia',
                [$instante],
            )
            ->selectRaw(
                "count(*) filter (where date_trunc('week', entregado_en at time zone zona_horaria)
                    = date_trunc('week', cast(? as timestamptz) at time zone zona_horaria)) as pedidos_de_la_semana",
                [$instante],
            )
            ->sole();

        return [
            'pedidos_del_dia' => (int) $conteos->pedidos_del_dia,
            'pedidos_de_la_semana' => (int) $conteos->pedidos_de_la_semana,
        ];
    }
}
