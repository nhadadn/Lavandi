<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La comisión global es una sola para todo el sistema, no una por plaza (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3,
 * Módulo D #5, "Porcentaje de comisión por socio o global"; openspec/changes/administracion/design.md, Open Questions 4,
 * resuelta por la decisión 39 del acta, C-236; administracion-E11).
 *
 * - comisiones.id_plazas deja de ser obligatoria: vacía, junto con id_lavanderias, es la comisión global del sistema;
 *   con las dos, la propia de esa lavandería, que sigue con su llave compuesta hacia lavanderias (modelo-E06). El CHECK
 *   comisiones_global_o_propia_check no deja otra combinación: sin él, una propia sin plaza se saltaría la llave
 *   compuesta (MATCH SIMPLE). Por eso la tabla pasa a la lista plaza_opcional de config/modelo.php.
 * - A lo más una global abierta en todo el sistema: comisiones_global_abierta_unique se rehace sin la plaza.
 * - Lo que haya se convierte, sin inventar ningún porcentaje: las globales de cada plaza pasan a ser la global del
 *   sistema, con su historial. De las que seguían abiertas queda abierta la que se fijó al último (la de vigente_desde
 *   más reciente; a la par, la de llave mayor); las demás se cierran en el instante en que empezó esa, y una que empezó
 *   en ese mismo instante se borra, porque nunca rigió sola (la regla de App\Comisiones\PorcentajesDeComision). Las
 *   propias no cambian.
 *
 * No edita la migración que creó la tabla: alguien ya pudo correrla (ADR-0016, regla 9).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('alter table comisiones alter column id_plazas drop not null');
        DB::statement('drop index if exists comisiones_global_abierta_unique');

        $this->convertirLasGlobalesDeCadaPlaza();

        DB::statement(
            'alter table comisiones add constraint comisiones_global_o_propia_check
                check ((id_lavanderias is null) = (id_plazas is null))',
        );
        DB::statement(
            'create unique index comisiones_global_abierta_unique on comisiones ((id_lavanderias is null))
                where id_lavanderias is null and vigente_hasta is null',
        );
    }

    /**
     * Las globales de cada plaza, a la global del sistema: una sola abierta, la que se fijó al último.
     */
    private function convertirLasGlobalesDeCadaPlaza(): void
    {
        $conservada = 'select id_comisiones, vigente_desde from comisiones
            where id_lavanderias is null and vigente_hasta is null
            order by vigente_desde desc, id_comisiones desc
            limit 1';

        // Las que empezaron en el mismo instante que la conservada nunca rigieron solas.
        DB::statement("delete from comisiones c using ({$conservada}) k
            where c.id_lavanderias is null and c.vigente_hasta is null
                and c.id_comisiones <> k.id_comisiones and c.vigente_desde >= k.vigente_desde");

        // Las demás abiertas se cierran cuando empezó la conservada.
        DB::statement("update comisiones c set vigente_hasta = k.vigente_desde, updated_at = now()
            from ({$conservada}) k
            where c.id_lavanderias is null and c.vigente_hasta is null and c.id_comisiones <> k.id_comisiones");

        DB::statement('update comisiones set id_plazas = null, updated_at = now() where id_lavanderias is null');
    }

    /**
     * Solo hacia adelante (ADR-0016, regla 9).
     */
    public function down(): void
    {
        //
    }
};
