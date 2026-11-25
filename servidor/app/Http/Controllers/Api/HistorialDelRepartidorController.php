<?php

namespace App\Http\Controllers\Api;

use App\Autorizacion\Autorizacion;
use App\Http\Controllers\Controller;
use App\Http\Middleware\RepartidorQueOpera;
use App\Http\Resources\PedidoEntregadoResource;
use App\Repartidores\EstadisticasDelRepartidor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * El historial de entregas del repartidor, en la app del Módulo C (NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3, Módulo C
 * #4; openspec/changes/repartidor/). El contrato completo está en openspec/changes/repartidor/design.md, "Contrato de la
 * API".
 *
 * Lleva sesión, el rol de repartidor y un repartidor que opera (auth:sanctum, autorizar:repartidor y repartidor-opera).
 * Qué entregas son suyas lo dice el componente de autorización (Autorizacion::entregasDelRepartidor()).
 *
 *     GET /api/repartidor/historial                  Sus entregas completadas (repartidor-E25)
 *
 *     200  {
 *            "pedidos": [
 *              {
 *                "id_publico": "01jd8x6m3v2c9q4w5e7r8t0y1z",   el de GET /api/repartidor/pedidos/{id_publico}
 *                "folio": "7KQ2M9XA",
 *                "entregado_en": "2026-12-04T01:30:00Z",       el instante en que él marcó Entregado, en UTC
 *                "fecha": "2026-12-03"                         ese instante, en la fecha de la plaza
 *              }
 *            ]
 *          }
 *          Completada es la que él marcó Entregado (design.md, lectura 5): ni una en curso, ni la de otro repartidor.
 *          De la entrega más reciente a la más antigua; con la misma, del pedido más nuevo al más viejo. Sin paginación;
 *          vacía si no hay.
 *
 *          Todavía no lleva el monto ni la calificación recibida: esperan DD-019 y DD-013 (repartidor-E26) y DD-052
 *          (repartidor-E27). Cuando se respondan, se agregan como campos nuevos.
 *
 *     GET /api/repartidor/estadisticas               Sus entregas completadas del día y de la semana (repartidor-E28)
 *
 *     200  { "entregas_del_dia": 2, "entregas_de_la_semana": 5 }
 *          Cuenta las mismas entregas del historial, por el instante en que marcó Entregado. "Del día" es el día de hoy
 *          y "de la semana", de lunes a domingo, los dos en la zona horaria de su plaza: una entrega a las 19:30 del
 *          jueves en la plaza es del jueves, aunque en UTC ya sea el viernes. La semana incluye hoy. Sin entregas, 0.
 *
 *          Todavía no lleva el ingreso total acumulado: espera DD-019 y DD-013 (repartidor-E29).
 */
class HistorialDelRepartidorController extends Controller
{
    public function __construct(private readonly Autorizacion $autorizacion) {}

    public function historial(Request $peticion): JsonResponse
    {
        $pedidos = $this->autorizacion->entregasDelRepartidor(RepartidorQueOpera::de($peticion))
            ->entregados()
            ->deLaEntregaMasReciente()
            ->with('plaza')
            ->get();

        return response()->json(['pedidos' => PedidoEntregadoResource::collection($pedidos)]);
    }

    /**
     * Las estadísticas: los conteos del día y de la semana. Cómo se cuentan lo dice App\Repartidores\EstadisticasDelRepartidor.
     */
    public function estadisticas(Request $peticion, EstadisticasDelRepartidor $estadisticas): JsonResponse
    {
        return response()->json($estadisticas->de(RepartidorQueOpera::de($peticion)));
    }
}
