<?php

namespace App\Lavanderias;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Una ventana de disponibilidad dura exactamente una hora: su fin es su inicio más 60 minutos
 * (NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #7, "ventanas de 1 hora"; alta-lavanderias-E22). La base solo
 * exige que el fin sea después del inicio; la hora exacta la valida el código (design.md, "Horario y ventanas en una
 * sola tabla").
 *
 * Va sobre cada ventana (ventanas.N), para que el error quede en la ventana y no en una de sus horas. Si una hora no
 * tiene el formato HH:MM, lo dice su propia regla y aquí no se revisa.
 */
final class VentanaDeUnaHora implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            return;
        }

        $inicio = self::minutos($value['hora_inicio'] ?? null, ReglasDeLaLavanderia::HORA);
        $fin = self::minutos($value['hora_fin'] ?? null, ReglasDeLaLavanderia::HORA_DE_FIN);

        if ($inicio !== null && $fin !== null && $fin - $inicio !== 60) {
            $fail('Cada ventana dura exactamente una hora.');
        }
    }

    /**
     * Los minutos desde la medianoche de una hora HH:MM, o null si no tiene ese formato.
     */
    private static function minutos(mixed $hora, string $formato): ?int
    {
        if (! is_string($hora) || preg_match($formato, $hora) !== 1) {
            return null;
        }

        [$horas, $minutos] = array_map('intval', explode(':', $hora));

        return $horas * 60 + $minutos;
    }
}
