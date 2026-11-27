<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La administración da de baja un registro de repartidor que EL CLIENTE no autoriza (repartidor-E31;
 * NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #2, "Alta, baja, suspensión y edición de socios repartidores";
 * :18, Módulo C #1, la autorización es de EL CLIENTE, fuera de la plataforma). Lo decidió el concilio del 2026-11-24,
 * por delegación de Arthur (C-245; acta-2026-11-20-modelo-y-pasarela.md, decisión 54).
 *
 * - repartidores_plaza_check deja de exigir la plaza en una baja que nunca tuvo alta: la plaza queda vacía si el
 *   repartidor está pendiente, o si está de baja con aprobado_en vacía. Un registro pendiente no tiene plaza (design.md,
 *   lectura 2), y darlo de baja no se la pone.
 * - La exige igual que antes en activo, en suspendido y en la baja de uno que sí se dio de alta (aprobado_en llena): ese
 *   ya tenía su plaza y la conserva.
 * - Las filas que ya existen cumplen el CHECK anterior, que es más estricto: no se convierte nada.
 *
 * Lo que puede hacer después una cuenta dada de baja no se decide aquí (DD-104). No edita la migración que creó la
 * tabla: alguien ya pudo correrla (ADR-0016, regla 9).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('alter table repartidores drop constraint repartidores_plaza_check');
        DB::statement(
            "alter table repartidores add constraint repartidores_plaza_check
                check (estado = 'pendiente' or id_plazas is not null or (estado = 'baja' and aprobado_en is null))",
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
