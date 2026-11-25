<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EnlaceDeRecuperacionRequest;
use App\Identidad\RecuperacionDeContrasena;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/contrasena/olvido: pide el enlace para crear una contraseña nueva (registro-E22 y registro-E23).
 *
 * Responde siempre lo mismo, exista o no la cuenta (ADR-0018, decisión 5). El límite de peticiones lo pone el
 * limitador recuperacion, antes de llegar aquí (registro-E31).
 */
class RecuperacionController extends Controller
{
    public const MENSAJE = 'Si el correo tiene una cuenta, te mandamos un enlace para crear una contraseña nueva. '
        .'Revisa tu correo.';

    public function __invoke(EnlaceDeRecuperacionRequest $request, RecuperacionDeContrasena $recuperacion): JsonResponse
    {
        $recuperacion->mandarEnlace($request->validated('correo'));

        return response()->json(['message' => self::MENSAJE]);
    }
}
