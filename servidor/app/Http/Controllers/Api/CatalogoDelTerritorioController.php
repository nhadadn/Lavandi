<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ColoniasRequest;
use App\Http\Resources\ColoniaResource;
use App\Http\Resources\MunicipioResource;
use App\Models\Colonia;
use App\Models\Municipio;
use Illuminate\Http\JsonResponse;

/**
 * El catálogo del territorio para la dirección asistida: la lista de municipios y las colonias de un código postal
 * (NSV-2026-001-v8.pdf:4:Cláusula I y :16:ANEXO A, Sección A.3, Módulo A #8; C-238; openspec/changes/direcciones/,
 * tarea 4.3). Lo carga el comando direcciones:cargar-catalogo; aquí solo se lee. El catálogo ayuda y no bloquea:
 * POST /api/direcciones no compara la colonia ni el código postal con él (direcciones-E20).
 *
 * Contrato para la app. Las dos rutas llevan sesión y pasan por el componente de autorización, como las demás:
 *
 *     Authorization: Bearer <token de la sesión>        (auth:sanctum y autorizar:usuario)
 *
 *     401  { "message": "Unauthenticated." }              sin sesión
 *     403  { "message": "Esta cuenta no tiene acceso." }  la cuenta no tiene el rol de usuario
 *
 * ---------------------------------------------------------------------------------------------------------------------
 *
 *     GET /api/catalogos/municipios                         La lista de municipios del territorio (direcciones-E17)
 *
 *     200  {
 *            "municipios": [
 *              {
 *                "codigo": "10005",                          el que la app manda en POST /api/direcciones
 *                "nombre": "Durango",
 *                "estado": { "codigo": "10", "nombre": "Durango" }
 *              },
 *              {
 *                "codigo": "05035",
 *                "nombre": "Torreón",
 *                "estado": { "codigo": "05", "nombre": "Coahuila de Zaragoza" }
 *              }
 *            ]
 *          }
 *          Todos los municipios del catálogo, ordenados por el nombre del estado y luego por el del municipio. Los
 *          códigos y los nombres son los del archivo de Correos de México. No depende de las plazas ni de las zonas: es
 *          el territorio del contrato; que una dirección quede fuera de toda zona lo resuelve direcciones-E11.
 *
 *     200  { "municipios": [] }
 *          Sin catálogo cargado. La app lo dice: la dirección escrita no se puede guardar, y la marcada en el mapa sí,
 *          porque ahí el municipio es opcional.
 *
 * ---------------------------------------------------------------------------------------------------------------------
 *
 *     GET /api/codigos-postales/{codigo_postal}/colonias      Las colonias de un código postal (direcciones-E19 y E20)
 *
 *     200  {
 *            "codigo_postal": "34000",
 *            "colonias": [
 *              {
 *                "nombre": "Zona Centro",
 *                "tipo": "Colonia",
 *                "municipio": { "codigo": "10005", "nombre": "Durango" }
 *              }
 *            ]
 *          }
 *          Ordenadas por nombre; con el mismo nombre, por tipo y luego por el nombre del municipio. Cada una trae su
 *          municipio, para que la app lo elija si la persona todavía no eligió uno (direcciones-E19). Un código postal
 *          puede tener colonias de dos municipios. Un asentamiento que el archivo repite con el mismo nombre, tipo y
 *          municipio sale una sola vez. Solo hay colonias de los municipios del territorio.
 *
 *     200  { "codigo_postal": "99999", "colonias": [] }
 *          El código postal no está en el catálogo. No es un error: la persona escribe la colonia a mano
 *          (direcciones-E20).
 *
 *     422  { "message": "El código postal son cinco números.",
 *            "errors": { "codigo_postal": ["El código postal son cinco números."] } }
 */
class CatalogoDelTerritorioController extends Controller
{
    public function municipios(): JsonResponse
    {
        $municipios = Municipio::query()
            ->select('municipios.*')
            ->join(
                'entidades_federativas',
                'entidades_federativas.id_entidades_federativas',
                '=',
                'municipios.id_entidades_federativas',
            )
            ->with('entidadFederativa')
            ->orderBy('entidades_federativas.nombre')
            ->orderBy('municipios.nombre')
            ->get();

        return response()->json(['municipios' => MunicipioResource::collection($municipios)]);
    }

    public function colonias(ColoniasRequest $peticion): JsonResponse
    {
        $codigoPostal = $peticion->codigoPostal();

        $colonias = Colonia::query()
            ->select('colonias.*')
            ->join('municipios', 'municipios.id_municipios', '=', 'colonias.id_municipios')
            ->with('municipio')
            ->where('colonias.codigo_postal', $codigoPostal)
            ->orderBy('colonias.nombre')
            ->orderBy('colonias.tipo')
            ->orderBy('municipios.nombre')
            ->get()
            // El archivo repite algunos asentamientos, que solo cambian en su identificador de Correos de México.
            ->unique(fn (Colonia $colonia): string => implode("\0", [$colonia->nombre, $colonia->tipo, $colonia->id_municipios]))
            ->values();

        return response()->json([
            'codigo_postal' => $codigoPostal,
            'colonias' => ColoniaResource::collection($colonias),
        ]);
    }
}
