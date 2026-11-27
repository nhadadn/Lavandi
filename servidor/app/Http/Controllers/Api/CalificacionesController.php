<?php

namespace App\Http\Controllers\Api;

use App\Autorizacion\Autorizacion;
use App\Calificaciones\CalificacionDelServicio;
use App\Http\Controllers\Controller;
use App\Http\Requests\CalificarPedidoRequest;
use App\Http\Resources\CalificacionResource;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * La calificación del servicio de un pedido de la cuenta: de 1 a 5 estrellas con un comentario opcional
 * (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo A #14; openspec/changes/historial-y-calificacion/). Queda como
 * calificación de la lavandería del pedido (historial-E12), y el historial la muestra en el pedido
 * (App\Http\Controllers\Api\PedidosController, GET /api/pedidos).
 *
 * Contrato para la app. La ruta lleva sesión y pasa por el componente de autorización:
 *
 *     Authorization: Bearer <token de la sesión>        (auth:sanctum y autorizar:usuario)
 *
 *     POST /api/pedidos/{id_publico}/calificacion       Califica el servicio del pedido (historial-E06 a E09 y E12)
 *
 *     {id_publico}  el ULID del pedido, el de GET /api/pedidos. Se acepta en mayúsculas o en minúsculas.
 *
 *     {
 *       "estrellas": 4,                            obligatoria: un número entero de 1 a 5
 *       "comentario": "La ropa llegó limpia."      opcional: texto de hasta 1000 caracteres
 *     }                                            (pedidos.comentario_de_calificacion_maximo). Se guarda tal como
 *                                                  llega, sin los espacios de los extremos; null, vacío o solo con
 *                                                  espacios es una calificación sin comentario. Cualquier otro campo
 *                                                  se ignora
 *
 *     201  { "calificacion": { "estrellas": 4, "comentario": "La ropa llegó limpia." | null } }
 *
 *     401  { "message": "Unauthenticated." }              sin sesión
 *     403  { "message": "Esta cuenta no tiene acceso." }  la cuenta no tiene el rol de usuario, como la de una lavandería
 *     404  { "message": "No existe el pedido." }
 *          No existe, es de otra cuenta, o el valor no es un ULID, como un folio: todos responden igual y sin ningún dato
 *          (historial-E09; ADR-0019, decisión 3). Se busca antes de revisar el cuerpo, así que un pedido ajeno responde
 *          404 aunque el cuerpo venga mal, como en el resto de la API (autorizacion-E07 y E14).
 *     422  { "message": "...", "errors": { "estrellas": ["..."] } }
 *          Solo sobre un pedido propio. Un error por campo, y "message" es el primero:
 *            estrellas   "Elige de 1 a 5 estrellas."                        falta, o llega null
 *            estrellas   "Las estrellas van como un número entero de 1 a 5." 4.5, "cuatro"
 *            estrellas   "Las estrellas van de 1 a 5."                      0, 6, -1
 *            comentario  "El comentario va en texto."
 *            comentario  "El comentario no puede pasar de 1000 caracteres."
 *     409  { "message": "Este pedido todavía no se puede calificar." }
 *          El pedido propio no está en Entregado: espera su pago, está en curso o lo rechazó la lavandería.
 *     409  { "message": "Este pedido ya está calificado." }
 *          Ya tiene su calificación: no se cambia ni se agrega otra.
 *          Los dos 409 son la lectura provisional de BR-25 (design.md, "La calificación: lectura provisional"): desde qué
 *          estado y hasta cuándo se califica, y si una calificación se cambia, esperan historial-E10 y DD-107.
 *
 *     En ningún error se guarda la calificación.
 */
class CalificacionesController extends Controller
{
    public function __construct(private readonly Autorizacion $autorizacion) {}

    public function __invoke(Request $peticion, CalificacionDelServicio $calificacion, string $pedido): JsonResponse
    {
        /** @var Usuario $usuario */
        $usuario = $peticion->user();

        $encontrado = Str::isUlid($pedido)
            ? $this->autorizacion->pedidosVisibles($usuario)
                // HasUlids guarda el ULID en minúsculas.
                ->where('id_publico', Str::lower($pedido))
                ->with('estado')
                ->first()
            : null;

        if ($encontrado === null) {
            return response()->json(['message' => 'No existe el pedido.'], 404);
        }

        // El cuerpo se revisa ya con el pedido propio en la mano: resolver la petición validada la valida (422).
        $validada = app(CalificarPedidoRequest::class);

        $guardada = $calificacion->calificar(
            $encontrado,
            (int) $validada->validated('estrellas'),
            $validada->validated('comentario'),
        );

        return response()->json(['calificacion' => new CalificacionResource($guardada)], 201);
    }
}
