<?php

namespace App\Repartidores;

use App\Models\Repartidor;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * La cuenta tiene el rol de repartidor, pero su repartidor no opera: está pendiente de alta (repartidor-E03) o
 * suspendido (repartidor-E06). Lo decide App\Autorizacion\Autorizacion::repartidorQueOpera().
 *
 * Responde 403, como un rol que no corresponde (ADR-0019, decisión 2), con el estado del repartidor para que la app
 * muestre la pantalla de espera o la de suspensión:
 *
 *     403  { "message": "Tu registro espera la autorización de la administración.", "estado": "pendiente" }
 *     403  { "message": "Tu cuenta de repartidor está suspendida.", "estado": "suspendido" }
 *     403  { "message": "Esta cuenta no tiene acceso.", "estado": null }    sin repartidor, o dado de baja
 */
final class RepartidorSinAcceso extends RuntimeException
{
    private const MENSAJES = [
        Repartidor::PENDIENTE => 'Tu registro espera la autorización de la administración.',
        Repartidor::SUSPENDIDO => 'Tu cuenta de repartidor está suspendida.',
    ];

    private const SIN_ACCESO = 'Esta cuenta no tiene acceso.';

    public function __construct(public readonly ?string $estado)
    {
        parent::__construct(self::MENSAJES[$estado] ?? self::SIN_ACCESO);
    }

    public function report(): void
    {
        //
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'estado' => array_key_exists((string) $this->estado, self::MENSAJES) ? $this->estado : null,
        ], 403);
    }
}
