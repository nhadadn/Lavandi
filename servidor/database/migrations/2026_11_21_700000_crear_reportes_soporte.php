<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La tabla reportes_soporte: el formulario de reporte de Soporte, ligado a la cuenta y, si ella quiere, a uno de sus
 * pedidos (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo A #16; openspec/changes/perfil-y-soporte/design.md,
 * "Tablas y migración"; vault/arquitectura/modelo-de-datos.md; ADR-0016).
 *
 * - La plaza es no nula (plazas-E09): con pedido, la del pedido; sin pedido, la del último pedido de la cuenta o la de
 *   su dirección guardada más reciente (decisión 27 de acta-2026-11-20-modelo-y-pasarela). La resuelve
 *   App\Soporte\RecepcionDeReporte.
 * - La llave compuesta (id_pedidos, id_plazas) hacia pedidos hace que la base rechace un reporte con otra plaza que la
 *   de su pedido (modelo-E06). Sin pedido, PostgreSQL no la revisa (MATCH SIMPLE); la de id_plazas, sí.
 * - La descripción del formulario se guarda en mensaje. La fecha del reporte es created_at (perfil-usuario-E11).
 * - El estado nace en recibido, con un CHECK de un solo valor: los demás dependen de cómo se atiende el reporte y entran
 *   con DD-108, en una migración hacia adelante (ADR-0016, regla 9).
 * - Sin únicos: una persona puede mandar varios reportes. Sin el trigger impedir_cambio(), que es del historial del
 *   pedido y del dinero.
 *
 * Toda llave foránea lleva su índice y ON DELETE RESTRICT (modelo-E09 y modelo-E10).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reportes_soporte', function (Blueprint $tabla) {
            $tabla->id('id_reportes_soporte');
            // Quien manda el reporte.
            $tabla->foreignId('id_usuarios')->index()->constrained('usuarios', 'id_usuarios')->restrictOnDelete();
            // Opcional: el pedido va "si él quiere" (perfil-usuario-E11).
            $tabla->unsignedBigInteger('id_pedidos')->nullable();
            $tabla->foreignId('id_plazas')->index()->constrained('plazas', 'id_plazas')->restrictOnDelete();
            $tabla->text('mensaje');
            $tabla->string('estado')->default('recibido');
            // Vacía hasta que alguien atienda el reporte (DD-108).
            $tabla->timestampTz('atendido_en')->nullable();
            $tabla->timestampsTz();
            $tabla->index(['id_pedidos', 'id_plazas']);
            $tabla->foreign(['id_pedidos', 'id_plazas'])
                ->references(['id_pedidos', 'id_plazas'])
                ->on('pedidos')
                ->restrictOnDelete();
        });

        DB::statement("alter table reportes_soporte add constraint reportes_soporte_estado_check check (estado in ('recibido'))");
    }

    /**
     * Solo hacia adelante (ADR-0016, regla 9).
     */
    public function down(): void
    {
        //
    }
};
