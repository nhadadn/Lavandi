<?php

namespace App\Finanzas;

use Illuminate\Support\Number;

/**
 * Cómo se lee un monto en el panel: en pesos, con dos decimales, "$350.00", como en la ficha del usuario y en los
 * indicadores. El monto llega como texto con dos decimales, el que da la base; aquí solo se presenta.
 */
final class Pesos
{
    public static function texto(string $monto): string
    {
        return (string) Number::currency((float) $monto, in: 'MXN', locale: 'es_MX');
    }
}
