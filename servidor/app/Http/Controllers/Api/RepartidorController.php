<?php

namespace App\Http\Controllers\Api;

use App\Autorizacion\Autorizacion;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegistroDeRepartidorRequest;
use App\Http\Resources\RepartidorResource;
use App\Http\Resources\UsuarioResource;
use App\Identidad\TokensDeLaApp;
use App\Models\Usuario;
use App\Repartidores\RegistroDeRepartidor;
use App\Repartidores\RepartidorSinAcceso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * El registro del repartidor y su perfil, en la app del Módulo C (NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3, Módulo C
 * #1; openspec/changes/repartidor/). El contrato completo del Módulo C está en openspec/changes/repartidor/design.md,
 * "Contrato de la API".
 *
 * El repartidor, como sale en las dos respuestas:
 *
 *     {
 *       "id_publico": "01jd8x6m3v2c9q4w5e7r8t0y1z",   el ULID del repartidor
 *       "nombre": "Rubén Repartidor",
 *       "telefono": "618 123 4567",
 *       "correo": "ruben@ejemplo.com",
 *       "foto": { "url": "https://…", "vence_en": "2026-12-01T19:00:00Z" } | null,   URL firmada que vence (ADR-0006)
 *       "tipo_vehiculo": "Motocicleta",               texto libre
 *       "estado": "pendiente" | "activo" | "suspendido",
 *       "puede_operar": false,                        true solo si está activo y con su plaza: ve y mueve pedidos
 *       "plaza": null | { "nombre": "Durango" }       la del alta; null mientras está pendiente
 *     }
 *
 * ---------------------------------------------------------------------------------------------------------------------
 *
 *     POST /api/repartidor/registro                  El registro, sin sesión (repartidor-E01 y E02). En multipart: la
 *                                                    foto va como archivo
 *
 *     nombre          obligatorio, texto de hasta 255
 *     telefono        obligatorio: de 10 a 15 dígitos, con espacios, guiones, paréntesis y un + al frente
 *     correo          obligatorio: un correo sin cuenta, de hasta 255; se guarda en minúsculas
 *     password        obligatoria, con el mínimo de config/acceso.php
 *     foto            obligatoria: una imagen de los tipos y del peso de config/fotos.php, ya comprimida por la app
 *     tipo_vehiculo   obligatorio, texto libre de hasta 100
 *
 *     Cualquier otro campo, como un estado, una plaza o un rol, se ignora.
 *
 *     201  { "token": "<token de la sesión>", "usuario": { "id_publico": "…", "correo": "…", "rol": "repartidor" },
 *            "repartidor": { ...el repartidor, con "estado": "pendiente" y "puede_operar": false... } }
 *          La cuenta queda con el rol de repartidor y la sesión abierta. El repartidor queda pendiente, sin plaza, hasta
 *          que la administración lo dé de alta en el panel: la app muestra que espera la autorización de EL CLIENTE.
 *     422  { "message": "...", "errors": { "<campo>": ["..."] } }
 *          Falta uno de los datos, o no vale. No se crea ninguna cuenta ni se guarda la foto. Los mensajes:
 *            nombre          "Escribe tu nombre."
 *            telefono        "Escribe tu teléfono." · "El teléfono no es válido."
 *            correo          "Escribe tu correo." · "El correo no es válido." · "Ese correo ya tiene una cuenta."
 *            password        "Escribe una contraseña." · "La contraseña debe tener al menos 8 caracteres."
 *            foto            "Toma o elige tu foto de perfil." · "La foto debe ser una imagen: jpg, jpeg, png, webp." ·
 *                            "La foto pesa más de 5120 KB."
 *            tipo_vehiculo   "Escribe tu tipo de vehículo."
 *
 * ---------------------------------------------------------------------------------------------------------------------
 *
 *     GET /api/repartidor/perfil                     Su perfil y su estado, en cualquier estado (repartidor-E01 y E08)
 *
 *     Authorization: Bearer <token de la sesión>        (auth:sanctum y autorizar:repartidor)
 *
 *     200  { "repartidor": { ...el repartidor... } }
 *          La app lo consulta para saber si ya tiene alta: con "puede_operar" en true, abre sus pedidos. También muestra
 *          lo que la administración le editó en el panel (repartidor-E08).
 *     401  sin sesión · 403 { "message": "Esta cuenta no tiene acceso." } la cuenta no es de un repartidor
 */
class RepartidorController extends Controller
{
    public function registrar(
        RegistroDeRepartidorRequest $peticion,
        RegistroDeRepartidor $registro,
        TokensDeLaApp $tokens,
    ): JsonResponse {
        $repartidor = $registro->registrar(
            Arr::only($peticion->validated(), ['nombre', 'telefono', 'correo', 'password', 'tipo_vehiculo']),
            $peticion->foto(),
        );

        return response()->json([
            'token' => $tokens->crear($repartidor->cuenta)->plainTextToken,
            'usuario' => new UsuarioResource($repartidor->cuenta),
            'repartidor' => RepartidorResource::delRepartidor($repartidor),
        ], 201);
    }

    public function perfil(Request $peticion, Autorizacion $autorizacion): JsonResponse
    {
        /** @var Usuario $cuenta */
        $cuenta = $peticion->user();

        $repartidor = $autorizacion->repartidorDeLaCuenta($cuenta) ?? throw new RepartidorSinAcceso(null);

        return response()->json(['repartidor' => RepartidorResource::delRepartidor($repartidor)]);
    }
}
