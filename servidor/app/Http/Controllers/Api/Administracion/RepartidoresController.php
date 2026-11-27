<?php

namespace App\Http\Controllers\Api\Administracion;

use App\Autorizacion\Autorizacion;
use App\Http\Controllers\Controller;
use App\Http\Resources\Administracion\RepartidorDeLaAdministracionResource;
use App\Models\Repartidor;
use App\Models\Usuario;
use App\Repartidores\EdicionDeRepartidor;
use App\Repartidores\EstadoDelRepartidor;
use App\Repartidores\PlazasDelAlta;
use App\Repartidores\TransicionNoPermitida;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * La gestión de repartidores de la administración en la app (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #2,
 * "Alta, baja, suspensión y edición de socios repartidores"; :18:Módulo C #1, "El alta, validación y autorización del
 * repartidor corresponden a EL CLIENTE"): la lista, la ficha, la edición, el alta en una plaza, la suspensión, su
 * levantamiento y la baja (administracion-app-E19 a E23). Las plazas del alta están en PlazasController.
 *
 * No valida ni calcula por su cuenta: lo que la administración ve sale de Autorizacion::repartidoresDelPanel(), los
 * cambios de estado de App\Repartidores\EstadoDelRepartidor, la edición de App\Repartidores\EdicionDeRepartidor y la
 * plaza del alta de App\Repartidores\PlazasDelAlta, las mismas clases que usa el panel (ADR-0022, decisión 9; las reglas
 * de repartidor-E05 a E08 y E31). El registro del repartidor es del Módulo C: aquí no se crea ninguno. Su posición no
 * sale (DD-077).
 *
 * Contrato para la app. Todas las rutas llevan sesión y pasan por el componente de autorización con el rol de
 * administración y el token de administración (ADR-0022, decisiones 7 y 8):
 *
 *     Authorization: Bearer <token de administración>    (auth:sanctum y autorizar:administracion)
 *
 *     401  { "message": "Unauthenticated." }              sin sesión, o con el token vencido o revocado
 *     403  { "message": "Esta cuenta no tiene acceso." }  la cuenta no es de administración, o su token no lleva la
 *                                                         habilidad de administración
 *     404  { "message": "No existe el repartidor." }      el repartidor no existe, o el valor no es un ULID: los dos
 *                                                         responden igual y sin ningún dato (ADR-0019, decisión 3)
 *
 * El repartidor en la ficha, como sale en todas las respuestas que la traen:
 *
 *     {
 *       "id_publico": "01jd8x6m3v2c9q4w5e7r8t0y1z",   el ULID del repartidor; la API nunca usa otro
 *       "nombre": "Nombre del repartidor",
 *       "telefono": "618 123 4567",
 *       "correo": "repartidor@ejemplo.com",
 *       "foto": { "url": "https://…firmada…", "vence_en": "2026-11-22T18:00:00Z" } | null,
 *                                                     la URL vence; al volver a pedir al repartidor llega otra
 *       "tipo_vehiculo": "Motocicleta",
 *       "estado": "pendiente" | "activo" | "suspendido" | "baja",
 *       "plaza": { "nombre": "Durango" } | null,      la de su alta; null mientras está pendiente, y en la baja de uno
 *                                                     que nunca se dio de alta (repartidor-E31)
 *                                                     hasta aquí, lo mismo que ve él en su perfil, con el mismo
 *                                                     significado: el contrato de
 *                                                     App\Http\Controllers\Api\RepartidorController
 *       "transiciones": ["suspender", "dar_de_baja"]  las que su estado permite ahora, en este orden, y solo esas; la
 *     }                                               app muestra solo esos botones:
 *                                                       "dar_de_alta"         POST …/alta
 *                                                       "suspender"           POST …/suspension
 *                                                       "levantar_suspension" DELETE …/suspension
 *                                                       "dar_de_baja"         POST …/baja
 *                                                     Pendiente: dar_de_alta y dar_de_baja (repartidor-E05 y E31).
 *                                                     Activo: suspender y dar_de_baja. Suspendido: levantar_suspension
 *                                                     y dar_de_baja (repartidor-E06 y E07). De baja: ninguna, porque
 *                                                     ninguna sale de la baja. Es la forma de la ficha de la
 *                                                     lavandería (design.md, sección 4, F-D01 y F-D02).
 *
 * ---------------------------------------------------------------------------------------------------------------------
 *
 *     GET /api/administracion/repartidores          Todos los repartidores, de todas las plazas y en cualquier estado,
 *                                                   también los pendientes sin plaza y los de baja (administracion-app-E19)
 *
 *     200  { "repartidores": [
 *            { "id_publico": "01jd8x6m3v2c9q4w5e7r8t0y1z", "nombre": "Nombre del repartidor", "estado": "pendiente",
 *              "plaza": null }
 *          ] }
 *          Cada uno con su nombre, su estado y su plaza, o null sin plaza; el resto está en su ficha. Del registro más
 *          nuevo al más viejo, como la lista del panel: la pantalla los ordena por estado, con los pendientes primero
 *          (imágenes 14 y 62 de la Propuesta 6). Sin paginación ni búsqueda. Sin repartidores, la lista llega vacía.
 *
 * ---------------------------------------------------------------------------------------------------------------------
 *
 *     GET /api/administracion/repartidores/{id_publico}       La ficha del repartidor (administracion-app-E19)
 *
 *     200  { "repartidor": { ...el repartidor en la ficha... } }
 *          También la de uno de baja.
 *     404  { "message": "No existe el repartidor." }
 *
 * ---------------------------------------------------------------------------------------------------------------------
 *
 *     PATCH /api/administracion/repartidores/{id_publico}     Edita sus datos (administracion-app-E23; repartidor-E08)
 *
 *     Solo los que cambian, con las reglas del registro del repartidor:
 *       - nombre: texto, hasta 255 caracteres;
 *       - telefono: de 10 a 15 dígitos, que pueden ir con espacios, guiones, paréntesis y un + al frente;
 *       - correo: un correo, hasta 255 caracteres, que no sea de otra cuenta; el suyo propio, aunque cambie de
 *         mayúsculas, no choca;
 *       - tipo_vehiculo: texto, hasta 100 caracteres;
 *       - foto: una imagen de los tipos de config/fotos.php y de hasta su peso máximo; reemplaza a la anterior.
 *     Sin los espacios de las orillas; uno vacío o de puros espacios se rechaza como si faltara. Cualquier otro campo se
 *     ignora. La contraseña no se edita. Sin foto, en JSON. Con foto, en multipart/form-data por POST con el campo
 *     _method=PATCH, porque PHP solo lee los archivos de un POST.
 *
 *     200  { "repartidor": { ...el repartidor en la ficha, ya con los cambios... } }
 *          El repartidor ve el cambio en su perfil.
 *     404  { "message": "No existe el repartidor." }
 *          La búsqueda va antes de validar.
 *     422  { "message": "Ese correo ya tiene una cuenta.", "errors": { "correo": ["Ese correo ya tiene una cuenta."] } }
 *          Cada error en su campo, con los mensajes del registro: "Escribe tu nombre.", "El teléfono no es válido.",
 *          "El correo no es válido.", "Escribe tu tipo de vehículo.", "La foto debe ser una imagen: jpg, jpeg, png,
 *          webp."... Si algo se rechaza, no cambia nada.
 *
 * ---------------------------------------------------------------------------------------------------------------------
 *
 *     POST /api/administracion/repartidores/{id_publico}/alta     Lo da de alta en una plaza (administracion-app-E20;
 *                                                                 repartidor-E05)
 *
 *     { "plaza": "Durango" }        obligatorio: el nombre de una de las plazas de GET /api/administracion/plazas
 *
 *     200  { "repartidor": { ...el repartidor en la ficha, "activo" y con su plaza... } }
 *          Queda activo en esa plaza, con la hora del alta, y ve los pedidos que se le asignen en ella.
 *     404  { "message": "No existe el repartidor." }
 *          La búsqueda va antes de validar.
 *     422  { "message": "Elige la plaza.", "errors": { "plaza": ["Elige la plaza."] } }
 *          Sin plaza, o vacía. O, si no es una de las plazas del alta, porque no existe o no está activa:
 *            "Esa plaza no está entre las del alta."
 *          No cambia nada.
 *     409  { "message": "Un repartidor en estado activo no se puede dar de alta." }
 *          Ya no está pendiente, por ejemplo porque otra sesión lo dio de alta antes. No cambia.
 *
 * ---------------------------------------------------------------------------------------------------------------------
 *
 *     POST   /api/administracion/repartidores/{id_publico}/suspension     Lo suspende (administracion-app-E21;
 *                                                                         repartidor-E06)
 *     DELETE /api/administracion/repartidores/{id_publico}/suspension     Le levanta la suspensión
 *     POST   /api/administracion/repartidores/{id_publico}/baja           Lo da de baja (administracion-app-E22;
 *                                                                         repartidor-E07 y E31)
 *
 *     Sin cuerpo.
 *
 *     200  { "repartidor": { ...el repartidor en la ficha, en su estado nuevo... } }
 *          Suspendido, no ve ni mueve pedidos. Sin la suspensión, vuelve a estar activo en su misma plaza. De baja, ya
 *          no inicia sesión y sus sesiones abiertas dejan de servir; sus entregas se conservan, y el pendiente que nunca
 *          se dio de alta queda de baja sin plaza. La baja no se deshace.
 *     404  { "message": "No existe el repartidor." }
 *     409  { "message": "Un repartidor en estado suspendido no se puede suspender." }
 *          Su estado ya no lo permite, por ejemplo porque otra sesión lo cambió antes. No cambia. Los otros mensajes:
 *            "Un repartidor en estado activo no se puede levantar de una suspensión."
 *            "Un repartidor en estado baja no se puede dar de baja."
 *          La app muestra el mensaje y vuelve a pedir la ficha.
 */
class RepartidoresController extends Controller
{
    public function __construct(
        private readonly Autorizacion $autorizacion,
        private readonly EstadoDelRepartidor $estado,
    ) {}

    /**
     * Todos los que ve la administración, como la lista del panel, del registro más nuevo al más viejo, cada uno en su
     * resumen.
     */
    public function lista(Request $peticion): JsonResponse
    {
        $repartidores = $this->autorizacion->repartidoresDelPanel($this->cuenta($peticion))
            ->with(RepartidorDeLaAdministracionResource::RELACIONES)
            ->latest()
            ->orderByDesc('id_repartidores')
            ->get()
            ->map(fn (Repartidor $repartidor): array => RepartidorDeLaAdministracionResource::resumen($repartidor));

        return response()->json(['repartidores' => $repartidores]);
    }

    public function mostrar(Request $peticion, string $repartidor): JsonResponse
    {
        return $this->ficha($this->visible($peticion, $repartidor));
    }

    /**
     * El repartidor se busca antes de validar: uno que no existe responde 404 aunque lo que se mande no sea válido. La
     * validación es la de la edición del panel; una ValidationException responde 422 con el error de cada campo.
     */
    public function editar(Request $peticion, EdicionDeRepartidor $edicion, string $repartidor): JsonResponse
    {
        $encontrado = $this->visible($peticion, $repartidor);

        $edicion->editar($encontrado, $peticion->all());

        return $this->ficha($encontrado);
    }

    public function darDeAlta(Request $peticion, PlazasDelAlta $plazas, string $repartidor): JsonResponse
    {
        $encontrado = $this->visible($peticion, $repartidor);
        $plaza = $plazas->laElegida($this->cuenta($peticion), $peticion->input('plaza'));

        return $this->cambiar($encontrado, fn () => $this->estado->darDeAlta($encontrado, $plaza));
    }

    public function suspender(Request $peticion, string $repartidor): JsonResponse
    {
        $encontrado = $this->visible($peticion, $repartidor);

        return $this->cambiar($encontrado, fn () => $this->estado->suspender($encontrado));
    }

    public function levantarSuspension(Request $peticion, string $repartidor): JsonResponse
    {
        $encontrado = $this->visible($peticion, $repartidor);

        return $this->cambiar($encontrado, fn () => $this->estado->levantarSuspension($encontrado));
    }

    public function darDeBaja(Request $peticion, string $repartidor): JsonResponse
    {
        $encontrado = $this->visible($peticion, $repartidor);

        return $this->cambiar($encontrado, fn () => $this->estado->darDeBaja($encontrado));
    }

    /**
     * El cambio de estado y la ficha nueva. Una transición que el estado ya no permite responde 409 con el mensaje de
     * App\Repartidores\TransicionNoPermitida, sin cambiar nada.
     *
     * @param  Closure(): void  $cambio
     */
    private function cambiar(Repartidor $repartidor, Closure $cambio): JsonResponse
    {
        try {
            $cambio();
        } catch (TransicionNoPermitida $error) {
            return response()->json(['message' => $error->getMessage()], 409);
        }

        return $this->ficha($repartidor);
    }

    /**
     * La ficha, leída otra vez de la base.
     */
    private function ficha(Repartidor $repartidor): JsonResponse
    {
        return response()->json(['repartidor' => RepartidorDeLaAdministracionResource::delRepartidor($repartidor->refresh())]);
    }

    /**
     * Un repartidor de los que ve la administración, por su identificador público. Uno que no existe y un valor que no
     * es ULID responden el mismo 404, sin ningún dato (ADR-0019, decisión 3).
     */
    private function visible(Request $peticion, string $repartidor): Repartidor
    {
        $encontrado = Str::isUlid($repartidor)
            ? $this->autorizacion->repartidoresDelPanel($this->cuenta($peticion))
                // HasUlids guarda el ULID en minúsculas.
                ->where('id_publico', Str::lower($repartidor))
                ->first()
            : null;

        abort_if($encontrado === null, 404, 'No existe el repartidor.');

        return $encontrado;
    }

    private function cuenta(Request $peticion): Usuario
    {
        /** @var Usuario $cuenta */
        $cuenta = $peticion->user();

        return $cuenta;
    }
}
