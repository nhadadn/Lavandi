<?php

namespace App\Pedidos;

use App\Models\EstadoPedido;

/**
 * Cómo nombra la administración cada estado del pedido: por su estado operativo, el que guarda el pedido (ADR-0021 y su
 * adenda), y no por el paso que ve el usuario. Así la administración distingue un pedido que espera la respuesta de la
 * lavandería (Confirmado) de uno aceptado, y Listo de En proceso de entrega. Los nombres son los del contrato: los cinco
 * del usuario (Módulo A #12), la respuesta de la lavandería (Módulo B #2) y su último botón (Módulo B #3).
 *
 * Vivía en el panel (App\Filament\EstadosDelPedido) y se movió aquí sin cambiar ningún nombre, para que el panel y la API
 * de la administración nombren igual (ADR-0022, decisión 9; openspec/changes/administracion-en-la-app/, tarea 1.7). La
 * llaman el monitoreo del panel, sus marcadores (MarcadoresDelMonitoreo) y la ficha del usuario (HistorialDelUsuario).
 *
 * Un estado que no está aquí se nombra como lo ve el usuario.
 */
final class NombresDelEstado
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
