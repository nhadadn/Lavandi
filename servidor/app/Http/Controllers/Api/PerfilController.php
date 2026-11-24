<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CambiarPerfilRequest;
use App\Http\Requests\SubirFotoDePerfilRequest;
use App\Http\Resources\PerfilResource;
use App\Identidad\FotoDePerfil;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * El perfil de la cuenta: su nombre y su foto (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo A #15;
 * openspec/changes/perfil-y-soporte/, tareas 1.1 y 1.2).
 *
 * Contrato para la app. Las tres rutas llevan sesión y pasan por el componente de autorización. Siempre son el perfil
 * de la cuenta de la sesión: la ruta no recibe de quién, así que no hay forma de pedir el de otra.
 *
 *     Authorization: Bearer <token de la sesión>        (auth:sanctum y autorizar:usuario)
 *
 *     401  { "message": "Unauthenticated." }              sin sesión
 *     403  { "message": "Esta cuenta no tiene acceso." }  la cuenta no tiene el rol de usuario
 *
 * El perfil, como sale en las tres respuestas:
 *
 *     {
 *       "id_publico": "01jd8x6m3v2c9q4w5e7r8t0y1z",   el ULID de la cuenta, el mismo de GET /api/cuenta
 *       "nombre": "Ana Prueba" | null,                vacío hasta que la persona lo escribe: el registro no lo pide
 *       "correo": "ana@ejemplo.com",                  solo para mostrarlo: no se cambia desde el perfil
 *       "foto": { "url": "https://…firmada…", "vence_en": "2026-11-22T18:00:00Z" } | null
 *                                                    null hasta que sube una. La URL vence en vence_en, UTC, a los
 *                                                    FOTOS_VIGENCIA_MINUTOS de la configuración; después, y sin la
 *                                                    firma, el almacenamiento responde 403. Al volver a pedir el perfil
 *                                                    llega otra (perfil-usuario-E03)
 *     }
 *
 * La foto vive en el almacenamiento privado de fotos del entorno (ADR-0006); su ruta nunca sale como campo. Ninguna
 * respuesta lleva un número de tarjeta (perfil-usuario-E06).
 *
 * ---------------------------------------------------------------------------------------------------------------------
 *
 *     GET /api/perfil                                              El perfil de la cuenta (perfil-usuario-E01)
 *
 *     200  { "perfil": { ...el perfil... } }
 *
 * ---------------------------------------------------------------------------------------------------------------------
 *
 *     PATCH /api/perfil                                         Cambia el nombre (perfil-usuario-E01 y E02)
 *
 *     {
 *       "nombre": "Ana Prueba"     obligatorio: texto de hasta 255, sin contar los espacios de los lados, que se quitan
 *     }
 *     Cualquier otro campo se ignora y no se guarda, también un número de tarjeta (perfil-usuario-E06).
 *
 *     200  { "perfil": { ...el perfil, con el nombre nuevo... } }
 *     422  { "message": "Escribe tu nombre.", "errors": { "nombre": ["Escribe tu nombre."] } }
 *          El nombre falta, viene vacío o solo con espacios (perfil-usuario-E02). Los otros motivos:
 *            "El nombre va en texto."
 *            "El nombre es demasiado largo."
 *          En ningún error cambia el nombre que había.
 *
 * ---------------------------------------------------------------------------------------------------------------------
 *
 *     POST /api/perfil/foto                                 Pone la foto de perfil (perfil-usuario-E03 y E04)
 *
 *     multipart/form-data:
 *       foto    obligatorio: una imagen de los tipos de config/fotos.php (jpg, jpeg, png o webp) y de hasta su peso
 *               máximo (FOTOS_PESO_MAXIMO_KB, 5120 KB por omisión). La app la comprime antes (tarea 2.2).
 *     Cualquier otro campo se ignora.
 *
 *     200  { "perfil": { ...el perfil, con la foto nueva... } }
 *          Reemplaza a la anterior, si había, y el archivo anterior se borra.
 *     422  { "message": "Elige una foto.", "errors": { "foto": ["Elige una foto."] } }
 *          No viene la foto. Los otros motivos, en el mismo campo:
 *            "La foto debe ser una imagen: jpg, jpeg, png, webp."   no es un archivo, no es una imagen o es de otro tipo
 *            "La foto pesa más de 5120 KB."                         pasa el peso máximo de la configuración
 *          En ningún error cambia la foto que había, ni se guarda un archivo (perfil-usuario-E04).
 */
class PerfilController extends Controller
{
    public function mostrar(Request $peticion): JsonResponse
    {
        return response()->json(['perfil' => new PerfilResource($peticion->user())]);
    }

    public function cambiar(CambiarPerfilRequest $peticion): JsonResponse
    {
        /** @var Usuario $usuario */
        $usuario = $peticion->user();

        $usuario->nombre = $peticion->validated('nombre');
        $usuario->save();

        return response()->json(['perfil' => new PerfilResource($usuario)]);
    }

    public function subirFoto(SubirFotoDePerfilRequest $peticion, FotoDePerfil $fotoDePerfil): JsonResponse
    {
        /** @var Usuario $usuario */
        $usuario = $peticion->user();

        $fotoDePerfil->poner($usuario, $peticion->foto());

        return response()->json(['perfil' => new PerfilResource($usuario)]);
    }
}
