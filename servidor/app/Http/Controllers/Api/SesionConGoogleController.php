<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SesionConGoogleRequest;
use App\Http\Resources\UsuarioResource;
use App\Identidad\EntradaConGoogle;
use App\Identidad\EntradaConGoogleRechazada;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * POST /api/sesion/google: abre la sesión de quien entra con Google, con un token de Sanctum nuevo, con la misma
 * respuesta que el inicio de sesión con correo y contraseña (registro-E16 a registro-E18; ADR-0005).
 *
 * Un rechazo sale como error de validación del campo id_token, sin token.
 */
class SesionConGoogleController extends Controller
{
    public function __invoke(SesionConGoogleRequest $request, EntradaConGoogle $entrada): JsonResponse
    {
        try {
            $usuario = $entrada->entrar($request->validated('id_token'));
        } catch (EntradaConGoogleRechazada $rechazo) {
            throw ValidationException::withMessages(['id_token' => $rechazo->getMessage()]);
        }

        return response()->json([
            'token' => $usuario->createToken(SesionController::NOMBRE_DEL_TOKEN)->plainTextToken,
            'usuario' => new UsuarioResource($usuario),
        ]);
    }
}
