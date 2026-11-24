<?php

namespace App\Fotos;

/**
 * Lo que el servidor acepta de una foto que sube la app, escrito una sola vez: la de la lavandería (alta-lavanderias-E02)
 * y la de perfil de la cuenta (perfil-usuario-E04). Un archivo, una imagen de los tipos de config/fotos.php y de hasta
 * su peso máximo. La app la comprime antes de subirla (ADR-0006); el servidor solo revisa el tipo y el peso.
 */
final class ReglasDeLaFoto
{
    /**
     * Las reglas del campo de la foto, obligatoria.
     *
     * @return list<string>
     */
    public static function reglas(): array
    {
        return [
            'bail',
            'required',
            'file',
            'image',
            'mimes:'.implode(',', self::tipos()),
            'max:'.(int) config('fotos.peso_maximo_kb'),
        ];
    }

    /**
     * Las reglas del campo de la foto cuando es opcional, como la evidencia de entrega (repartidor-E23): sin foto, nada
     * que revisar; con foto, las mismas reglas.
     *
     * @return list<string>
     */
    public static function opcional(): array
    {
        return array_values(array_map(fn (string $regla): string => $regla === 'required' ? 'nullable' : $regla, self::reglas()));
    }

    /**
     * Las extensiones de imagen que se aceptan.
     *
     * @return list<string>
     */
    public static function tipos(): array
    {
        return array_values((array) config('fotos.tipos'));
    }
}
