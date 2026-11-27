<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UsuarioResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/cuenta: la propia cuenta, la del token de la petición, y ninguna otra (registro-E10).
 */
class CuentaController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'usuario' => new UsuarioResource($request->user()->load('roles')),
        ]);
    }
}
