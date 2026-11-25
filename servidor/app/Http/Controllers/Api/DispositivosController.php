<?php

namespace App\Http\Controllers\Api;

use App\Avisos\TelefonosRegistrados;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegistroDeTelefonoRequest;
use App\Models\Usuario;
use Illuminate\Http\Response;

/**
 * El teléfono que recibe los avisos de la cuenta (openspec/changes/avisos/, tarea 2.1; ADR-0008).
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
 *     POST /api/dispositivos                          Registra el token de Firebase del teléfono (avisos-E03, E04, E21)
 *
 *     { "token_fcm": "<token que da Firebase al teléfono>", "plataforma": "android" }
 *
 *     La app lo manda al iniciar sesión, si el usuario concedió el permiso de avisos, y cada vez que Firebase le
 *     renueva el token. La plataforma es "ios" o "android".
 *
 *     204  (sin cuerpo)
 *          El token queda ligado a la cuenta de la sesión, con su plataforma y su último uso. Si ya estaba, de esta
 *          cuenta o de otra, pasa a esta: los avisos de la otra cuenta ya no llegan a ese teléfono. La respuesta es la
 *          misma en los tres casos: no dice si el token era de alguien.
 *     422  { "message": "...", "errors": { "token_fcm": ["Falta el token del teléfono."] } }
 *          Falta el token o no es texto, pasa de 4096 caracteres, o la plataforma falta o no es ios ni android. No
 *          se guarda nada.
 *
 * El token se borra al cerrar la sesión (DELETE /api/sesion con token_fcm, en SesionController), al borrar la cuenta
 * y cuando FCM dice que ya no sirve (avisos-E05 y avisos-E06).
 */
class DispositivosController extends Controller
{
    public function registrar(RegistroDeTelefonoRequest $peticion, TelefonosRegistrados $telefonos): Response
    {
        /** @var Usuario $usuario */
        $usuario = $peticion->user();

        $telefonos->registrar($usuario, $peticion->validated('token_fcm'), $peticion->validated('plataforma'));

        return response()->noContent();
    }
}
