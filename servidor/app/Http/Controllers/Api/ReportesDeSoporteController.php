<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EnviarReporteRequest;
use App\Http\Resources\ReporteResource;
use App\Models\Usuario;
use App\Soporte\RecepcionDeReporte;
use Illuminate\Http\JsonResponse;

/**
 * El formulario de reporte de Soporte (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo A #16;
 * openspec/changes/perfil-y-soporte/, tarea 1.4).
 *
 * Contrato para la app. La ruta lleva sesión y pasa por el componente de autorización:
 *
 *     Authorization: Bearer <token de la sesión>        (auth:sanctum y autorizar:usuario)
 *
 *     401  { "message": "Unauthenticated." }              sin sesión
 *     403  { "message": "Esta cuenta no tiene acceso." }  la cuenta no tiene el rol de usuario
 *
 * ---------------------------------------------------------------------------------------------------------------------
 *
 *     POST /api/soporte/reportes                     Guarda un reporte (perfil-usuario-E11 a E13 y E17)
 *
 *     {
 *       "descripcion": "La camisa azul llegó con una mancha.",   obligatoria: texto de hasta 5000, sin contar los
 *                                                                espacios de los lados, que se quitan
 *       "pedido": "01jd8x6m3v2c9q4w5e7r8t0y1z"                   opcional: el id_publico de un pedido de la cuenta,
 *     }                                                          en mayúsculas o minúsculas; sin él, o null, el
 *                                                                reporte no es de un pedido
 *     Cualquier otro campo se ignora.
 *
 *     201  {
 *            "reporte": {
 *              "descripcion": "La camisa azul llegó con una mancha.",
 *              "pedido": "01jd8x6m3v2c9q4w5e7r8t0y1z" | null,
 *              "estado": "recibido",                     el único estado mientras no se responda DD-108
 *              "recibido_en": "2026-12-03T18:30:00Z"     la fecha del reporte, en UTC
 *            }
 *          }
 *          La app confirma que se recibió, sin prometer un tiempo de respuesta (perfil-usuario-E11). A quién le llega y
 *          cómo se le responde a la persona es DD-108 (perfil-usuario-E14). Sin pedido, el reporte va a la plaza del
 *          último pedido de la cuenta o, si no tiene, a la de su dirección guardada más reciente con plaza; sin ninguna
 *          de las dos, se guarda sin plaza y la respuesta es la misma (perfil-usuario-E17).
 *
 *     422  { "message": "Cuéntanos qué pasó.", "errors": { "descripcion": ["Cuéntanos qué pasó."] } }
 *          La descripción falta, viene vacía o solo con espacios (perfil-usuario-E12). Los otros motivos:
 *            "La descripción va en texto."
 *            "La descripción es demasiado larga."
 *            "El pedido va en texto."                    en "pedido"
 *     404  { "message": "No existe el pedido." }
 *          El pedido es de otra cuenta, no existe o el valor no es un ULID, como un folio: todos responden igual y sin
 *          ningún dato (perfil-usuario-E13; ADR-0019, decisión 3).
 *
 *     En ningún error se guarda el reporte.
 */
class ReportesDeSoporteController extends Controller
{
    public function __invoke(EnviarReporteRequest $peticion, RecepcionDeReporte $recepcion): JsonResponse
    {
        /** @var Usuario $usuario */
        $usuario = $peticion->user();

        $reporte = $recepcion->recibir(
            $usuario,
            $peticion->validated('descripcion'),
            $peticion->validated('pedido'),
        );

        return response()->json(['reporte' => new ReporteResource($reporte)], 201);
    }
}
