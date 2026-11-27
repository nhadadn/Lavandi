<?php

namespace App\Finanzas;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Cómo queda cada pago y cada movimiento de la pasarela al conciliar un periodo (NSV-2026-001-v8.pdf:19:ANEXO A,
 * Sección A.3, Módulo D #6; administracion-E20 y E21): conciliado, o una de las tres diferencias que nombra la spec.
 * Cada uno va con su texto, no solo con su color.
 */
enum ResultadoDeConciliacion: string implements HasColor, HasLabel
{
    /** El pago de la plataforma y el movimiento de la pasarela cuadran: la misma orden y el mismo monto (E20). */
    case Conciliado = 'conciliado';

    /** La pasarela acreditó un pago que no cuadra con ningún pedido pagado en la plataforma (E21). */
    case MovimientoSinPedido = 'movimiento_sin_pedido';

    /** La plataforma tiene el pedido pagado y la pasarela no tiene su movimiento (E21). */
    case PedidoSinMovimiento = 'pedido_sin_movimiento';

    /** La misma orden, con un monto en la plataforma y otro en la pasarela (E21). */
    case MontoDistinto = 'monto_distinto';

    public function getLabel(): string
    {
        return match ($this) {
            self::Conciliado => 'Conciliado',
            self::MovimientoSinPedido => 'Movimiento sin pedido',
            self::PedidoSinMovimiento => 'Pedido pagado sin movimiento',
            self::MontoDistinto => 'Monto que no coincide',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Conciliado => 'success',
            self::MontoDistinto => 'warning',
            self::MovimientoSinPedido, self::PedidoSinMovimiento => 'danger',
        };
    }
}
