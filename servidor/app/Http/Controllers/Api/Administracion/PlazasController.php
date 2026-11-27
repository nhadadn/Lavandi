<?php

namespace App\Http\Controllers\Api\Administracion;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use App\Repartidores\PlazasDelAlta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Las plazas en que la administración da de alta a un repartidor, para elegir una en la app (administracion-app-E20;
 * NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #2, "Alta"). Son las de App\Repartidores\PlazasDelAlta, las
 * mismas que ofrece el panel: las activas de Autorizacion::plazasDelPanel() (ADR-0022, decisión 9; repartidor-E05).
 *
 * Contrato para la app, con la sesión y los 401 y 403 de App\Http\Controllers\Api\Administracion\RepartidoresController:
 *
 *     GET /api/administracion/plazas
 *
 *     200  { "plazas": [ { "nombre": "Durango" } ] }
 *          Por nombre, en orden alfabético. El nombre es el que se manda en "plaza" a
 *          POST /api/administracion/repartidores/{id_publico}/alta: es único, y la plaza no tiene otro identificador en
 *          la API (ADR-0016, regla 5). Una plaza que no está activa no sale. Sin plazas, la lista llega vacía.
 */
class PlazasController extends Controller
{
    public function __invoke(Request $peticion, PlazasDelAlta $plazas): JsonResponse
    {
        /** @var Usuario $cuenta */
        $cuenta = $peticion->user();

        $nombres = $plazas->de($cuenta)->orderBy('nombre')->pluck('nombre');

        return response()->json(['plazas' => $nombres->map(fn (string $nombre): array => ['nombre' => $nombre])->values()]);
    }
}
