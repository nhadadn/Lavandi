<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LavanderiasCercanasRequest;
use App\Lavanderias\ConsultaDeCercanas;
use App\Lavanderias\LavanderiaCercana;
use Illuminate\Http\JsonResponse;

/**
 * Las lavanderías cercanas activas a un punto, para el mapa de la pantalla principal y la búsqueda con filtros
 * (NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #3 y #4; openspec/changes/lavanderias-cercanas/, tareas 1.1 y
 * 1.3).
 *
 * Contrato para la app:
 *
 *     GET /api/lavanderias/cercanas?latitud=10.5&longitud=-119.5&distancia_maxima_metros=3000&calificacion_minima=4
 *     Authorization: Bearer <token de la sesión>        (auth:sanctum y autorizar:usuario)
 *
 *     latitud                  obligatorio, número de -90 a 90, en grados decimales (WGS 84)
 *     longitud                 obligatorio, número de -180 a 180
 *     distancia_maxima_metros  opcional, entero de 1 en adelante; la distancia se compara redondeada al metro
 *     calificacion_minima      opcional, número de 1 a 5; una lavandería sin calificaciones no lo pasa
 *     Los dos filtros se combinan: con los dos, salen solo las que cumplen los dos (cercanas-E18).
 *
 *     200
 *     {
 *       "lavanderias": [
 *         {
 *           "id_publico": "01jd8x6m3v2c9q4w5e7r8t0y1u",  el de GET /api/lavanderias/{id_publico}, el perfil
 *           "nombre": "Lavandería de prueba 1",
 *           "latitud": 10.5,                              la ubicación de su pin
 *           "longitud": -119.5,
 *           "distancia_metros": 2190,                     en línea recta desde el punto, entero, en metros
 *           "calificacion_promedio": 4.8 | null,          de 1 a 5, con dos decimales; null sin calificaciones
 *           "numero_de_calificaciones": 5
 *         }
 *       ]                                               vacía, sin error, si no hay ninguna (cercanas-E11)
 *     }
 *
 *     401  { "message": "Unauthenticated." }            sin sesión (cercanas-E10)
 *     403  { "message": "Esta cuenta no tiene acceso." }  la cuenta no tiene el rol de usuario
 *     422  { "message": "...", "errors": { "latitud": ["..."], "distancia_maxima_metros": ["..."] } }
 *
 * Solo aparecen las activas de una plaza activa (cercanas-E07). Mientras DD-083 no responda (tarea 1.4), no hay
 * límite de cercanía, no hay filtro de disponibilidad, y el orden es provisional: de la más cercana a la más lejana. La
 * app no debe depender del orden. Si una lavandería sin calificaciones debe pasar el filtro de calificación también es
 * pregunta de DD-083; hoy no lo pasa.
 */
class LavanderiasCercanasController extends Controller
{
    public function __invoke(LavanderiasCercanasRequest $peticion, ConsultaDeCercanas $consulta): JsonResponse
    {
        $distancia = $peticion->validated('distancia_maxima_metros');
        $calificacion = $peticion->validated('calificacion_minima');

        $lavanderias = $consulta->buscar(
            latitud: (float) $peticion->validated('latitud'),
            longitud: (float) $peticion->validated('longitud'),
            distanciaMaximaMetros: $distancia === null ? null : (int) $distancia,
            calificacionMinima: $calificacion === null ? null : (float) $calificacion,
        );

        return response()->json([
            'lavanderias' => $lavanderias->map(fn (LavanderiaCercana $lavanderia): array => $lavanderia->toArray())->all(),
        ]);
    }
}
