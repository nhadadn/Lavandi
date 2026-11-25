<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Una vigencia termina después de empezar: parametros_plaza rechaza un fin de vigencia anterior a su inicio o en el
 * mismo instante. Sin fin de vigencia, el parámetro sigue vigente (plazas-E15; DD-134, punto 5, C-234).
 *
 * Que dos vigencias de la misma clave no se traslapen no lo revisa este CHECK: espera al primer cambio que agregue un
 * parámetro (openspec/changes/plazas-y-zonas/specs/plazas/spec.md, plazas-E15).
 *
 * Va en una migración aparte porque la que crea la tabla ya corrió: las migraciones solo van hacia adelante
 * (ADR-0016, regla 9).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'alter table parametros_plaza add constraint parametros_plaza_vigencia_check
                check (vigente_hasta is null or vigente_hasta > vigente_desde)',
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
