<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La función que protege el historial completo (NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3; ADR-0016, regla 6;
 * openspec/changes/modelo-de-datos/design.md, decisión 4). Corre justo después de PostGIS, antes de cualquier tabla
 * del negocio.
 *
 * Lanza un error con SQLSTATE 23001 (restrict_violation) que nombra la operación y la tabla. No crea ningún trigger:
 * cada cambio que crea pedidos, pedido_eventos, pagos o reembolsos le pone el suyo en la misma migración:
 *
 *     create trigger pedidos_impedir_borrado
 *         before delete on pedidos for each row execute function impedir_cambio();
 *     create trigger pedido_eventos_impedir_edicion
 *         before update on pedido_eventos for each row execute function impedir_cambio();
 *
 * TRUNCATE y DROP no disparan los triggers de fila, así que migrate:fresh y las pruebas pueden recrear la base.
 */
return new class extends Migration
{
    public function up(): void
    {
        // CREATE OR REPLACE, porque migrate:fresh borra las tablas pero no las funciones.
        DB::unprepared(<<<'SQL'
            create or replace function impedir_cambio() returns trigger
            language plpgsql
            as $$
            begin
                raise exception 'impedir_cambio: % sobre % no está permitido; la tabla guarda el historial completo',
                    tg_op, tg_table_name
                    using errcode = 'restrict_violation',
                          hint = 'Se corrige con una fila nueva, nunca editando ni borrando (ADR-0016, regla 6).';
            end;
            $$;
        SQL);
    }

    /**
     * Solo hacia adelante (ADR-0016, regla 9): la función no se quita al revertir, porque los triggers del historial
     * dependen de ella.
     */
    public function down(): void
    {
        //
    }
};
