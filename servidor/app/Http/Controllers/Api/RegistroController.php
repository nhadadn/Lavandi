<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegistroRequest;
use App\Http\Resources\UsuarioResource;
use App\Identidad\AltaDeCuenta;
use App\Identidad\TokensDeLaApp;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/registro: crea la cuenta con el rol de usuario y abre su sesión, con el token de App\Identidad\TokensDeLaApp
 * (registro-E01 a registro-E03).
 */
class RegistroController extends Controller
{
    public function __invoke(RegistroRequest $request, AltaDeCuenta $alta, TokensDeLaApp $tokens): JsonResponse
    {
        $datos = $request->validated();

        $usuario = $alta->deUsuario($datos['correo'], $datos['password']);

        return response()->json([
            'token' => $tokens->crear($usuario)->plainTextToken,
            'usuario' => new UsuarioResource($usuario),
        ], 201);
    }
}
