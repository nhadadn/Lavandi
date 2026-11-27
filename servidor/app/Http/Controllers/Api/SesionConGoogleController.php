<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SesionConGoogleRequest;
use App\Http\Resources\UsuarioResource;
use App\Identidad\EntradaConGoogle;
use App\Identidad\EntradaConGoogleRechazada;
use App\Identidad\TokensDeLaApp;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * POST /api/sesion/google: abre la sesión de quien entra con Google, con un token de Sanctum nuevo de
 * App\Identidad\TokensDeLaApp, con la misma respuesta que el inicio de sesión con correo y contraseña (registro-E16 a
 * registro-E18; ADR-0005).
 *
 * Un rechazo sale como error de validación del campo id_token, sin token. Así sale también el de una cuenta de
 * administración, que entra con su correo y su contraseña (administracion-app-E08).
 */
class SesionConGoogleController extends Controller
{
    public function __invoke(
        SesionConGoogleRequest $request,
        EntradaConGoogle $entrada,
        TokensDeLaApp $tokens,
    ): JsonResponse {
        try {
            $usuario = $entrada->entrar($request->validated('id_token'));
        } catch (EntradaConGoogleRechazada $rechazo) {
            throw ValidationException::withMessages(['id_token' => $rechazo->getMessage()]);
        }

        return response()->json([
            'token' => $tokens->crear($usuario)->plainTextToken,
            'usuario' => new UsuarioResource($usuario),
        ]);
    }
}
