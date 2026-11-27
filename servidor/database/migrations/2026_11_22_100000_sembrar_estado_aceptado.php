<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El estado en que queda el pedido cuando la lavandería lo acepta: aceptado ("Aceptar o rechazar con motivo",
 * NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo B #2; pedidos-lavanderia-E07).
 *
 * El usuario lo sigue viendo como Confirmado: lleva el nombre y el orden de Confirmado, y no es uno de los cinco pasos
 * del seguimiento (visible_usuario falso). La API del usuario le presenta el paso de su mismo orden (adenda propuesta a
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
            'codigo' => 'aceptado',
            'nombre_usuario' => 'Confirmado',
            'orden' => DB::table('estados_pedido')->where('codigo', 'confirmado')->value('orden'),
            'visible_usuario' => false,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);
    }

    public function down(): void
    {
        // Con pedidos en aceptado, la llave foránea (ON DELETE RESTRICT) impide borrarlo.
        DB::table('estados_pedido')->where('codigo', 'aceptado')->delete();
    }
};
