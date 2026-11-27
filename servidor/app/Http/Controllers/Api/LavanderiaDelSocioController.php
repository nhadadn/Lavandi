<?php

namespace App\Http\Controllers\Api;

use App\Autorizacion\Autorizacion;
use App\Direcciones\GeocodificacionNoDisponible;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegistroDeLavanderiaRequest;
use App\Http\Resources\LavanderiaDelSocioResource;
use App\Lavanderias\EdicionDeLavanderia;
use App\Lavanderias\RegistroDeLavanderia;
use App\Models\Lavanderia;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * La lavandería del socio en el Módulo B: su registro (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo B #1;
 * openspec/changes/alta-de-lavanderias/, tarea 1.1) y su perfil y horarios, con la descripción y las fotos de su perfil
 * (:17:Módulo B #6; tareas 2.1 a 2.4). El mismo contrato está en el design.md del cambio, sección "El contrato de la
 * API".
 *
 * Todas las rutas llevan sesión y pasan por el componente de autorización con el rol de socio lavandería:
 *
 *     Authorization: Bearer <token de la sesión>        (auth:sanctum y autorizar:lavanderia)
 *
 *     401  { "message": "Unauthenticated." }              sin sesión
 *     403  { "message": "Esta cuenta no tiene acceso." }  la cuenta no tiene el rol de socio lavandería
 *
 * La lavandería, como sale en todas las respuestas:
 *
 *     {
 *       "id_publico": "01jd8x6m3v2c9q4w5e7r8t0y1z",       el ULID de la lavandería; la API nunca usa otro
 *       "nombre": "Lavandería Ejemplo",
 *       "direccion": {
 *         "calle": "Calle Ejemplo",
 *         "numero": "123",
 *         "colonia": "Centro",
 *         "codigo_postal": "34000",
 *         "municipio": { "codigo": "10005", "nombre": "Durango", "estado": "Durango" } | null
 *       },
 *       "descripcion": "Entregamos el mismo día." | null, la del perfil, opcional (alta-lavanderias-E26)
 *       "foto": { "url": "https://…firmada…", "vence_en": "2026-11-22T18:00:00Z" } | null,
 *                                                        la principal, la primera de fotos; la URL vence, y al volver
 *                                                        a pedir la lavandería llega otra
 *       "fotos": [
 *         { "url": "https://…firmada…", "vence_en": "2026-11-22T18:00:00Z" }
 *       ],                                               todas las del perfil, en su orden; la primera es la principal
 *                                                        (alta-lavanderias-E27 a E29)
 *       "maximo_de_fotos": 5,                            cuántas puede tener, de config/fotos.php (alta-lavanderias-E30)
 *       "servicios": [
 *         { "tipo": "estandar", "nombre": "Lavado estándar", "precio": "18.50",
 *           "unidad": { "codigo": "kg", "nombre": "kilo" } }
 *       ],                                               los que ofrece, en el orden del catálogo
 *       "ventanas": [
 *         { "dia_semana": 4, "hora_inicio": "10:00", "hora_fin": "11:00" }
 *       ]                                                por día (1, el lunes, a 7, el domingo) y por hora, en la
 *     }                                                  hora local de la plaza de la lavandería
 *
 * ---------------------------------------------------------------------------------------------------------------------
 *
 *     POST /api/socio/lavanderias           Registra la lavandería de la cuenta (alta-lavanderias-E01, E02, E05, E06,
 *                                           E09 y E10)
 *
 *     multipart/form-data, porque lleva la foto:
 *
 *       nombre                     obligatorio, texto de hasta 255
 *       direccion[calle]           obligatorio, texto de hasta 255
 *       direccion[numero]          obligatorio, texto de hasta 255 ("S/N" si no tiene)
 *       direccion[colonia]         obligatorio, texto de hasta 255
 *       direccion[codigo_postal]   obligatorio, cinco números
 *       direccion[municipio]       obligatorio: el codigo de un municipio de GET /api/catalogos/municipios
 *       foto                       obligatorio: una imagen de los tipos de config/fotos.php (jpg, jpeg, png o webp)
 *                                  y de hasta su peso máximo (5120 KB por omisión). La app la comprime antes.
 *       servicios[N][tipo]         al menos uno: estandar o planchado (el lavado en seco espera DD-010)
 *       servicios[N][precio]       obligatorio: pesos por kilo, sin signo y con hasta dos decimales, como "18.50"
 *       ventanas[N][dia_semana]    al menos una: 1, el lunes, a 7, el domingo
 *       ventanas[N][hora_inicio]   obligatorio, HH:MM en la hora de la plaza
 *       ventanas[N][hora_fin]      obligatorio, HH:MM; exactamente una hora después del inicio (24:00 vale)
 *
 *     La dirección se geocodifica una sola vez y la lavandería queda en la plaza de la zona de servicio que contiene
 *     el punto.
 *
 *     201  { "lavanderia": { ...la lavandería... } }
 *     409  { "message": "Esta cuenta ya tiene una lavandería registrada." }
 *     422  { "message": "...", "errors": { "nombre": ["Falta el nombre de la lavandería."], "foto": [...], ... } }
 *          Falta un dato o no es válido, cada uno en su campo: nombre, direccion.calle, foto, servicios.0.precio,
 *          ventanas.1... No se consultó la geocodificación ni se subió la foto (alta-lavanderias-E02).
 *     422  { "message": "La dirección está fuera de las zonas de servicio.",
 *            "errors": { "direccion": ["La dirección está fuera de las zonas de servicio."] },
 *            "motivo": "fuera_de_la_zona_de_servicio" | "direccion_no_encontrada" }
 *          La geocodificación no ubicó la dirección, o su punto no cae en ninguna zona de servicio activa. No se guardó
 *          nada (alta-lavanderias-E10).
 *     503  { "message": "No pudimos ubicar la dirección. Inténtalo más tarde.", "motivo": "geocodificacion_no_disponible" }
 *          El servicio de geocodificación no respondió. No se guardó nada.
 *
 * ---------------------------------------------------------------------------------------------------------------------
 *
 *     GET /api/socio/lavanderias                 Las lavanderías de la cuenta (alta-lavanderias-E18 y E19)
 *
 *     200  { "lavanderias": [ { ...la lavandería... } ] }
 *          Solo las de la cuenta: hoy, a lo más una. Vacía si todavía no registra la suya. Así la app sabe, al entrar,
 *          si abre el registro o el perfil, y con qué id_publico.
 *
 * ---------------------------------------------------------------------------------------------------------------------
 *
 *     GET /api/socio/lavanderias/{id_publico}    Su lavandería, con su información y sus ventanas actuales
 *                                                (alta-lavanderias-E18, E19 y E20)
 *
 *     200  { "lavanderia": { ...la lavandería... } }
 *     404  { "message": "No existe la lavandería." }
 *          No existe, está borrada o es de otra cuenta: las tres responden igual y sin ningún dato. Un valor que no es
 *          ULID también responde 404.
 *
 * ---------------------------------------------------------------------------------------------------------------------
 *
 *     PATCH /api/socio/lavanderias/{id_publico}  Edita su información, su descripción, sus fotos y sus ventanas
 *                                                (alta-lavanderias-E18, E20, E21, E22, E25 y E26 a E30)
 *
 *     Los mismos campos del registro, con las mismas reglas, más la descripción y la lista de fotos; solo van los que
 *     cambian:
 *       - nombre;
 *       - direccion, completa: calle, numero, colonia, codigo_postal y municipio;
 *       - foto, que reemplaza a la principal;
 *       - servicios, la lista completa: un tipo que no viene deja de ofrecerse;
 *       - ventanas, el horario completo de la semana: reemplaza a todas las anteriores;
 *       - descripcion: texto de hasta 1000 caracteres; vacía, el perfil queda sin descripción;
 *       - fotos, la lista completa y en orden, de 1 a maximo_de_fotos; la primera es la principal. Cada elemento es una
 *         foto que ya tiene, por su lugar en "fotos" de la última lectura (0, la primera), o un archivo nuevo con las
 *         reglas de la foto del registro. Una foto que no viene se quita y su archivo se borra. No va junto con foto.
 *             JSON:       "fotos": [2, 0]
 *             multipart:  fotos[0]=2, fotos[1]=<archivo>, fotos[2]=0
 *     Sin archivos, en JSON. Con foto o con una foto nueva en fotos, en multipart/form-data por POST con el campo
 *     _method=PATCH, porque PHP solo lee los archivos de un POST.
 *
 *     La dirección nueva se geocodifica una vez; la misma que ya tiene no se vuelve a consultar.
 *
 *     200  { "lavanderia": { ...la lavandería, ya con los cambios... } }
 *     404  { "message": "No existe la lavandería." }
 *          Como en GET: una ajena no se edita ni se nombra, y sus datos no cambian (alta-lavanderias-E20). La búsqueda
 *          va antes de validar.
 *     422  Como en el registro, cada error en su campo; si algo se rechaza, no cambia nada. Los de la lista de fotos van
 *          en fotos (sin fotos, más que maximo_de_fotos, o junto con foto) o en su lugar, fotos.N (una foto que no
 *          tiene o que ya está en la lista, o un archivo que no cumple las reglas). Además:
 *     422  { "message": "La dirección nueva queda en otra plaza de servicio. Ese cambio todavía no se puede hacer.",
 *            "errors": { "direccion": ["..."] }, "motivo": "en_otra_plaza" }
 *          La dirección cae en una zona de otra plaza: la lavandería no cambia de plaza (alta-lavanderias-E25).
 *     503  Como en el registro.
 */
class LavanderiaDelSocioController extends Controller
{
    public function __construct(private readonly Autorizacion $autorizacion) {}

    public function lista(Request $peticion): JsonResponse
    {
        /** @var Usuario $cuenta */
        $cuenta = $peticion->user();

        $lavanderias = $this->autorizacion->lavanderiasDeLaCuenta($cuenta)->orderBy('id_lavanderias')->get()
            ->map(fn (Lavanderia $lavanderia): LavanderiaDelSocioResource => new LavanderiaDelSocioResource(
                LavanderiaDelSocioResource::cargar($lavanderia),
            ));

        return response()->json(['lavanderias' => $lavanderias]);
    }

    public function mostrar(Request $peticion, string $lavanderia): JsonResponse
    {
        return response()->json([
            'lavanderia' => new LavanderiaDelSocioResource(LavanderiaDelSocioResource::cargar($this->deLaCuenta($peticion, $lavanderia))),
        ]);
    }

    /**
     * La lavandería se busca antes de validar: una ajena responde 404 aunque lo que se mande no sea válido.
     */
    public function editar(Request $peticion, EdicionDeLavanderia $edicion, string $lavanderia): JsonResponse
    {
        $propia = $this->deLaCuenta($peticion, $lavanderia);

        // La validación de la edición, la misma del panel y de la API de la administración: los campos que llegan.
        $datos = $edicion->validar($peticion->all());

        try {
            $edicion->editar($propia, Arr::except($datos, ['foto']), $peticion->file('foto'));
        } catch (GeocodificacionNoDisponible $error) {
            return $this->geocodificacionNoDisponible($error);
        }

        return response()->json([
            'lavanderia' => new LavanderiaDelSocioResource(LavanderiaDelSocioResource::cargar($propia->refresh())),
        ]);
    }

    public function registrar(RegistroDeLavanderiaRequest $peticion, RegistroDeLavanderia $registro): JsonResponse
    {
        /** @var Usuario $cuenta */
        $cuenta = $peticion->user();

        try {
            $lavanderia = $registro->registrar($cuenta, Arr::except($peticion->validated(), ['foto']), $peticion->foto());
        } catch (GeocodificacionNoDisponible $error) {
            return $this->geocodificacionNoDisponible($error);
        }

        return response()->json([
            'lavanderia' => new LavanderiaDelSocioResource(LavanderiaDelSocioResource::cargar($lavanderia)),
        ], 201);
    }

    /**
     * La lavandería de la cuenta con ese ULID. Una de otra cuenta, borrada o que no existe responde el mismo 404, sin
     * ningún dato (alta-lavanderias-E20; ADR-0019, decisión 3).
     */
    private function deLaCuenta(Request $peticion, string $lavanderia): Lavanderia
    {
        /** @var Usuario $cuenta */
        $cuenta = $peticion->user();

        $encontrada = $this->autorizacion->lavanderiasDeLaCuenta($cuenta)
            // HasUlids guarda el ULID en minúsculas.
            ->where('id_publico', Str::lower($lavanderia))
            ->first();

        abort_if($encontrada === null, 404, 'No existe la lavandería.');

        return $encontrada;
    }

    /**
     * El servicio de geocodificación no respondió: no se sabe si la dirección existe, así que no se guardó nada y la
     * app puede volver a intentar. Queda en el registro, como en las direcciones del usuario.
     */
    private function geocodificacionNoDisponible(GeocodificacionNoDisponible $error): JsonResponse
    {
        report($error);

        return response()->json([
            'message' => GeocodificacionNoDisponible::MENSAJE,
            'motivo' => GeocodificacionNoDisponible::MOTIVO,
        ], 503);
    }
}
