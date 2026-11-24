<?php

namespace App\Acceso\Google;

/**
 * Quién es la persona según un idToken de Google ya verificado. Su correo siempre es uno que Google da por verificado:
 * el verificador rechaza los demás (registro-E17; C-228).
 */
final readonly class IdentidadDeGoogle
{
    /**
     * @param  string  $sujeto  El identificador de la cuenta de Google (reclamo sub). No cambia aunque cambie el correo.
     * @param  string  $correo  El correo de la cuenta de Google (reclamo email), verificado por Google.
     */
    public function __construct(
        public string $sujeto,
        public string $correo,
    ) {}
}
