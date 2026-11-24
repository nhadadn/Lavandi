<?php

namespace App\Avisos\Fcm;

use RuntimeException;

/**
 * Firebase Cloud Messaging respondió que el token del teléfono ya no sirve: la app se desinstaló, el token venció o es
 * de otro proyecto. Quien manda el aviso borra el token (avisos-E06).
 */
final class TokenRechazado extends RuntimeException
{
    public function __construct(public readonly string $codigo)
    {
        parent::__construct("FCM rechazó el token del teléfono: {$codigo}.");
    }
}
