<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El reporte de Soporte se guarda aunque la cuenta no tenga de dónde tomar su plaza (NSV-2026-001-v8.pdf:17:ANEXO A,
 * Sección A.3, Módulo A #16, "formulario de reporte", sin condiciones; perfil-usuario-E17).
 *
 * - reportes_soporte.id_plazas deja de ser obligatoria. Vacía, el reporte no es de ninguna plaza: la cuenta no tiene
 *   pedidos ni direcciones guardadas con plaza. Con valor, sigue como antes: la de su pedido, la del último pedido de la
 *   cuenta o la de su dirección guardada más reciente (App\Soporte\RecepcionDeReporte).
 * - La tabla ya estaba en la lista plaza_opcional de config/modelo.php (plazas-E09). La llave hacia plazas y la
 *   compuesta (id_pedidos, id_plazas) hacia pedidos no cambian: un reporte con pedido siempre lleva la plaza de su pedido,
 *   y el CHECK reportes_soporte_pedido_con_plaza_check no deja un pedido sin plaza, que se saltaría la compuesta
 *   (MATCH SIMPLE).
 * - Los reportes que ya existen tienen plaza: no se convierte nada.
 *
 * No edita la migración que creó la tabla: alguien ya pudo correrla (ADR-0016, regla 9).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('alter table reportes_soporte alter column id_plazas drop not null');
        DB::statement(
            'alter table reportes_soporte add constraint reportes_soporte_pedido_con_plaza_check
                check (id_pedidos is null or id_plazas is not null)',
        );
    }

    /**
     * Solo hacia adelante (ADR-0016, regla 9).
     */
    public function down(): void
    {
        //
    }
};
