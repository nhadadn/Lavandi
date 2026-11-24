<?php

namespace App\Identidad;

use RuntimeException;

/**
 * La entrada con Google no abre sesión. El mensaje es el que ve la persona en la app.
 */
final class EntradaConGoogleRechazada extends RuntimeException
{
    /**
     * El idToken no pasó la verificación (registro-E17), su correo no está verificado por Google, o su cuenta está
     * borrada. No se dice cuál, ni si el correo ya tiene una cuenta (C-228).
     */
    public static function noValida(): self
    {
        return new self('No se pudo entrar con tu cuenta de Google.');
    }

    /**
     * La cuenta está bloqueada desde el panel (administracion-E03). Se dice solo después de verificar el idToken: quien
     * lo manda ya probó que el correo es suyo.
     */
    public static function bloqueada(): self
    {
        return new self('Esta cuenta está bloqueada.');
    }
}
