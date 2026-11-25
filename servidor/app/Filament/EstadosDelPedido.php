<?php

namespace App\Filament;

use App\Models\EstadoPedido;

/**
 * Cómo nombra el panel de administración cada estado del pedido: por su estado operativo, el que guarda el pedido
 * (ADR-0021 y su adenda), y no por el paso que ve el usuario. Así la administración distingue un pedido que espera la
 * respuesta de la lavandería (Confirmado) de uno aceptado, y Listo de En proceso de entrega. Los nombres son los del
 * contrato: los cinco del usuario (Módulo A #12), la respuesta de la lavandería (Módulo B #2) y su último botón (Módulo
 * B #3).
 *
 * Un estado que no está aquí se nombra como lo ve el usuario.
 */
final class EstadosDelPedido
{
    /** @var array<string, string> */
    public const NOMBRES = [
        EstadoPedido::POR_PAGAR => 'Por pagar',
        EstadoPedido::CONFIRMADO => 'Confirmado',
        EstadoPedido::ACEPTADO => 'Aceptado',
        EstadoPedido::RECHAZADO => 'Rechazado',
        EstadoPedido::RECOLECTADO => 'Recolectado',
        EstadoPedido::EN_PROCESO => 'En proceso',
        EstadoPedido::LISTO => 'Listo',
        EstadoPedido::EN_ENTREGA => 'En proceso de entrega',
        EstadoPedido::ENTREGADO => 'Entregado',
    ];

    public static function nombre(EstadoPedido $estado): string
    {
        return self::NOMBRES[$estado->codigo] ?? $estado->nombre_usuario;
    }
}
