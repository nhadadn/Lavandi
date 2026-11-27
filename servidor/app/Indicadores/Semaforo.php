<?php

namespace App\Indicadores;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * El color del semáforo del ranking de lavanderías (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5, "Ranking con
 * semáforo"; indicadores-E13). Cada color va con su texto, no solo con el color: el panel muestra la etiqueta en una
 * insignia del color de estado de Filament.
 */
enum Semaforo: string implements HasColor, HasLabel
{
    case Verde = 'verde';
    case Amarillo = 'amarillo';
    case Rojo = 'rojo';

    public function getLabel(): string
    {
        return match ($this) {
            self::Verde => 'Verde',
            self::Amarillo => 'Amarillo',
            self::Rojo => 'Rojo',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Verde => 'success',
            self::Amarillo => 'warning',
            self::Rojo => 'danger',
        };
    }
}
