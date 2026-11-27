<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Completa calificaciones con la llave compuesta (id_pedidos, id_plazas) hacia pedidos, con su índice y ON DELETE
 * RESTRICT (openspec/changes/historial-y-calificacion/design.md, "Tablas y migración"; vault/arquitectura/modelo-de-datos.md;
 * ADR-0016). calificaciones nació antes que pedidos, con id_pedidos opcional y sin llave (ADR-0016, regla 4): desde aquí
 * la base rechaza una calificación de un pedido que no existe o de otra plaza que la de su pedido (modelo-E06).
 *
 * Lo demás que pide el diseño ya lo trajo perfil-de-lavanderia y no se repite (design.md, Risks): id_lavanderias
 * opcional, el CHECK de exactamente un calificado y los únicos por pedido y calificado (historial-E13). La llave hacia
 * repartidores la agregó repartidor.
 *
 * id_pedidos sigue opcional: las calificaciones de prueba del perfil de la lavandería no tienen pedido, y con la columna
 * vacía la llave no se revisa. Sin el trigger impedir_cambio(), que es solo de pedidos, pedido_eventos, pagos y
 * reembolsos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calificaciones', function (Blueprint $tabla) {
            $tabla->index(['id_pedidos', 'id_plazas']);
            $tabla->foreign(['id_pedidos', 'id_plazas'])
                ->references(['id_pedidos', 'id_plazas'])
                ->on('pedidos')
                ->restrictOnDelete();
        });
    }

    /**
     * Solo hacia adelante (ADR-0016, regla 9).
     */
    public function down(): void
    {
        //
    }
};
