<?php

namespace App\Http\Controllers\Api;

use App\Autorizacion\Autorizacion;
use App\Direcciones\RecogidaYEntrega;
use App\Http\Controllers\Controller;
use App\Http\Requests\CrearPedidoRequest;
use App\Http\Requests\ListaDePedidosRequest;
use App\Http\Resources\PedidoDeLaListaResource;
use App\Http\Resources\PedidoResource;
use App\Models\Lavanderia;
use App\Models\Usuario;
use App\Pedidos\CreacionDePedido;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Los pedidos de la cuenta: el pedido nace al aceptar el resumen, antes del pago (NSV-2026-001-v8.pdf:16-17:ANEXO A,
 * Sección A.3, Módulo A #9 y #11; C-188; openspec/changes/resumen-y-confirmacion/).
 *
 * Contrato para la app. Todas las rutas llevan sesión y pasan por el componente de autorización:
 *
 *     Authorization: Bearer <token de la sesión>        (auth:sanctum y autorizar:usuario)
 *
 *     401  { "message": "Unauthenticated." }              sin sesión
 *     403  { "message": "Esta cuenta no tiene acceso." }  la cuenta no tiene el rol de usuario
 *
 * El pedido, como sale en todas las respuestas:
 *
 *     {
 *       "id_publico": "01jd8x6m3v2c9q4w5e7r8t0y1z",   el ULID del pedido; la API nunca lo identifica de otra forma
 *       "folio": null | "…",                          vacío hasta Confirmado; su formato, ADR-0020
 *       "confirmado_en": null | "2026-12-03T15:30:00Z",  el instante en que pasó a Confirmado, en UTC
 *       "estado": {
 *         "codigo": "confirmado",                     el paso del seguimiento en que el usuario ve el estado actual:
 *                                                     con la lavandería en "aceptado" sale "confirmado", y en
 *                                                     "en_entrega", "listo" (ADR-0021 y su adenda propuesta). Fuera
 *                                                     del flujo, el código del estado: "por_pagar" o "rechazado"
 *         "nombre": "Confirmado",                     el nombre para el usuario
 *         "visible_usuario": true                     false fuera del flujo, como en el estado previo al pago (DD-123)
 *                                                     o en "rechazado" (estados-E19 espera DD-103): la app no lo nombra
 *       },
 *       "seguimiento": [                              los cinco estados que ve el usuario, en el orden del contrato
 *         { "codigo": "confirmado", "nombre": "Confirmado" },     (estados-E01). Siempre los cinco, también antes
 *         { "codigo": "recolectado", "nombre": "Recolectado" },   del pago. La app ubica el estado actual por
 *         { "codigo": "en_proceso", "nombre": "En proceso" },     estado.codigo: los anteriores están cumplidos y
 *         { "codigo": "listo", "nombre": "Listo" },               los siguientes, pendientes (estados-E02). Con
 *         { "codigo": "entregado", "nombre": "Entregado" }        visible_usuario en false no hay estado actual
 *       ],
 *       "lavanderia": { "id_publico": "01jd8x6m3v2c9q4w5e7r8t0y1u", "nombre": "Lavandería de prueba 1" },
 *       "servicios": [
 *         {
 *           "codigo": "estandar",                     el tipo de servicio, como en el perfil de la lavandería
 *           "nombre": "Lavado estándar",
 *           "unidad": { "codigo": "kg", "nombre": "kilo" },
 *           "precio_unitario": "18.00",               texto en pesos, con dos decimales: el copiado al crear el pedido
 *           "cantidad_estimada": null,                vacía hasta DD-012
 *           "monto_estimado": null                    vacío hasta DD-012; la base lo calcula, cantidad por precio
 *         }
 *       ],                                            uno por ahora; varios, si DD-098 lo pide
 *       "zona_horaria": "America/Mexico_City",        la de la plaza de la lavandería
 *       "ventana": {
 *         "inicio": "2026-12-03T11:00:00-06:00",      ISO 8601 con el desfase de la plaza, como lo da la agenda
 *         "fin": "2026-12-03T12:00:00-06:00",
 *         "fecha": "2026-12-03",                      en la hora de la plaza
 *         "hora_inicio": "11:00",
 *         "hora_fin": "12:00"
 *       },
 *       "recogida": {                                 la copia que guardó el pedido, no la dirección guardada
 *         "alias": "Casa" | null,
 *         "calle": "Calle Ejemplo" | null,
 *         "numero_exterior": "123" | null,
 *         "numero_interior": "4" | null,
 *         "colonia": "Centro",
 *         "codigo_postal": "34000" | null,
 *         "municipio": "Durango" | null,              el nombre del municipio del catálogo al crear el pedido; sin estado
 *         "referencias": "Portón gris" | null,
 *         "latitud": 24.0277,
 *         "longitud": -104.6532
 *       },
 *       "entrega": { ...igual que recogida... },      la misma copia si se entrega en la recogida
 *       "monto_estimado": null,                       vacío hasta DD-012 y DD-010 (confirmacion-E02)
 *       "envio": null                                 vacío hasta DD-019 y DD-025 (confirmacion-E03)
 *     }
 *
 * La política de cancelación no viaja todavía: espera DD-016 (confirmacion-E04).
 *
 * ---------------------------------------------------------------------------------------------------------------------
 *
 *     POST /api/pedidos                          Acepta el resumen: crea el pedido antes del pago (confirmacion-E05)
 *
 *     {
 *       "lavanderia": "01jd8x6m3v2c9q4w5e7r8t0y1u",    obligatorio: el id_publico de la lavandería elegida
 *       "servicio": "estandar",                         obligatorio: el código del servicio, de los que da su perfil
 *       "ventana": {                                    obligatoria: tal como la dio
 *         "inicio": "2026-12-03T11:00:00-06:00",        GET /api/lavanderias/{id_publico}/ventanas
 *         "fin": "2026-12-03T12:00:00-06:00"
 *       },
 *       "recogida": "01jd8x6m3v2c9q4w5e7r8t0y1v",      obligatorio: el id_publico de una dirección de la cuenta
 *       "entrega": "01jd8x6m3v2c9q4w5e7r8t0y1w"        opcional: sin entrega, o null, se entrega en la recogida
 *     }
 *     Cualquier otro campo se ignora: ni un folio, ni un estado, ni que ya pagó confirman el pedido (confirmacion-E07).
 *
 *     201  { "pedido": { ...el pedido... } }
 *          El pedido nace en el estado previo al pago: "folio" y "confirmado_en" vacíos y "estado.visible_usuario"
 *          false. Pasa a Confirmado solo cuando la pasarela confirma el pago (C-188); la app sigue al pago.
 *
 *     Antes de crear nada, el servidor vuelve a validar la ventana y las direcciones con las mismas reglas con que se
 *     ofrecieron (confirmacion-E08). Cada error va en su campo, para que la app lleve a elegir solo eso de nuevo, con
 *     los demás datos del pedido intactos.
 *
 *     422  { "message": "La ventana ya empezó.", "errors": { "ventana": ["La ventana ya empezó."] } }
 *          La ventana ya no se ofrece (App\Agenda\Agenda::validar(), agenda-E09). Los motivos:
 *            "La ventana va con su inicio y su fin en ISO 8601, con su desfase, como los da la agenda."
 *            "La ventana debe durar exactamente una hora."
 *            "La ventana ya terminó."
 *            "La ventana ya empezó."
 *            "La ventana empieza antes de la anticipación mínima de la plaza."
 *            "La ventana está después de los días que se pueden agendar."
 *            "La lavandería no ofrece esa ventana en su horario."
 *     422  {
 *            "message": "La dirección está fuera de la zona de servicio.",
 *            "errors":  { "recogida": ["La dirección está fuera de la zona de servicio."] },
 *            "motivos": { "recogida": "fuera_de_la_zona_de_servicio" }
 *          }
 *          La recogida, la entrega o las dos ya no sirven; solo vienen las rechazadas, cada una con su motivo, con la
 *          misma forma que POST /api/direcciones/recogida-y-entrega. Los motivos:
 *            fuera_de_la_zona_de_servicio          el punto no cae en ninguna zona de servicio activa
 *            fuera_de_la_plaza_de_la_lavanderia    cae en una zona de otra plaza que la de la lavandería
 *            plaza_de_la_direccion_desactualizada  cae en la zona de la lavandería, pero la dirección quedó con la
 *                                                  plaza de antes de un cambio de zonas y ya la usó un pedido de esa
 *                                                  plaza: "Esta dirección cambió de zona de servicio. Guárdala de
 *                                                  nuevo para usarla." La app ofrece guardarla otra vez
 *                                                  (POST /api/direcciones) y usa la nueva
 *     422  { "message": "...", "errors": { "servicio": ["La lavandería no ofrece ese servicio."] } }
 *          El servicio no lo ofrece la lavandería, o no está activo.
 *     422  { "message": "...", "errors": { "recogida": ["Falta la dirección de recogida."] } }
 *          Falta un campo o no es válido.
 *     404  { "message": "No existe la lavandería." }
 *          No existe, o el usuario no la ve (Lavanderia::visiblesParaElUsuario()).
 *     404  { "message": "No existe la dirección." }
 *          La recogida o la entrega no existe, está borrada o es de otra cuenta: las tres responden igual.
 *     503  { "message": "La agenda de la plaza no está configurada." }
 *          A la plaza le falta una de las dos claves de su agenda (App\Agenda\ParametrosDeAgenda).
 *     503  { "message": "Todavía no se pueden crear pedidos." }
 *          Falta el estado previo al pago (config/pedidos.php; DD-123).
 *
 *     En ningún error se crea el pedido.
 *
 *     Una dirección guardada sin plaza, o con la plaza de antes de un cambio de zonas, que hoy cae en una zona activa
 *     de la plaza de la lavandería, se acepta: el servidor le vuelve a resolver la plaza antes de copiarla.
 *
 * ---------------------------------------------------------------------------------------------------------------------
 *
 *     GET /api/pedidos/{id_publico}          Un pedido de la cuenta: su folio, su resumen y su estado (confirmacion-E09
 *                                            y E12; estados-E01 y E11)
 *
 *     {id_publico}  el ULID del pedido, el que trae "pedido.id_publico" en la respuesta de POST /api/pedidos. Se
 *                   acepta en mayúsculas o en minúsculas.
 *
 *     200  { "pedido": { ...el pedido... } }
 *          La app consulta aquí si el pedido ya pasó a Confirmado: "folio" y "confirmado_en" llenos y "estado.codigo"
 *          en "confirmado". Entonces muestra la confirmación con el folio y el resumen. El seguimiento consulta aquí
 *          el estado actual: "estado" trae el nombre que ve el usuario, el mismo que marcó la lavandería en
 *          Recolectado, En proceso y Listo (estados-E11), y "seguimiento", los cinco en su orden.
 *     404  { "message": "No existe el pedido." }
 *          No existe, es de otra cuenta, o el valor no es un ULID (un folio, por ejemplo): todos responden igual y sin
 *          ningún dato. El folio no es un identificador de la API, ni para el pedido propio.
 *
 * ---------------------------------------------------------------------------------------------------------------------
 *
 *     GET /api/pedidos?desde=2026-12-01&hasta=2026-12-31     El historial de la cuenta: sus pedidos, con el filtro por
 *                                                            fecha (historial-E01 a E04; Módulo A #13)
 *
 *     desde, hasta  opcionales, AAAA-MM-DD. Los dos días entran en el rango; con uno solo, queda abierto del otro lado.
 *                   Se comparan contra la fecha de la recolección del pedido en la hora de su plaza: la de
 *                   "ventana.fecha" del pedido, la que muestra su resumen.
 *
 *     200  {
 *            "pedidos": [
 *              {
 *                "id_publico": "01jd8x6m3v2c9q4w5e7r8t0y1z",   el de GET /api/pedidos/{id_publico}
 *                "folio": "X4TCWWR5",                          siempre lleno: solo vienen pedidos confirmados
 *                "fecha": "2026-12-03",                        la de la recolección, en la hora de la plaza
 *                "lavanderia": { "id_publico": "01jd8x6m3v2c9q4w5e7r8t0y1u", "nombre": "Lavandería de prueba 1" },
 *                "estado": { "codigo": "entregado", "nombre": "Entregado", "visible_usuario": true },
 *                "calificacion": null | { "estrellas": 4, "comentario": "La ropa llegó limpia." | null }
 *              }                                             la calificación del servicio, si ya la hizo
 *                                                            (historial-E06; POST /api/pedidos/{id_publico}/calificacion)
 *            ]
 *          }
 *          Solo los pedidos de la cuenta (historial-E02) y solo los confirmados, con "confirmado_en" lleno: uno que
 *          espera su pago no entró al flujo y no tiene folio ni estado que nombrar (C-188; ADR-0021). Del más reciente
 *          al más antiguo por la recolección, y con la misma recolección, del más nuevo al más viejo. Sin paginación:
 *          todos los del rango. Sin pedidos, o sin pedidos en el rango, "pedidos" llega vacía (historial-E04).
 *     422  { "message": "...", "errors": { "desde": ["La fecha desde va como AAAA-MM-DD."] } }
 *          Cada error va en su campo, y "message" es el primero:
 *            desde  "La fecha desde va como AAAA-MM-DD."
 *            hasta  "La fecha hasta va como AAAA-MM-DD."
 *            hasta  "La fecha hasta no puede ser anterior a la fecha desde."
 *
 * No hay ninguna ruta que confirme un pedido: lo confirma el cobro cuando la pasarela confirma el pago
 * (App\Pedidos\ConfirmacionDePedido; C-188).
 */
class PedidosController extends Controller
{
    public function __construct(private readonly Autorizacion $autorizacion) {}

    public function crear(
        CrearPedidoRequest $peticion,
        RecogidaYEntrega $recogidaYEntrega,
        CreacionDePedido $creacion,
    ): JsonResponse {
        /** @var Usuario $usuario */
        $usuario = $peticion->user();

        $lavanderia = Lavanderia::query()
            ->visiblesParaElUsuario()
            // HasUlids guarda el ULID en minúsculas.
            ->where('id_publico', Str::lower($peticion->validated('lavanderia')))
            ->with('plaza')
            ->first();

        if ($lavanderia === null) {
            return response()->json(['message' => 'No existe la lavandería.'], 404);
        }

        $direcciones = $recogidaYEntrega->elegir(
            $usuario,
            $peticion->validated('recogida'),
            $peticion->validated('entrega'),
        );

        $pedido = $creacion->crear(
            $usuario,
            $lavanderia,
            $peticion->validated('servicio'),
            $peticion->validated('ventana'),
            $direcciones,
        );

        return response()->json(['pedido' => PedidoResource::delPedido($pedido)], 201);
    }

    /**
     * Un pedido de la cuenta por su identificador público. Quién ve qué lo dice el componente de autorización: uno de
     * otra cuenta, uno que no existe y un valor que no es ULID, como un folio, responden igual (confirmacion-E12).
     */
    public function mostrar(Request $peticion, string $pedido): JsonResponse
    {
        /** @var Usuario $usuario */
        $usuario = $peticion->user();

        $encontrado = Str::isUlid($pedido)
            ? $this->autorizacion->pedidosVisibles($usuario)
                // HasUlids guarda el ULID en minúsculas.
                ->where('id_publico', Str::lower($pedido))
                ->first()
            : null;

        if ($encontrado === null) {
            return response()->json(['message' => 'No existe el pedido.'], 404);
        }

        return response()->json(['pedido' => PedidoResource::delPedido($encontrado)]);
    }

    /**
     * El historial de la cuenta: sus pedidos confirmados, con el filtro por fecha. De quién son lo dice el componente
     * de autorización (historial-E02); cuáles entran y en qué orden, el modelo del pedido.
     */
    public function lista(ListaDePedidosRequest $peticion): JsonResponse
    {
        /** @var Usuario $usuario */
        $usuario = $peticion->user();

        $pedidos = $this->autorizacion->pedidosVisibles($usuario)
            ->confirmados()
            ->recolectadosEntre($peticion->validated('desde'), $peticion->validated('hasta'))
            ->delMasRecienteAlMasAntiguo()
            ->with(['plaza', 'lavanderia', 'estado.paso', 'calificacionDelServicio'])
            ->get();

        return response()->json(['pedidos' => PedidoDeLaListaResource::collection($pedidos)]);
    }
}
