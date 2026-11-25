<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El estado en que queda el pedido cuando la lavandería lo rechaza con su motivo: rechazado ("Aceptar o rechazar con
 * motivo", NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo B #2; pedidos-lavanderia-E12). ADR-0021, decisión 3, lo
 * asigna a este cambio.
 *
 * No es ninguno de los cinco que ve el usuario: es la banda de excepción (ADR-0021; C-201). Va con el orden 99, que no
 * comparte ningún paso del seguimiento, así que el pedido rechazado queda fuera del flujo (adenda propuesta a ADR-0021,
 * en openspec/changes/pedidos-de-la-lavanderia/design.md). Qué ve el usuario de él espera DD-103 (estados-E19).
 *
 * Solo datos del catálogo: no cambia ninguna tabla.
 */
return new class extends Migration
{
    public function up(): void
    {
        $ahora = now();

        DB::table('estados_pedido')->insert([
            'codigo' => 'rechazado',
            'nombre_usuario' => 'Rechazado',
            'orden' => 99,
            'visible_usuario' => false,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);
    }

    public function down(): void
    {
        // Con pedidos en rechazado, la llave foránea (ON DELETE RESTRICT) impide borrarlo.
        DB::table('estados_pedido')->where('codigo', 'rechazado')->delete();
    }
};
