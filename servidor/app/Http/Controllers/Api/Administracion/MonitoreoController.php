<?php

namespace App\Http\Controllers\Api\Administracion;

use App\Autorizacion\Autorizacion;
use App\Http\Controllers\Controller;
use App\Http\Resources\Administracion\PedidoDelMonitoreoResource;
use App\Models\Usuario;
use App\Pedidos\MarcadoresDelMonitoreo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * El monitoreo de pedidos de la administración, en la app (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #4,
 * "Vista en tiempo real de todos los pedidos activos con mapa"; openspec/changes/administracion-en-la-app/, design.md,
 * sección 4, F-D04; ADR-0022, decisiones 9, 12 y 13).
 *
 * Una sola respuesta trae los pedidos activos y sus marcadores, y los marcadores son solo de los pedidos de la lista:
 * ningún marcador llega sin su pedido. Son dos lecturas, como en el panel: si un pedido cambia de estado justo entre
 * las dos, esa respuesta puede traer su marcador con el nombre del estado nuevo, o sin marcador si dejó de estar
 * activo; la siguiente consulta, a los 30 s, lo corrige. No calcula nada: llama a las mismas clases que el monitoreo
 * del panel (administracion-app-E11). Los pedidos son los que el componente de autorización le da a la cuenta
 * (Autorizacion::pedidosActivosDelPanel(), que usa Pedido::activosDelMonitoreo()); su estado se nombra con
 * App\Pedidos\NombresDelEstado; y los marcadores son los de App\Pedidos\MarcadoresDelMonitoreo::deLosPedidos(). No trae
 * nada de ningún repartidor: su posición en el mapa está fuera del contrato (DD-077).
 *
 * Contrato para la app. La ruta lleva sesión y pasa por el componente de autorización:
 *
 *     Authorization: Bearer <token de administración>      (auth:sanctum y autorizar:administracion)
 *
 *     401  { "message": "Unauthenticated." }                sin sesión, o con el token vencido o revocado
 *                                                           (administracion-app-E05)
 *     403  { "message": "Esta cuenta no tiene acceso." }    la cuenta no es de administración, o su token no lleva la
 *                                                           habilidad de administración (administracion-app-E06 y E07)
 *
 * ---------------------------------------------------------------------------------------------------------------------
 *
 *     GET /api/administracion/monitoreo       Los pedidos activos de todas las plazas y su mapa (administracion-app-E28).
 *                                             La app la consulta cada 30 s mientras la pantalla está visible y la app
 *                                             al frente (ADR-0007; administracion-app-E29)
 *
 *     Sin parámetros.
 *
 *     200  {
 *            "pedidos": [
 *              {
 *                "id_publico": "01jd8x6m3v2c9q4w5e7r8t0y1z",    el ULID del pedido
 *                "folio": "7KQ2M9XA",                           siempre lleno: el pedido activo está pagado
 *                "estado": {
 *                  "codigo": "en_proceso",                     el estado operativo, en estados_pedido
 *                  "nombre": "En proceso"                      su nombre operativo, el mismo del panel y de los
 *                },                                            marcadores
 *                "plaza": "Plaza de prueba",                   el nombre de la plaza del pedido
 *                "ventana": {                                  la recolección, como en los pedidos de la lavandería
 *                  "inicio": "2026-12-03T11:00:00-06:00",      y del repartidor: instantes con el desfase de la plaza,
 *                  "fin": "2026-12-03T12:00:00-06:00",         y la fecha y las horas en su hora
 *                  "fecha": "2026-12-03",
 *                  "hora_inicio": "11:00",
 *                  "hora_fin": "12:00"
 *                }
 *              }
 *            ],
 *            "marcadores": [
 *              {
 *                "pedido": "01jd8x6m3v2c9q4w5e7r8t0y1z",       el id_publico de su pedido, que siempre está en
 *                                                              "pedidos"
 *                "folio": "7KQ2M9XA",
 *                "estado": "En proceso",                       el mismo nombre que "estado.nombre" de su pedido, salvo
 *                                                              si cambió justo entre las dos lecturas
 *                "plaza": "Plaza de prueba",                   el mismo nombre que "plaza" de su pedido
 *                "tipo": "recoleccion",                        "recoleccion" (R en el mapa) o "entrega" (E)
 *                "etiqueta": "Recolección",                    "Recolección", "Entrega" o "Recolección y entrega"
 *                "latitud": 10.5,                              el punto que se guardó con la dirección, en grados
 *                "longitud": -119.5
 *              }
 *            ]
 *          }
 *
 *          "pedidos": los pedidos activos con la regla de administracion-E06: los de pago acreditado en "confirmado",
 *          "aceptado", "rechazado", "recolectado", "en_proceso", "listo" y "en_entrega". Nunca uno entregado ni uno que
 *          espera su pago. El rechazado sigue activo mientras DD-103 y DD-016 no digan qué pasa con él; el cancelado
 *          espera DD-016 (administracion-app-E31). De la recolección más próxima a la más lejana; con la misma, del más
 *          viejo al más nuevo. Sin paginación. Sin pedidos activos, "pedidos" y "marcadores" llegan vacías.
 *
 *          "marcadores": con la regla de administracion-E09 y E10, solo de los pedidos de "pedidos" y en su orden: uno
 *          en la recolección de cada pedido y, después, otro en la entrega cuando es en otra dirección. Si se entrega
 *          en la misma, el pedido lleva un solo marcador, el de la recolección, con la etiqueta "Recolección y
 *          entrega". Solo recolecciones y entregas: ningún marcador es de un repartidor.
 */
class MonitoreoController extends Controller
{
    public function __construct(
        private readonly Autorizacion $autorizacion,
        private readonly MarcadoresDelMonitoreo $marcadores,
    ) {}

    /**
     * Los pedidos activos y sus marcadores. Cuáles ve la cuenta lo dice el componente de autorización; cuáles están
     * activos y en qué orden, el modelo del pedido; los marcadores, la clase del mapa del panel.
     */
    public function __invoke(Request $peticion): JsonResponse
    {
        $cuenta = $this->cuenta($peticion);

        $pedidos = $this->autorizacion->pedidosActivosDelPanel($cuenta)
            ->deLaRecoleccionMasProxima()
            ->with(PedidoDelMonitoreoResource::RELACIONES)
            ->get();

        return response()->json([
            'pedidos' => PedidoDelMonitoreoResource::collection($pedidos),
            // Solo los de la lista que ya se leyó: un pedido que se volvió activo entre las dos lecturas no deja un
            // marcador sin su pedido; llega en las dos en la siguiente consulta.
            'marcadores' => $this->marcadores->deLosPedidos(
                $this->autorizacion->pedidosActivosDelPanel($cuenta)->whereKey($pedidos->modelKeys()),
            ),
        ]);
    }

    private function cuenta(Request $peticion): Usuario
    {
        /** @var Usuario $cuenta */
        $cuenta = $peticion->user();

        return $cuenta;
    }
}
