<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El estado del botón "En proceso de entrega" de la lavandería (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo B
 * #3), que el repartidor marcará como "En tránsito" (Módulo C #3): en_entrega (ADR-0021, decisiones 2 y 3;
 * estados-E04).
 *
 * El usuario lo sigue viendo como Listo: lleva el nombre y el orden de Listo y no es uno de los cinco pasos del
 * seguimiento (visible_usuario falso). La API del usuario le presenta el paso de su mismo orden (adenda propuesta a
 * ADR-0021, en openspec/changes/pedidos-de-la-lavanderia/design.md).
 *
 * Solo datos del catálogo: no cambia ninguna tabla.
 */
return new class extends Migration
{
    public function up(): void
    {
        $ahora = now();

        DB::table('estados_pedido')->insert([
            'codigo' => 'en_entrega',
            'nombre_usuario' => 'Listo',
            'orden' => DB::table('estados_pedido')->where('codigo', 'listo')->value('orden'),
            'visible_usuario' => false,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);
    }

    public function down(): void
    {
        // Con pedidos en en_entrega, la llave foránea (ON DELETE RESTRICT) impide borrarlo.
        DB::table('estados_pedido')->where('codigo', 'en_entrega')->delete();
    }
};
