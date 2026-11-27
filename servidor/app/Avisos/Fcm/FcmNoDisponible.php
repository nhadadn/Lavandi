<?php

namespace App\Avisos\Fcm;

use RuntimeException;
use Throwable;

/**
 * El aviso no salió porque Firebase Cloud Messaging no respondió, respondió con un error o no está configurado. El
 * pedido no depende de él (avisos-E02; ADR-0008): quien manda el aviso lo deja en la bitácora y, si es reintentable,
 * lo vuelve a intentar más tarde.
 */
final class FcmNoDisponible extends RuntimeException
{
    private function __construct(string $motivo, public readonly bool $reintentable, ?Throwable $previa = null)
    {
        parent::__construct("FCM no disponible: {$motivo}.", previous: $previa);
    }

    /**
     * Una falla de la red o de Google que puede pasar sola: sin conexión, un 5xx, un 429 o un token de acceso vencido.
     */
    public static function porque(string $motivo, ?Throwable $previa = null): self
    {
        return new self($motivo, reintentable: true, previa: $previa);
    }

    /**
     * Una falla que no se arregla reintentando: falta la cuenta de servicio, no se puede leer, o FCM rechazó el
     * mensaje mismo.
     */
    public static function sinRemedio(string $motivo, ?Throwable $previa = null): self
    {
        return new self($motivo, reintentable: false, previa: $previa);
    }
}
