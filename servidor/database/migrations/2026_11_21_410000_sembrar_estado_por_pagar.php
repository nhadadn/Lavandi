<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El estado en que nace el pedido al aceptar el resumen y en que espera su pago: por_pagar, que el usuario no ve
 * (ADR-0021, decisión 2; C-188). Va antes de Confirmado, con orden 0. Es el que nombra config/pedidos.php.
 *
 * Los demás estados internos (en_entrega, rechazado y cancelado) los siembra el cambio que los usa (ADR-0021,
 * decisión 3).
 */
return new class extends Migration
{
    public function up(): void
    {
        $ahora = now();

        DB::table('estados_pedido')->insert([
            'codigo' => 'por_pagar',
            'nombre_usuario' => 'Por pagar',
            'orden' => 0,
            'visible_usuario' => false,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);
    }

    public function down(): void
    {
        // Con pedidos en por_pagar, la llave foránea (ON DELETE RESTRICT) impide borrarlo.
        DB::table('estados_pedido')->where('codigo', 'por_pagar')->delete();
    }
};
