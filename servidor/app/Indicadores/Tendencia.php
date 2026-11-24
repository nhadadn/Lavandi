<?php

namespace App\Indicadores;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Hacia dónde fue un indicador frente al periodo anterior de la misma duración (NSV-2026-001-v8.pdf:22:ANEXO A,
 * Sección A.5, "Indicador destacado con tendencia"; openspec/changes/indicadores/design.md, lectura 4;
 * indicadores-E09). La tendencia va siempre con su texto, no solo con el color.
 */
enum Tendencia: string implements HasColor, HasLabel
{
    case Subio = 'subio';
    case Bajo = 'bajo';
    case Igual = 'igual';

    /**
     * La tendencia de una diferencia contra el periodo anterior, en centavos.
     */
    public static function deLaDiferencia(int $centavos): self
    {
        return match (true) {
            $centavos > 0 => self::Subio,
            $centavos < 0 => self::Bajo,
            default => self::Igual,
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Subio => 'Subió',
            self::Bajo => 'Bajó',
            self::Igual => 'Igual',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Subio => 'success',
            self::Bajo => 'danger',
            self::Igual => 'gray',
        };
    }
}
