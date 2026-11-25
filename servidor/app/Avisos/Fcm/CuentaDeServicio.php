<?php

namespace App\Avisos\Fcm;

use JsonException;

/**
 * La cuenta de servicio del proyecto de Firebase del entorno, leída del archivo JSON que descarga la consola de
 * Firebase (ADR-0012). Su ruta sale de avisos.fcm.credenciales (FCM_CREDENCIALES en el .env) y el archivo vive fuera
 * del repositorio: ninguna llave se versiona.
 */
final class CuentaDeServicio
{
    /** El punto de tokens de Google, si el archivo no trae el suyo. */
    private const DIRECCION_DE_TOKENS_POR_OMISION = 'https://oauth2.googleapis.com/token';

    private function __construct(
        public readonly string $proyecto,
        public readonly string $correo,
        public readonly string $llavePrivada,
        public readonly ?string $idDeLlave,
        public readonly string $direccionDeTokens,
    ) {}

    /**
     * @throws FcmNoDisponible si la ruta está vacía o el archivo no se puede leer
     */
    public static function desdeLaConfiguracion(): self
    {
        $ruta = config('avisos.fcm.credenciales');

        if (! is_string($ruta) || trim($ruta) === '') {
            throw FcmNoDisponible::sinRemedio('no hay cuenta de servicio configurada (FCM_CREDENCIALES)');
        }

        $contenido = is_file($ruta) && is_readable($ruta) ? file_get_contents($ruta) : false;

        if ($contenido === false) {
            throw FcmNoDisponible::sinRemedio('no se puede leer el archivo de la cuenta de servicio');
        }

        try {
            $datos = json_decode($contenido, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw FcmNoDisponible::sinRemedio('el archivo de la cuenta de servicio no es JSON', $error);
        }

        foreach (['project_id', 'client_email', 'private_key'] as $campo) {
            if (! is_string($datos[$campo] ?? null) || $datos[$campo] === '') {
                throw FcmNoDisponible::sinRemedio("a la cuenta de servicio le falta {$campo}");
            }
        }

        return new self(
            proyecto: $datos['project_id'],
            correo: $datos['client_email'],
            llavePrivada: $datos['private_key'],
            idDeLlave: is_string($datos['private_key_id'] ?? null) ? $datos['private_key_id'] : null,
            direccionDeTokens: is_string($datos['token_uri'] ?? null) ? $datos['token_uri'] : self::DIRECCION_DE_TOKENS_POR_OMISION,
        );
    }
}
