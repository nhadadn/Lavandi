<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PerfilDeLavanderiaResource;
use App\Models\Lavanderia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * El perfil de una lavandería activa (NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #5;
 * openspec/changes/perfil-de-lavanderia/, tareas 1.2 y 2.1).
 *
 * Contrato para la app:
 *
 *     GET /api/lavanderias/{id_publico}
 *     Authorization: Bearer <token de la sesión>        (auth:sanctum y autorizar:usuario)
 *
 *     {id_publico}  el ULID de la lavandería, el que trae cada lavandería de GET /api/lavanderias/cercanas.
 *
 *     200
 *     {
 *       "lavanderia": {
 *         "id_publico": "01jd8x6m3v2c9q4w5e7r8t0y1u",
 *         "nombre": "Lavandería de prueba 1",
 *         "descripcion": "Texto de la lavandería" | null,
 *         "fotos": [
 *           { "url": "https://…firmada…", "vence_en": "2026-11-22T18:00:00Z" }
 *         ],                                             todas, por su orden; la primera es la principal, la del
 *                                                        registro. Vacía si no tiene. Cada URL vence en vence_en, UTC,
 *                                                        a los FOTOS_VIGENCIA_MINUTOS de la configuración; después, y
 *                                                        sin la firma, el almacenamiento responde 403 y no entrega la
 *                                                        foto. Al volver a pedir el perfil llegan URLs nuevas
 *         "servicios": [
 *           {
 *             "codigo": "estandar" | "planchado" | "seco",
 *             "nombre": "Lavado estándar",
 *             "precio": "10.00",                         texto en pesos, siempre con dos decimales
 *             "unidad": { "codigo": "kg", "nombre": "kilo" }
 *           }
 *         ],                                             solo los que ofrece y están activos, en el orden del catálogo
 *         "calificacion_promedio": 4.25 | null,          de 1 a 5, con dos decimales; null sin calificaciones, nunca 0
 *         "numero_de_calificaciones": 4,
 *         "resenas": [
 *           { "estrellas": 5, "comentario": "Texto" | null, "fecha": "2026-11-20T15:30:00Z" }
 *         ]                                              todas, de la más reciente a la más antigua; fecha en UTC
 *       }
 *     }
 *
 *     401  { "message": "Unauthenticated." }            sin sesión
 *     403  { "message": "Esta cuenta no tiene acceso." }  la cuenta no tiene el rol de usuario
 *     404  { "message": "No existe la lavandería." }      no existe, está suspendida o dada de baja, borrada, o su
 *                                                        plaza no está activa (Lavanderia::visiblesParaElUsuario())
 *
 * Las fotos viven en el almacenamiento privado del entorno (App\Fotos\AlmacenDeFotos; ADR-0006) y solo salen como URL
 * firmada: ni su ruta ni su llave salen como campo (perfil-lavanderia-E03 y E04). Las reseñas no traen nada de quien
 * calificó (perfil-lavanderia-E08). Van todas, de la más reciente a la más antigua (design.md, resuelta el
 * 2026-11-24).
 */
class PerfilDeLavanderiaController extends Controller
{
    public function __invoke(string $lavanderia): JsonResponse
    {
        $perfil = Lavanderia::query()
            ->visiblesParaElUsuario()
            // HasUlids guarda el ULID en minúsculas.
            ->where('id_publico', Str::lower($lavanderia))
            ->with([
                'servicios' => fn (HasMany $servicios) => $servicios
                    ->where('activo', true)
                    ->whereHas('tipo', fn (Builder $tipo) => $tipo->where('activo', true))
                    ->with(['tipo', 'unidad'])
                    ->orderBy('id_tipos_servicio'),
                'calificaciones' => fn (HasMany $calificaciones) => $calificaciones
                    ->orderByDesc('created_at')
                    ->orderByDesc('id_calificaciones'),
                'fotos' => fn (HasMany $fotos) => $fotos
                    ->orderBy('orden')
                    ->orderBy('id_lavanderia_fotos'),
            ])
            ->first();

        abort_if($perfil === null, 404, 'No existe la lavandería.');

        return response()->json([
            'lavanderia' => new PerfilDeLavanderiaResource($perfil),
        ]);
    }
}
