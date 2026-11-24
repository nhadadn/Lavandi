<?php

namespace App\Cobro;

/**
 * Quita del contenido de un aviso cualquier dato de la tarjeta que no sea su marca y sus últimos cuatro dígitos, antes de
 * guardarlo (NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3: "Los números de tarjeta nunca se guardan en la plataforma";
 * modelo-E13; openspec/changes/pago/design.md, "Risks / Trade-offs").
 *
 * Es una red para cualquier pasarela: quita, a cualquier profundidad, las llaves que nombran el número, el vencimiento o
 * el código de seguridad, en español y en inglés. Qué trae de verdad cada aviso de Conekta se confirma con las cargas de
 * su sandbox (tarea 3.2), y su implementación puede quitar más.
 */
final class SinDatosDeTarjeta
{
    /** Las llaves que se quitan, comparadas en minúsculas. */
    private const LLAVES = [
        // El número.
        'number', 'numero', 'card_number', 'numero_tarjeta', 'numero_de_tarjeta', 'pan',
        // El código de seguridad.
        'cvc', 'cvv', 'cvv2', 'cvc2', 'csc', 'security_code', 'codigo_seguridad', 'codigo_de_seguridad',
        // El vencimiento.
        'exp', 'exp_month', 'exp_year', 'expiration', 'expiration_date', 'expiry', 'expiracion', 'vencimiento',
        'vencimiento_tarjeta', 'mes_vencimiento', 'anio_vencimiento',
    ];

    /**
     * @param  array<array-key, mixed>  $contenido
     * @return array<array-key, mixed>
     */
    public static function limpiar(array $contenido): array
    {
        $limpio = [];

        foreach ($contenido as $llave => $valor) {
            if (is_string($llave) && in_array(strtolower($llave), self::LLAVES, true)) {
                continue;
            }

            $limpio[$llave] = is_array($valor) ? self::limpiar($valor) : $valor;
        }

        return $limpio;
    }
}
