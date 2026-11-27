<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Soporte\NumeroDeSoporte;
use Illuminate\Http\JsonResponse;

/**
 * Soporte de la app: el chat de WhatsApp Business (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo A #16;
 * openspec/changes/perfil-y-soporte/, tarea 1.3).
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
 *     GET /api/soporte                        El número de WhatsApp de soporte (perfil-usuario-E09 y E10)
 *
 *     200  { "whatsapp": "5210000000001" }
 *          El número de la configuración del servidor, solo con sus dígitos, en formato internacional (país y número).
 *          La app abre el chat con https://wa.me/<whatsapp> (perfil-usuario-E09).
 *     200  { "whatsapp": null }
 *          No hay número configurado (DD-082). La app muestra la opción de WhatsApp como no disponible y no abre un
 *          enlace vacío (perfil-usuario-E10).
 */
class SoporteController extends Controller
{
    public function __invoke(NumeroDeSoporte $numero): JsonResponse
    {
        return response()->json(['whatsapp' => $numero->whatsapp()]);
    }
}
