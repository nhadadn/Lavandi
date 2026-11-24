<?php

namespace App\Acceso\Google;

use RuntimeException;
use Throwable;

/**
 * El idToken de Google no se acepta: está vencido, su firma no es de Google, se emitió para otro cliente, le falta un
 * reclamo, Google no da su correo por verificado (C-228) o no se pudo comprobar (registro-E17). El mensaje dice el
 * motivo, para la bitácora; a la app no se le da.
 */
final class IdTokenDeGoogleNoValido extends RuntimeException
{
    public static function porque(string $motivo, ?Throwable $causa = null): self
    {
        return new self("El idToken de Google no es válido: {$motivo}.", 0, $causa);
    }
}
