<?php

namespace App\Http\Controllers\Api\Administracion;

use App\Autorizacion\Autorizacion;
use App\Avisos\CreacionDeAvisoMasivo;
use App\Http\Controllers\Controller;
use App\Http\Resources\Administracion\AvisoMasivoDeLaAdministracionResource;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Los avisos masivos de la administración en la app: escribir un aviso con título y texto, mandarlo a todos los teléfonos
 * registrados y ver los enviados (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #7; administracion-app-E42 y
 * E43). La regla es la del panel (administracion-E23): el aviso lo crea App\Avisos\CreacionDeAvisoMasivo, la misma clase
 * que usa el panel, y la lista sale de Autorizacion::avisosMasivosDelPanel() (ADR-0022, decisiones 9 y 16). Este
 * controlador no valida ni calcula. El aviso es global: el aviso a un segmento espera DD-113 (administracion-app-E44).
 *
 * Contrato para la app. Las rutas llevan sesión y pasan por el componente de autorización:
 *
 *     Authorization: Bearer <token de administración>   (auth:sanctum y autorizar:administracion)
 *
 *     401  { "message": "Unauthenticated." }              sin sesión, o con el token vencido o revocado
 *     403  { "message": "Esta cuenta no tiene acceso." }  la cuenta no es de administración, o su token no lleva la
 *                                                         habilidad de administración (administracion-app-E06 y E07)
 *
 * ---------------------------------------------------------------------------------------------------------------------
 *
 *     GET /api/administracion/avisos-masivos               Los avisos enviados (administracion-app-E43)
 *
 *     200  {
 *            "avisos": [
 *              {
 *                "titulo": "Mantenimiento",
 *                "mensaje": "La app no estará disponible el domingo.",
 *                "enviado_en": "2027-03-15T09:05:00Z",     cuándo lo mandaron, en UTC
 *                "enviado_por_esta_cuenta": true,          quién lo mandó: true si fue la cuenta de la sesión, cuyo
 *                                                          correo es el de GET /api/cuenta; false si fue otra cuenta
 *                                                          de administración, cuyo correo, nombre y llave no salen aquí
 *                "destinatarios": 3                       a cuántos teléfonos salió; null mientras sigue en la cola,
 *              }                                           y la app dice "En cola"
 *            ]
 *          }
 *          Todos los avisos enviados, los mandara la cuenta de administración que fuera, como en el panel: del más
 *          reciente al más antiguo, sin páginas. Sin ninguno, "avisos" llega vacío. Por qué "quién" es "esta cuenta u
 *          otra": openspec/changes/administracion-en-la-app/design.md, F-D07 (delta de autorizacion-E14).
 *
 *     POST /api/administracion/avisos-masivos              Manda un aviso global (administracion-app-E42)
 *
 *     {
 *       "titulo": "Mantenimiento",                         obligatorio: texto de hasta 255 caracteres
 *       "mensaje": "La app no estará disponible el domingo."   obligatorio: texto de hasta 1000 caracteres
 *     }                                                    Los dos se guardan sin los espacios de los extremos, que
 *                                                          quita CreacionDeAvisoMasivo antes de contar sus caracteres,
 *                                                          igual que en el panel; cualquier otro campo se ignora
 *
 *     201  { "aviso": { ... } }
 *          El aviso guardado, con la forma de la lista y "destinatarios": null: sale en cola a todas las cuentas de la
 *          app con un teléfono registrado para avisos, salvo las bloqueadas, sin que la respuesta espere a FCM.
 *          Confirmar que sale a todos los teléfonos registrados es de la app, antes de llamar aquí.
 *     422  { "message": "...", "errors": { "titulo": ["Escribe el título."] } }
 *          Un error por campo, con los mensajes de CreacionDeAvisoMasivo::mensajes():
 *            titulo   "Escribe el título."                           falta, llega null, vacío o solo con espacios
 *            titulo   "El título va en texto."                       un número, una lista
 *            titulo   "El título no puede pasar de 255 caracteres."
 *            mensaje  "Escribe el texto."                            falta, llega null, vacío o solo con espacios
 *            mensaje  "El texto va en texto."                        un número, una lista
 *            mensaje  "El texto no puede pasar de 1000 caracteres."
 *          No se guarda nada y no sale nada.
 */
class AvisosMasivosController extends Controller
{
    public function __construct(private readonly Autorizacion $autorizacion) {}

    public function lista(Request $peticion): JsonResponse
    {
        /** @var Usuario $cuenta */
        $cuenta = $peticion->user();

        $avisos = $this->autorizacion->avisosMasivosDelPanel($cuenta)
            ->orderByDesc('enviado_en')
            ->orderByDesc('id_avisos_masivos')
            ->get();

        return response()->json(['avisos' => AvisoMasivoDeLaAdministracionResource::collection($avisos)]);
    }

    public function crear(Request $peticion, CreacionDeAvisoMasivo $creacion): JsonResponse
    {
        /** @var Usuario $cuenta */
        $cuenta = $peticion->user();

        abort_unless($this->autorizacion->puedeMandarAvisosMasivos($cuenta), 403, 'Esta cuenta no tiene acceso.');

        $aviso = $creacion->crear($cuenta, $peticion->input('titulo'), $peticion->input('mensaje'));

        return response()->json(['aviso' => new AvisoMasivoDeLaAdministracionResource($aviso)], 201);
    }
}
