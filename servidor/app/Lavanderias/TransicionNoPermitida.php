<?php

namespace App\Lavanderias;

use LogicException;

/**
 * Un cambio de estado de la lavandería que no se puede hacer desde el que tiene: suspender una de baja, o levantar la
 * suspensión de una que no está suspendida (App\Lavanderias\EstadoDeLaLavanderia). El panel no ofrece el botón; si
 * llega, es un error del código.
 */
final class TransicionNoPermitida extends LogicException
{
    public static function de(string $estado, string $accion): self
    {
        return new self("Una lavandería en estado {$estado} no se puede {$accion}.");
    }
}
