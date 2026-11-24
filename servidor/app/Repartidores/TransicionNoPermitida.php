<?php

namespace App\Repartidores;

use LogicException;

/**
 * Un cambio de estado del repartidor que no se puede hacer desde el que tiene: dar de alta a uno que no está pendiente,
 * suspender a uno que no está activo, o dar de baja a uno pendiente (App\Repartidores\EstadoDelRepartidor). El panel no
 * ofrece el botón; si llega, es un error del código.
 */
final class TransicionNoPermitida extends LogicException
{
    public static function de(string $estado, string $accion): self
    {
        return new self("Un repartidor en estado {$estado} no se puede {$accion}.");
    }
}
