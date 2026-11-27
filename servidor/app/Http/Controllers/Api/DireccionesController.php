<?php

namespace App\Http\Controllers\Api;

use App\Autorizacion\Autorizacion;
use App\Direcciones\AltaDeDireccion;
use App\Direcciones\DireccionNoEncontrada;
use App\Direcciones\GeocodificacionNoDisponible;
use App\Direcciones\RecogidaYEntrega;
use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarDireccionRequest;
use App\Http\Requests\RecogidaYEntregaRequest;
use App\Http\Resources\DireccionResource;
use App\Models\Lavanderia;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use LogicException;

/**
 * Las direcciones de recogida y de entrega de la cuenta (NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #8;
 * openspec/changes/direcciones/, tareas 1.2 a 1.5, y la dirección asistida de C-238, tarea 4.4).
 *
 * Contrato para la app. Todas las rutas llevan sesión y pasan por el componente de autorización:
 *
 *     Authorization: Bearer <token de la sesión>        (auth:sanctum y autorizar:usuario)
 *
 *     401  { "message": "Unauthenticated." }              sin sesión
 *     403  { "message": "Esta cuenta no tiene acceso." }  la cuenta no tiene el rol de usuario
 *
 * La dirección, como sale en todas las respuestas:
 *
 *     {
 *       "id_publico": "01jd8x6m3v2c9q4w5e7r8t0y1z",   el ULID de la dirección; la API nunca usa otro identificador
 *       "alias": "Casa" | null,
 *       "es_favorita": false,
 *       "calle": "Calle Ejemplo" | null,
 *       "numero_exterior": "123" | null,
 *       "numero_interior": "4" | null,
 *       "colonia": "Centro",                           siempre
 *       "codigo_postal": "34000" | null,
 *       "municipio": "Durango" | null,                 el nombre del municipio del catálogo; null en una del mapa sin él
 *       "estado": "Durango" | null,                    el estado de ese municipio (direcciones-E17)
 *       "referencias": "Portón gris" | null,
 *       "latitud": 24.0277,                            el punto guardado, en grados decimales (WGS 84)
 *       "longitud": -104.6532
 *     }
 *
 * ---------------------------------------------------------------------------------------------------------------------
 *
 *     POST /api/direcciones             Guarda una dirección escrita o marcada en el mapa (direcciones-E01 a E04 y E06)
 *
 *     Escrita (direcciones-E01):
 *     {
 *       "origen": "escrita",                obligatorio
 *       "calle": "Calle Ejemplo",           obligatorio, texto de hasta 255
 *       "numero_exterior": "123",           obligatorio, texto de hasta 255 ("S/N" si no tiene)
 *       "numero_interior": "4",             opcional
 *       "codigo_postal": "34000",           opcional; si viene, cinco números (direcciones-E20)
 *       "colonia": "Centro",                obligatorio, texto libre: de la lista de su código postal o a mano
 *       "municipio": "10005",               obligatorio: el codigo de un municipio de GET /api/catalogos/municipios
 *       "referencias": "Portón gris",       opcional, texto de hasta 1000
 *       "es_favorita": true,                opcional, false si no viene
 *       "alias": "Casa"                     obligatorio si es_favorita es true; texto de hasta 255
 *     }
 *     No lleva latitud ni longitud: el punto sale de una sola consulta de geocodificación, que nunca se repite. El
 *     texto que se geocodifica lleva el municipio y el estado del catálogo: "Calle Ejemplo 123, Centro, 34000 Durango,
 *     Durango". La colonia y el código postal no se comparan con el catálogo (direcciones-E20).
 *
 *     Marcada en el mapa (direcciones-E04):
 *     {
 *       "origen": "mapa",                   obligatorio
 *       "latitud": 24.0277,                 obligatorio, número de -90 a 90: el punto del pin
 *       "longitud": -104.6532,              obligatorio, número de -180 a 180
 *       "colonia": "Centro",                obligatorio
 *       "calle", "numero_exterior", "numero_interior", "codigo_postal", "referencias":  opcionales
 *       "municipio": "10005",               opcional; si viene, el codigo de un municipio del catálogo
 *       "es_favorita", "alias":             como en la escrita
 *     }
 *     Se guarda con el punto del pin, sin consultar la geocodificación.
 *
 *     La plaza de la dirección es la de la zona de servicio activa que contiene el punto. Una dirección fuera de toda
 *     zona se guarda igual, pero no se acepta como recogida ni como entrega (direcciones-E11).
 *
 *     201  { "direccion": { ...la dirección... } }
 *
 *     422  { "message": "...", "errors": { "colonia": ["Falta la colonia."], ... } }
 *          Falta un campo o no es válido. No se consultó la geocodificación (direcciones-E02). Entre ellos:
 *     422  { "message": "Elige un municipio de la lista.",
 *            "errors": { "municipio": ["Elige un municipio de la lista."] } }
 *          El código no está en el catálogo: fuera del territorio, como Saltillo, o inventado (direcciones-E18).
 *     422  { "message": "El código postal son cinco números.",
 *            "errors": { "codigo_postal": ["El código postal son cinco números."] } }
 *     422  { "message": "No encontramos la dirección. Márcala en el mapa.", "motivo": "direccion_no_encontrada" }
 *          La geocodificación no la encontró. No se guardó nada; la app ofrece marcarla en el mapa (direcciones-E03).
 *     503  { "message": "No pudimos buscar la dirección. Márcala en el mapa o inténtalo más tarde.",
 *            "motivo": "geocodificacion_no_disponible" }
 *          El servicio de geocodificación no respondió. No se guardó nada.
 *
 * ---------------------------------------------------------------------------------------------------------------------
 *
 *     GET /api/direcciones/favoritas                             La lista de favoritas de la cuenta (direcciones-E06)
 *
 *     200  { "direcciones": [ { ...la dirección... } ] }
 *          Solo las favoritas de la cuenta, en el orden en que se guardaron; vacía si no tiene. Cada una trae su punto
 *          guardado: elegirla no consulta la geocodificación (direcciones-E07). Las que no son favoritas no salen
 *          (direcciones-E08).
 *
 * ---------------------------------------------------------------------------------------------------------------------
 *
 *     GET /api/direcciones/{id_publico}                          Una dirección de la cuenta (direcciones-E14)
 *
 *     {id_publico}  el ULID de la dirección, el que trae cada dirección de las otras rutas.
 *
 *     200  { "direccion": { ...la dirección... } }
 *     404  { "message": "No existe la dirección." }
 *          No existe, está borrada o es de otra cuenta: las tres responden igual y sin ningún dato. Un valor que no es
 *          ULID también responde 404.
 *
 * ---------------------------------------------------------------------------------------------------------------------
 *
 *     POST /api/direcciones/recogida-y-entrega      Valida la recogida y la entrega de un pedido (direcciones-E09 a E12)
 *
 *     {
 *       "lavanderia": "01jd8x6m3v2c9q4w5e7r8t0y1z",   obligatorio: el id_publico de la lavandería elegida
 *       "recogida": "01jd8x6m3v2c9q4w5e7r8t0y1v",     obligatorio: el id_publico de una dirección de la cuenta
 *       "entrega": "01jd8x6m3v2c9q4w5e7r8t0y1w"       opcional: sin entrega, o null, se entrega en la misma
 *     }                                               dirección de la recogida (direcciones-E09)
 *
 *     200  { "recogida": { ...la dirección... }, "entrega": { ...la dirección... } }
 *          Las dos sirven para un pedido con esa lavandería. Con la entrega en la misma dirección, las dos son la misma.
 *          El servidor no guarda nada: la app las lleva hasta el resumen del pedido, y POST /api/pedidos las vuelve a
 *          validar y las copia al crear el pedido (resumen-y-confirmacion). No se consulta la geocodificación: se usa
 *          el punto guardado (direcciones-E07).
 *
 *     422  {
 *            "message": "La dirección está fuera de la zona de servicio.",
 *            "errors":  { "recogida": ["La dirección está fuera de la zona de servicio."],
 *                         "entrega":  ["Esta lavandería no da servicio en esa dirección."] },
 *            "motivos": { "recogida": "fuera_de_la_zona_de_servicio",
 *                         "entrega":  "fuera_de_la_plaza_de_la_lavanderia" }
 *          }
 *          Una o las dos no sirven; solo vienen las rechazadas, cada una con su motivo. La app lo dice y deja elegir
 *          otra. Los motivos:
 *            fuera_de_la_zona_de_servicio        el punto no cae en ninguna zona de servicio activa (direcciones-E11)
 *            fuera_de_la_plaza_de_la_lavanderia  cae en una zona de otra plaza que la de la lavandería (direcciones-E12)
 *     422  { "message": "...", "errors": { "recogida": ["Falta la dirección de recogida."] } }
 *          Falta la lavandería o la recogida.
 *     404  { "message": "No existe la lavandería." }
 *          No existe, o el usuario no la ve (suspendida, dada de baja, borrada o de una plaza no activa).
 *     404  { "message": "No existe la dirección." }
 *          La recogida o la entrega no existe, está borrada o es de otra cuenta: las tres responden igual
 *          (direcciones-E14).
 */
class DireccionesController extends Controller
{
    public function __construct(
        private readonly Autorizacion $autorizacion,
        private readonly RecogidaYEntrega $recogidaYEntrega,
    ) {}

    public function guardar(GuardarDireccionRequest $peticion, AltaDeDireccion $alta): JsonResponse
    {
        /** @var Usuario $usuario */
        $usuario = $peticion->user();

        $municipio = $peticion->municipio();

        try {
            $direccion = $peticion->esDelMapa()
                ? $alta->guardarDelMapa($usuario, $peticion->validated(), $peticion->pin(), $municipio)
                // La escrita siempre trae su municipio: la validación lo exige.
                : $alta->guardarEscrita(
                    $usuario,
                    $peticion->validated(),
                    $municipio ?? throw new LogicException('Una dirección escrita sin municipio pasó la validación.'),
                );
        } catch (DireccionNoEncontrada) {
            return response()->json([
                'message' => 'No encontramos la dirección. Márcala en el mapa.',
                'motivo' => 'direccion_no_encontrada',
            ], 422);
        } catch (GeocodificacionNoDisponible $error) {
            report($error);

            return response()->json([
                'message' => 'No pudimos buscar la dirección. Márcala en el mapa o inténtalo más tarde.',
                'motivo' => 'geocodificacion_no_disponible',
            ], 503);
        }

        return response()->json(['direccion' => new DireccionResource($direccion)], 201);
    }

    public function favoritas(Request $peticion): JsonResponse
    {
        /** @var Usuario $usuario */
        $usuario = $peticion->user();

        $favoritas = $this->autorizacion->direccionesVisibles($usuario)
            ->where('es_favorita', true)
            ->conCoordenadas()
            ->with('municipio.entidadFederativa')
            ->orderBy('id_direcciones')
            ->get();

        return response()->json(['direcciones' => DireccionResource::collection($favoritas)]);
    }

    public function mostrar(Request $peticion, string $direccion): JsonResponse
    {
        /** @var Usuario $usuario */
        $usuario = $peticion->user();

        return response()->json([
            'direccion' => new DireccionResource($this->recogidaYEntrega->direccion($usuario, $direccion)),
        ]);
    }

    /**
     * La lógica vive en App\Direcciones\RecogidaYEntrega, que reusa la creación del pedido (confirmacion-E08).
     */
    public function recogidaYEntrega(RecogidaYEntregaRequest $peticion): JsonResponse
    {
        /** @var Usuario $usuario */
        $usuario = $peticion->user();

        $lavanderia = Lavanderia::query()
            ->visiblesParaElUsuario()
            // HasUlids guarda el ULID en minúsculas.
            ->where('id_publico', Str::lower($peticion->validated('lavanderia')))
            ->first();

        if ($lavanderia === null) {
            return response()->json(['message' => 'No existe la lavandería.'], 404);
        }

        // 404 si una no es de la cuenta (DireccionNoVisible); 422 con los motivos si no sirven
        // (DireccionesFueraDeServicio).
        $direcciones = $this->recogidaYEntrega->elegir(
            $usuario,
            $peticion->validated('recogida'),
            $peticion->validated('entrega'),
        );
        $this->recogidaYEntrega->validar($direcciones, $lavanderia);

        return response()->json([
            'recogida' => new DireccionResource($direcciones->recogida),
            'entrega' => new DireccionResource($direcciones->entrega),
        ]);
    }
}
