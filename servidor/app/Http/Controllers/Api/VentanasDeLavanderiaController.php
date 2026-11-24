<?php

namespace App\Http\Controllers\Api;

use App\Agenda\Agenda;
use App\Http\Controllers\Controller;
use App\Http\Requests\VentanasRequest;
use App\Models\Lavanderia;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * Las ventanas de una hora de una lavandería para una fecha, para el selector de fecha y hora del pedido nuevo
 * (NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #7; openspec/changes/agenda-de-recoleccion/, tareas 1.1 y
 * 1.2).
 *
 * Contrato para la app:
 *
 *     GET /api/lavanderias/{id_publico}/ventanas?fecha=2026-12-03
 *     Authorization: Bearer <token de la sesión>        (auth:sanctum y autorizar:usuario)
 *
 *     {id_publico}  el ULID de la lavandería, el de GET /api/lavanderias/cercanas y del perfil.
 *     fecha         obligatoria, AAAA-MM-DD, el día en la hora de la plaza de la lavandería. Una fecha antes de
 *                   primer_dia o después de ultimo_dia responde 200 sin ventanas.
 *
 *     200
 *     {
 *       "fecha": "2026-12-03",
 *       "zona_horaria": "America/Mexico_City",           la de la plaza, de la base de husos IANA
 *       "primer_dia": "2026-12-01",                      hoy en la plaza: la app no navega antes
 *       "ultimo_dia": "2026-12-04",                      hoy más los días hacia adelante de la plaza: ni después
 *       "dia_sin_servicio": false,                       true si ese día de la semana la lavandería no tiene horario;
 *                                                        false con ventanas vacías es un día con horario sin ventanas
 *                                                        que ofrecer (ya pasaron, o no caben en la anticipación)
 *       "ventanas": [
 *         {
 *           "inicio": "2026-12-03T09:00:00-06:00",       ISO 8601 con el desfase de la plaza: un instante absoluto
 *           "fin": "2026-12-03T10:00:00-06:00",          siempre una hora después del inicio
 *           "fecha": "2026-12-03",                       en la hora de la plaza
 *           "hora_inicio": "09:00",                      en la hora de la plaza, para mostrar tal cual
 *           "hora_fin": "10:00"
 *         }
 *       ]                                               de la más temprana a la más tarde, solo las que empiezan
 *                                                       después de ahora más la anticipación mínima; vacía si no hay
 *     }
 *
 *     401  { "message": "Unauthenticated." }            sin sesión
 *     403  { "message": "Esta cuenta no tiene acceso." }  la cuenta no tiene el rol de usuario
 *     404  { "message": "No existe la lavandería." }      no existe, o el usuario no la ve (Lavanderia::visiblesParaElUsuario())
 *     422  { "message": "...", "errors": { "fecha": ["Falta la fecha."] } }
 *          o "La fecha va como AAAA-MM-DD, en la hora de la plaza."
 *     503  { "message": "La agenda de la plaza no está configurada." }  a la plaza le falta una de las dos claves de
 *                                                       su agenda en parametros_plaza (App\Agenda\ParametrosDeAgenda)
 *
 * La app muestra la hora de hora_inicio y hora_fin, que ya es la de la plaza, y no la convierte a la del teléfono. Para
 * pedir, manda de vuelta inicio y fin tal como llegaron: el servidor los valida con App\Agenda\Agenda::validar(), que
 * responde 422 con { "errors": { "ventana": ["<motivo>"] } } si la ventana ya no se ofrece (agenda-E09).
 */
class VentanasDeLavanderiaController extends Controller
{
    public function __invoke(VentanasRequest $peticion, Agenda $agenda, string $lavanderia): JsonResponse
    {
        $encontrada = Lavanderia::query()
            ->visiblesParaElUsuario()
            // HasUlids guarda el ULID en minúsculas.
            ->where('id_publico', Str::lower($lavanderia))
            ->with('plaza')
            ->first();

        abort_if($encontrada === null, 404, 'No existe la lavandería.');

        return response()->json($agenda->dia($encontrada, $peticion->validated('fecha'))->toArray());
    }
}
