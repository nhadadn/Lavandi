<?php

namespace App\Landing;

/**
 * Los botones de App Store y de Google Play de "Descarga la app" (NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2,
 * sección 4), con el enlace de cada tienda tomado de config/landing.php.
 *
 * Solo se publica un enlace que sea una dirección http o https completa: uno vacío, relativo o de otro esquema se trata
 * como si no estuviera, y el botón se muestra sin enlace, con el texto que se configure (landing-E09). Así ningún botón
 * lleva a una dirección vacía o rota.
 */
final class BotonesDeDescarga
{
    /** Las dos tiendas, en el orden del A.2, con el nombre que se muestra. */
    private const TIENDAS = [
        'app_store' => 'App Store',
        'google_play' => 'Google Play',
    ];

    /**
     * @return list<array{clave: string, nombre: string, enlace: ?string, texto_sin_enlace: ?string}>
     */
    public function botones(): array
    {
        $botones = [];

        foreach (self::TIENDAS as $clave => $nombre) {
            $texto = config("landing.tiendas.{$clave}.texto_sin_enlace");

            $botones[] = [
                'clave' => $clave,
                'nombre' => $nombre,
                'enlace' => self::enlaceValido(config("landing.tiendas.{$clave}.enlace")),
                'texto_sin_enlace' => is_string($texto) && trim($texto) !== '' ? trim($texto) : null,
            ];
        }

        return $botones;
    }

    /**
     * El enlace, si es una dirección http o https con su servidor; si no, ninguno.
     */
    private static function enlaceValido(mixed $enlace): ?string
    {
        if (! is_string($enlace)) {
            return null;
        }

        $enlace = trim($enlace);
        $esquema = strtolower((string) parse_url($enlace, PHP_URL_SCHEME));

        if (filter_var($enlace, FILTER_VALIDATE_URL) === false || ! in_array($esquema, ['http', 'https'], true)) {
            return null;
        }

        return parse_url($enlace, PHP_URL_HOST) ? $enlace : null;
    }
}
