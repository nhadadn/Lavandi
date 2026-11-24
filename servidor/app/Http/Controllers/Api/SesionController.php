<?php

namespace App\Http\Controllers\Api;

use App\Autorizacion\Autorizacion;
use App\Avisos\TelefonosRegistrados;
use App\Http\Controllers\Controller;
use App\Http\Requests\CierreDeSesionRequest;
use App\Http\Requests\InicioDeSesionRequest;
use App\Http\Resources\UsuarioResource;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Timebox;
use Illuminate\Validation\ValidationException;

/**
 * La sesión de la app con correo y contraseña, con tokens de Sanctum (ADR-0005):
 *
 * - POST /api/sesion abre una sesión nueva: un token nuevo por cada inicio (registro-E07 y registro-E08). A una cuenta
 *   bloqueada desde el panel no se le abre: con la contraseña correcta, responde
 *   422 { "message": "Esta cuenta está bloqueada.", "errors": { "correo": ["Esta cuenta está bloqueada."] } }, sin
 *   token; con otra contraseña, la respuesta de siempre (administracion-E03);
 * - DELETE /api/sesion la cierra: revoca el token con el que llegó la petición, y solo ese (registro-E12).
 *
 * Al cerrar la sesión, la app manda también el token de Firebase del teléfono, para que la API lo borre y los avisos de
 * la cuenta dejen de llegarle (avisos-E05):
 *
 *     DELETE /api/sesion
 *
 *     { "token_fcm": "<token que da Firebase al teléfono>" }      opcional
 *
 *     204  (sin cuerpo)
 *          La sesión queda cerrada. Si el token es de un teléfono de la cuenta, se borra; si es de otra cuenta o no está
 *          registrado, no se toca nada y la respuesta es la misma. Sin el token, solo se cierra la sesión.
 *     422  { "message": "...", "errors": { "token_fcm": ["El token del teléfono va en texto."] } }
 *          El token no es texto o pasa de 4096 caracteres. La sesión sigue abierta.
 */
class SesionController extends Controller
{
    /** El nombre de los tokens de la app en personal_access_tokens. */
    public const NOMBRE_DEL_TOKEN = 'app';

    /** El mensaje a quien escribe bien su contraseña, pero tiene la cuenta bloqueada desde el panel. */
    public const CUENTA_BLOQUEADA = 'Esta cuenta está bloqueada.';

    public function iniciar(InicioDeSesionRequest $request, Timebox $timebox, Autorizacion $autorizacion): JsonResponse
    {
        $datos = $request->validated();

        // El Timebox iguala la duración de la respuesta exista o no la cuenta, como el guard de Laravel: el tiempo no
        // delata qué correos tienen cuenta.
        $usuario = $timebox->call(function (Timebox $timebox) use ($datos): ?Usuario {
            $usuario = Usuario::conCorreo($datos['correo'])->first();

            if ($usuario?->password === null || ! Hash::check($datos['password'], $usuario->password)) {
                return null;
            }

            $timebox->returnEarly();

            return $usuario;
        }, (int) config('auth.timebox_duration', 200_000));

        if ($usuario === null) {
            throw ValidationException::withMessages(['correo' => 'Las credenciales no son válidas.']);
        }

        // Solo con la contraseña correcta se dice que la cuenta está bloqueada (administracion-E03).
        if (! $autorizacion->puedeIniciarSesion($usuario)) {
            throw ValidationException::withMessages(['correo' => self::CUENTA_BLOQUEADA]);
        }

        // Solo los roles con módulo en la app reciben token; la administración entra por el panel (registro-E33).
        abort_unless(
            $autorizacion->usaLaApp($usuario->load('roles')),
            403,
            'Esta cuenta no tiene acceso a la app. Las cuentas de administración entran por el panel.',
        );

        return response()->json([
            'token' => $usuario->createToken(self::NOMBRE_DEL_TOKEN)->plainTextToken,
            'usuario' => new UsuarioResource($usuario->load('roles')),
        ]);
    }

    public function cerrar(CierreDeSesionRequest $request, TelefonosRegistrados $telefonos): Response
    {
        /** @var Usuario $usuario */
        $usuario = $request->user();
        $token = $request->validated('token_fcm');

        DB::transaction(function () use ($usuario, $token, $telefonos): void {
            if (is_string($token) && $token !== '') {
                $telefonos->olvidar($usuario, $token);
            }

            $usuario->currentAccessToken()->delete();
        });

        return response()->noContent();
    }
}
