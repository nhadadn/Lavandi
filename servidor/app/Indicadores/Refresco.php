<?php

namespace App\Indicadores;

/**
 * Cada cuánto se refrescan solos los indicadores con la página abierta (NSV-2026-001-v8.pdf:21:ANEXO A, Sección
 * A.4.4; indicadores-E21): el intervalo de config/indicadores.php, en el formato del sondeo de Livewire, como "30s" o
 * "1500ms".
 *
 * Cuánto es "en tiempo real" para los indicadores lo fija la adenda del 2026-11-22 de ADR-0007: 30 s, el valor por
 * omisión de la configuración (indicadores-E22). Con un intervalo que no sirve, los widgets no sondean y las cifras se
 * actualizan al abrir o recargar la página.
 */
final class Refresco
{
    /** Un número entero mayor que cero de segundos o de milisegundos. */
    private const FORMATO = '/^([1-9]\d*)(s|ms)$/';

    /**
     * El intervalo para wire:poll, o null si no hay uno que sirva.
     */
    public static function intervalo(): ?string
    {
        $valor = config('indicadores.refresco');

        return is_string($valor) && preg_match(self::FORMATO, $valor) === 1 ? $valor : null;
    }

    /**
     * Lo que dice la página sobre el refresco: "Las cifras se actualizan solas cada 30 segundos.".
     */
    public static function descripcion(): string
    {
        $intervalo = self::intervalo();

        if ($intervalo === null) {
            return 'Las cifras se actualizan al abrir o recargar la página.';
        }

        preg_match(self::FORMATO, $intervalo, $partes);
        $unidad = $partes[2] === 's' ? 'segundos' : 'milisegundos';

        return "Las cifras se actualizan solas cada {$partes[1]} {$unidad}.";
    }
}
